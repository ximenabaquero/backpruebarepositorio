<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * SecurityTest
 *
 * Tests de seguridad y rendimiento lógico.
 * Solo cubre lo que tiene valor real de detección:
 *
 *   1. Rate limiting en login — protección contra fuerza bruta
 *   2. N+1 en monthComparison — regresión de rendimiento crítica
 *   3. Unique constraint de cédula — integridad de datos
 */
class SecurityTest extends TestCase
{
    // ─────────────────────────────────────────────
    // 1. Rate limiting — fuerza bruta en login
    // ─────────────────────────────────────────────

    /**
     * Después de 5 intentos fallidos desde la misma IP,
     * el endpoint devuelve 429 Too Many Requests.
     */
    public function test_login_se_bloquea_despues_de_5_intentos(): void
    {
        // Limpiar el rate limiter antes del test para evitar
        // interferencia con otros tests que usen la misma IP
        RateLimiter::clear('login|127.0.0.1');

        $payload = [
            'email'    => 'noexiste@test.com',
            'password' => 'wrongpassword',
        ];

        // Los primeros 5 intentos deben pasar el rate limiter
        // (pueden devolver 401 por credenciales incorrectas, pero no 429)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/login', $payload);
            $this->assertNotEquals(429, $response->status(), "El intento {$i} no debería bloquearse");
        }

        // El 6to intento debe ser bloqueado por el rate limiter
        $this->postJson('/api/v1/login', $payload)
            ->assertStatus(429);
    }

    /**
     * El rate limiter también limita por email+IP.
     * Una misma cuenta no puede ser atacada desde múltiples intentos.
     */
    public function test_login_limita_por_email_y_ip(): void
    {
        RateLimiter::clear('login|noexiste@test.com|127.0.0.1');

        // Verificar que el limiter por email+IP existe y funciona
        // independientemente del limiter por IP sola
        $payload = ['email' => 'victima@test.com', 'password' => 'wrong'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', $payload);
        }

        $this->postJson('/api/v1/login', $payload)
            ->assertStatus(429);
    }

    // ─────────────────────────────────────────────
    // 2. N+1 — monthComparison
    // ─────────────────────────────────────────────

    /**
     * monthComparison fue refactorizado de ~480 queries a 8.
     * Este test detecta regresiones — si alguien introduce un loop
     * diario con queries, el contador superará el umbral.
     *
     * Umbral conservador de 20 queries para evitar falsos positivos
     * por queries de auth/middleware, sin perder el valor de detección.
     */
    public function test_month_comparison_no_ejecuta_queries_en_loop(): void
    {
        $this->actingAsAdmin();

        DB::enableQueryLog();

        $this->getJson('/api/v1/stats/month-comparison')
            ->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $queryCount = count($queries);

        $this->assertLessThan(
            20,
            $queryCount,
            "monthComparison ejecutó {$queryCount} queries — posible regresión de N+1. " .
            "El refactor redujo de ~480 a ~8. Si superás 20, revisá si se reintrodujo un loop diario."
        );
    }

    // ─────────────────────────────────────────────
    // 3. Unique constraint — cédula de paciente
    // ─────────────────────────────────────────────

    /**
     * La DB debe rechazar dos pacientes con la misma cédula
     * aunque el frontend valide — la constraint es la última línea de defensa.
     *
     * Cubre el caso de condición de carrera donde dos remitentes
     * intentan registrar el mismo paciente simultáneamente.
     */
    public function test_no_permite_dos_pacientes_con_misma_cedula(): void
    {
        $remitente = $this->actingAsRemitente();

        // Crear el primer paciente
        Patient::factory()->forRemitente($remitente)->create([
            'cedula' => '1234567890',
        ]);

        // Intentar crear un segundo con la misma cédula
        // debe fallar por el unique constraint de DB
        $this->expectException(\Illuminate\Database\QueryException::class);

        Patient::factory()->forRemitente($remitente)->create([
            'cedula' => '1234567890',
        ]);
    }

    /**
     * El endpoint de creación de pacientes devuelve 200 con el paciente
     * existente cuando la cédula ya está registrada — no lanza error 500.
     * Verifica que la lógica de deduplicación funciona correctamente.
     */
    public function test_endpoint_maneja_cedula_duplicada_correctamente(): void
    {
        $remitente = $this->actingAsRemitente();

        Patient::factory()->forRemitente($remitente)->create([
            'cedula' => '9999999999',
        ]);

        // El endpoint debe devolver el paciente existente, no un error
        $this->postJson('/api/v1/patients', [
            'first_name'     => 'Otro',
            'last_name'      => 'Nombre',
            'cedula'         => '9999999999',
            'document_type'  => 'Cédula de Ciudadanía',
            'cellphone'      => '3001234567',
            'biological_sex' => 'Femenino',
            'date_of_birth'  => '1990-01-01',
        ])
        ->assertOk()
        ->assertJsonPath('data.message', 'El paciente ya está registrado en el sistema.');
    }

    /**
     * Un paciente puede ser atendido por múltiples remitentes.
     * Si el remitente 2 intenta registrar un paciente con cédula existente,
     * el sistema devuelve el paciente ya existente — no crea duplicado.
     * Cada remitente puede luego crear sus propias evaluaciones para ese paciente.
     */
    public function test_cedula_duplicada_entre_remitentes_devuelve_paciente_existente(): void
    {
        $remitente1 = User::factory()->remitente()->create();
        $remitente2 = User::factory()->remitente()->create();

        // Remitente 1 registra a Juana
        $pacienteOriginal = Patient::factory()->forRemitente($remitente1)->create([
            'cedula' => '5555555555',
        ]);

        // Remitente 2 intenta registrar a Juana con la misma cédula
        $this->actingAs($remitente2);

        $response = $this->postJson('/api/v1/patients', [
            'first_name'     => 'Juana',
            'last_name'      => 'García',
            'cedula'         => '5555555555',
            'document_type'  => 'Cédula de Ciudadanía',
            'cellphone'      => '3009876543',
            'biological_sex' => 'Femenino',
            'date_of_birth'  => '1985-06-15',
        ]);

        // Devuelve el paciente existente — no se crea un duplicado
        $response->assertOk()
            ->assertJsonPath('data.message', 'El paciente ya está registrado en el sistema.');

        // Solo existe 1 registro de Juana en DB
        $this->assertDatabaseCount('patients', 1);

        // El paciente devuelto es el mismo que registró remitente 1
        $this->assertEquals(
            $pacienteOriginal->id,
            $response->json('data.data.id')
        );
    }
}