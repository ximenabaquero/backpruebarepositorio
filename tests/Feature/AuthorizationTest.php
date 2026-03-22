<?php

namespace Tests\Feature;

use App\Models\MedicalEvaluation;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\User;
use Tests\TestCase;

/**
 * AuthorizationTest
 *
 * Tests de autorización por rol — verifica que un remitente
 * no puede operar sobre evaluaciones o procedimientos ajenos.
 *
 * Complementa SecurityTest (rate limiting, cédula única) y
 * ClinicalRecordViewTest (lectura).
 *
 * Cubre:
 *   1. Editar evaluación ajena → 403
 *   2. Confirmar evaluación ajena → 403
 *   3. Cancelar evaluación ajena → 403
 *   4. Editar procedimiento ajeno → 403
 *   5. Contraseña débil al crear remitente → 422
 *   6. Contraseña débil al actualizar remitente → 422
 */
class AuthorizationTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    /**
     * Crea un paciente del remitente1 con una evaluación del remitente2.
     * Escenario real: Juana fue registrada por remitente1 pero en algún
     * momento remitente2 también la atendió.
     */
    private function evaluacionAjena(): array
    {
        $remitente1 = $this->actingAsRemitente();
        $remitente2 = User::factory()->remitente()->create();

        $paciente   = Patient::factory()->forRemitente($remitente1)->create();
        $evaluation = MedicalEvaluation::factory()->enEspera()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente2->id,
        ]);

        return compact('remitente1', 'remitente2', 'paciente', 'evaluation');
    }

    // ─────────────────────────────────────────────
    // 1. Evaluaciones — escritura
    // ─────────────────────────────────────────────

    public function test_remitente_no_puede_editar_evaluacion_ajena(): void
    {
        ['evaluation' => $evaluation] = $this->evaluacionAjena();

        $this->putJson("/api/v1/medical-evaluations/{$evaluation->id}", [
            'weight' => 70,
        ])->assertForbidden();
    }

    public function test_remitente_no_puede_confirmar_evaluacion_ajena(): void
    {
        ['evaluation' => $evaluation] = $this->evaluacionAjena();

        $this->patchJson("/api/v1/medical-evaluations/{$evaluation->id}/confirmar", [
            'terms_accepted'    => true,
            'patient_signature' => 'data:image/png;base64,fake',
        ])->assertForbidden();
    }

    public function test_remitente_no_puede_cancelar_evaluacion_ajena(): void
    {
        ['evaluation' => $evaluation] = $this->evaluacionAjena();

        $this->patchJson("/api/v1/medical-evaluations/{$evaluation->id}/cancelar")
            ->assertForbidden();
    }

    public function test_remitente_puede_editar_su_propia_evaluacion(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->enEspera()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $this->putJson("/api/v1/medical-evaluations/{$evaluation->id}", [
            'weight' => 70,
        ])->assertOk();
    }

    public function test_admin_puede_editar_cualquier_evaluacion(): void
    {
        $this->actingAsAdmin();

        $remitente  = User::factory()->remitente()->create();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->enEspera()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $this->putJson("/api/v1/medical-evaluations/{$evaluation->id}", [
            'weight' => 70,
        ])->assertOk();
    }

    // ─────────────────────────────────────────────
    // 2. Procedimientos — escritura
    // ─────────────────────────────────────────────

    public function test_remitente_no_puede_editar_procedimiento_ajeno(): void
    {
        // Evaluación de otro remitente
        $remitente1 = $this->actingAsRemitente();
        $remitente2 = User::factory()->remitente()->create();

        $paciente   = Patient::factory()->forRemitente($remitente1)->create();
        $evaluation = MedicalEvaluation::factory()->enEspera()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente2->id,
        ]);

        $procedure = Procedure::factory()->sinItems()->create([
            'medical_evaluation_id' => $evaluation->id,
        ]);

        $this->putJson("/api/v1/procedures/{$procedure->id}", [
            'notes' => 'Intento de edición no autorizada',
        ])->assertForbidden();
    }

    public function test_remitente_puede_editar_su_propio_procedimiento(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->enEspera()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $procedure = Procedure::factory()->sinItems()->create([
            'medical_evaluation_id' => $evaluation->id,
        ]);

        $this->putJson("/api/v1/procedures/{$procedure->id}", [
            'notes' => 'Actualización correcta',
        ])->assertOk();
    }

    public function test_admin_puede_editar_cualquier_procedimiento(): void
    {
        $this->actingAsAdmin();

        $remitente  = User::factory()->remitente()->create();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->enEspera()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $procedure = Procedure::factory()->sinItems()->create([
            'medical_evaluation_id' => $evaluation->id,
        ]);

        $this->putJson("/api/v1/procedures/{$procedure->id}", [
            'notes' => 'Actualización por admin',
        ])->assertOk();
    }

    // ─────────────────────────────────────────────
    // 3. Contraseña — requisitos
    // ─────────────────────────────────────────────

    public function test_no_se_puede_crear_remitente_con_contrasena_debil(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/remitentes', [
            'name'                  => 'remitente1',
            'first_name'            => 'Test',
            'last_name'             => 'User',
            'cellphone'             => '3001234567',
            'email'                 => 'test@test.com',
            'password'              => '123456', // sin mayúscula, símbolo ni longitud suficiente
            'password_confirmation' => '123456',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['password']);
    }

    public function test_no_se_puede_crear_remitente_sin_confirmacion_de_contrasena(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/remitentes', [
            'name'       => 'remitente1',
            'first_name' => 'Test',
            'last_name'  => 'User',
            'cellphone'  => '3001234567',
            'email'      => 'test@test.com',
            'password'   => 'Password1!',
            // password_confirmation ausente
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['password']);
    }

    public function test_contrasena_valida_crea_remitente_correctamente(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/remitentes', [
            'name'                  => 'remitente_test',
            'first_name'            => 'Test',
            'last_name'             => 'User',
            'cellphone'             => '3001234567',
            'email'                 => 'remitente@test.com',
            'password'              => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertCreated();
    }

    public function test_no_se_puede_actualizar_remitente_con_contrasena_debil(): void
    {
        $this->actingAsAdmin();

        $remitente = User::factory()->remitente()->create();

        $this->putJson("/api/v1/remitentes/{$remitente->id}", [
            'password'              => 'debil',
            'password_confirmation' => 'debil',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['password']);
    }

    public function test_contrasena_mayor_a_64_caracteres_es_rechazada(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/remitentes', [
            'name'                  => 'remitente1',
            'first_name'            => 'Test',
            'last_name'             => 'User',
            'cellphone'             => '3001234567',
            'email'                 => 'test@test.com',
            'password'              => str_repeat('Abc1!', 13) . 'extra', // 70 chars
            'password_confirmation' => str_repeat('Abc1!', 13) . 'extra',
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['password']);
    }
}