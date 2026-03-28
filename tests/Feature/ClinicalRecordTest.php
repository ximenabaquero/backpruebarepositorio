<?php

namespace Tests\Feature;

use App\Models\MedicalEvaluation;
use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class ClinicalRecordTest extends TestCase
{
    private array $validPayload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validPayload = [
            'patient' => [
                'first_name'     => 'María',
                'last_name'      => 'García',
                'cedula'         => '1234567890',
                'document_type'  => 'Cédula de Ciudadanía',
                'cellphone'      => '3001234567',
                'biological_sex' => 'Femenino',
                'date_of_birth'  => '1990-01-01',
            ],
            'evaluation' => [
                'weight'             => 65,
                'height'             => 1.65,
                'medical_background' => 'Sin antecedentes relevantes',
            ],
            'procedure' => [
                'notes' => 'Primera consulta de valoración',
                'items' => [
                    ['item_name' => 'Consulta inicial', 'price' => 150000],
                    ['item_name' => 'Valoración corporal', 'price' => 80000],
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────
    // Flujo 1 — Paciente nuevo
    // ─────────────────────────────────────────────

    public function test_remitente_puede_crear_registro_clinico_completo(): void
    {
        $this->actingAsRemitente();

        $response = $this->postJson('/api/v1/clinical-records', $this->validPayload);

        $response->assertCreated()
            ->assertJsonPath('error', null)
            ->assertJsonPath('data.message', 'Registro clínico creado correctamente')
            ->assertJsonStructure(['data' => ['message', 'patient', 'evaluation']]);

        // Verificar que se crearon los 3 registros en DB
        $this->assertDatabaseHas('patients', ['cedula' => '1234567890']);
        $this->assertDatabaseHas('medical_evaluations', ['status' => 'EN_ESPERA']);
        $this->assertDatabaseCount('procedures', 1);
        $this->assertDatabaseCount('procedure_items', 2);
    }

    public function test_bmi_se_calcula_correctamente_al_crear(): void
    {
        $this->actingAsRemitente();

        $this->postJson('/api/v1/clinical-records', $this->validPayload);

        // BMI = 65 / (1.65^2) = 23.88
        $this->assertDatabaseHas('medical_evaluations', [
            'bmi' => 23.88,
        ]);
    }

    public function test_total_amount_se_calcula_desde_items(): void
    {
        $this->actingAsRemitente();

        $this->postJson('/api/v1/clinical-records', $this->validPayload);

        // 150000 + 80000 = 230000
        $this->assertDatabaseHas('procedures', ['total_amount' => 230000]);
    }

    public function test_referrer_name_coincide_con_nombre_del_usuario(): void
    {
        $remitente = $this->actingAsRemitente();

        $this->postJson('/api/v1/clinical-records', $this->validPayload);

        $this->assertDatabaseHas('medical_evaluations', [
            'referrer_name' => $remitente->name,
        ]);
    }

    public function test_paciente_existente_devuelve_409(): void
    {
        $remitente = $this->actingAsRemitente();

        // Crear el paciente primero
        Patient::factory()->forRemitente($remitente)->create([
            'cedula' => '1234567890',
        ]);

        $response = $this->postJson('/api/v1/clinical-records', $this->validPayload);

        $response->assertStatus(409)
            ->assertJsonPath('error', 'PATIENT_EXISTS')
            ->assertJsonPath('data.message', 'El paciente ya está registrado en el sistema.');
    }

    public function test_paciente_existente_no_se_duplica_en_db(): void
    {
        $remitente = $this->actingAsRemitente();
        Patient::factory()->forRemitente($remitente)->create(['cedula' => '1234567890']);

        $this->postJson('/api/v1/clinical-records', $this->validPayload);

        // Sigue habiendo solo 1 paciente con esa cédula
        $this->assertDatabaseCount('patients', 1);
    }

    public function test_si_falla_la_evaluacion_no_se_crea_el_paciente(): void
    {
        $this->actingAsRemitente();

        // Payload inválido — height fuera de rango rompe la evaluación
        $payload = $this->validPayload;
        $payload['evaluation']['height'] = 99;

        $this->postJson('/api/v1/clinical-records', $payload);

        // La transacción hizo rollback — no se creó el paciente
        $this->assertDatabaseMissing('patients', ['cedula' => '1234567890']);
    }

    // ─────────────────────────────────────────────
    // Flujo 2 — Paciente existente, nuevo registro
    // ─────────────────────────────────────────────

    public function test_remitente_puede_crear_registro_para_paciente_existente(): void
    {
        $remitente = $this->actingAsRemitente();
        $patient   = Patient::factory()->forRemitente($remitente)->create();

        $response = $this->postJson("/api/v1/patients/{$patient->id}/clinical-records", [
            'evaluation' => [
                'weight'             => 70,
                'height'             => 1.70,
                'medical_background' => 'Sin antecedentes',
            ],
            'procedure' => [
                'notes' => 'Segunda sesión',
                'items' => [
                    ['item_name' => 'Láser básico', 'price' => 200000],
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.message', 'Registro clínico creado correctamente');

        $this->assertDatabaseHas('medical_evaluations', ['patient_id' => $patient->id]);
    }

    public function test_remitente_no_puede_crear_registro_para_paciente_de_otro(): void
    {
        $this->actingAsRemitente();

        // Paciente de otro remitente
        $otroRemitente = User::factory()->remitente()->create();
        $patient = Patient::factory()->forRemitente($otroRemitente)->create();

        $this->postJson("/api/v1/patients/{$patient->id}/clinical-records", [
            'evaluation' => [
                'weight' => 70, 'height' => 1.70,
                'medical_background' => 'Sin antecedentes',
            ],
            'procedure' => [
                'notes' => 'Sesión',
                'items' => [['item_name' => 'Láser', 'price' => 200000]],
            ],
        ])->assertForbidden();
    }

    // ─────────────────────────────────────────────
    // Validaciones
    // ─────────────────────────────────────────────

    public function test_falla_sin_items_de_procedimiento(): void
    {
        $this->actingAsRemitente();

        $payload = $this->validPayload;
        $payload['procedure']['items'] = [];

        $this->postJson('/api/v1/clinical-records', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['procedure.items']);
    }

    public function test_falla_sin_notas_de_procedimiento(): void
    {
        $this->actingAsRemitente();

        $payload = $this->validPayload;
        unset($payload['procedure']['notes']);

        $this->postJson('/api/v1/clinical-records', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['procedure.notes']);
    }

    public function test_falla_con_altura_fuera_de_rango(): void
    {
        $this->actingAsRemitente();

        $payload = $this->validPayload;
        $payload['evaluation']['height'] = 3.0;

        $this->postJson('/api/v1/clinical-records', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['evaluation.height']);
    }

    public function test_admin_puede_crear_registro_clinico(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/clinical-records', $this->validPayload)
            ->assertCreated();
    }
}