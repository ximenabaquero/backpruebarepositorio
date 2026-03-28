<?php

namespace Tests\Feature;

use App\Models\MedicalEvaluation;
use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class MedicalEvaluationTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Lectura — ahora vive en ClinicalRecordController
    // ─────────────────────────────────────────────

    public function test_remitente_solo_ve_evaluaciones_de_su_paciente(): void
    {
        $remitente     = $this->actingAsRemitente();
        $otroRemitente = User::factory()->remitente()->create();

        // Mi paciente con una evaluación mía
        $miPaciente = Patient::factory()->forRemitente($remitente)->create();
        MedicalEvaluation::factory()->create([
            'patient_id' => $miPaciente->id,
            'user_id'    => $remitente->id,
        ]);

        // Paciente ajeno — no debo poder acceder
        $pacienteAjeno = Patient::factory()->forRemitente($otroRemitente)->create();
        MedicalEvaluation::factory()->create([
            'patient_id' => $pacienteAjeno->id,
            'user_id'    => $otroRemitente->id,
        ]);

        // Vista 1 de mi paciente — devuelve sus evaluaciones
        $response = $this->getJson("/api/v1/patients/{$miPaciente->id}/clinical-records");

        $response->assertOk();
        $this->assertCount(1, $response->json('data.evaluations'));
    }

    public function test_remitente_no_puede_ver_evaluaciones_de_paciente_ajeno(): void
    {
        $this->actingAsRemitente();

        $otroRemitente = User::factory()->remitente()->create();
        $pacienteAjeno = Patient::factory()->forRemitente($otroRemitente)->create();

        MedicalEvaluation::factory()->create([
            'patient_id' => $pacienteAjeno->id,
            'user_id'    => $otroRemitente->id,
        ]);

        // Intentar acceder al perfil de un paciente ajeno → 403
        $this->getJson("/api/v1/patients/{$pacienteAjeno->id}/clinical-records")
            ->assertForbidden();
    }

    public function test_devuelve_lista_vacia_si_paciente_no_tiene_evaluaciones(): void
    {
        $remitente = $this->actingAsRemitente();
        $paciente  = Patient::factory()->forRemitente($remitente)->create();

        // Vista 1 devuelve 200 con array vacío — no 404
        // El 404 era del endpoint anterior que ya no existe
        $response = $this->getJson("/api/v1/patients/{$paciente->id}/clinical-records");

        $response->assertOk();
        $this->assertCount(0, $response->json('data.evaluations'));
    }

    // ─────────────────────────────────────────────
    // Confirmar
    // ─────────────────────────────────────────────

    public function test_remitente_puede_confirmar_su_evaluacion(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->enEspera()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $this->patchJson("/api/v1/medical-evaluations/{$evaluation->id}/confirmar", [
            'terms_accepted'    => true,
            'patient_signature' => 'data:image/png;base64,fake',
        ])->assertOk()
          ->assertJsonPath('data.data.status', 'CONFIRMADO');

        $this->assertDatabaseHas('medical_evaluations', [
            'id'     => $evaluation->id,
            'status' => 'CONFIRMADO',
        ]);
    }

    public function test_confirmar_guarda_campos_de_auditoria(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->enEspera()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $this->patchJson("/api/v1/medical-evaluations/{$evaluation->id}/confirmar", [
            'terms_accepted'    => true,
            'patient_signature' => 'data:image/png;base64,fake',
        ]);

        $evaluation->refresh();

        $this->assertNotNull($evaluation->confirmed_at);
        $this->assertNotNull($evaluation->terms_accepted_at);
        $this->assertEquals($remitente->id, $evaluation->confirmed_by_user_id);
        $this->assertEquals('data:image/png;base64,fake', $evaluation->patient_signature);
    }

    public function test_confirmar_es_idempotente(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->confirmado()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        // Confirmar una valoración ya confirmada no lanza error
        $this->patchJson("/api/v1/medical-evaluations/{$evaluation->id}/confirmar", [
            'terms_accepted'    => true,
            'patient_signature' => 'data:image/png;base64,fake',
        ])->assertOk()
          ->assertJsonPath('data.message', 'La valoración ya está confirmada');
    }

    public function test_no_puede_confirmar_sin_firma(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->enEspera()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $this->patchJson("/api/v1/medical-evaluations/{$evaluation->id}/confirmar", [
            'terms_accepted' => true,
            // patient_signature ausente
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['patient_signature']);
    }

    public function test_remitente_no_puede_confirmar_evaluacion_ajena(): void
    {
        $this->actingAsRemitente();

        $otroRemitente = User::factory()->remitente()->create();
        $paciente = Patient::factory()->forRemitente($otroRemitente)->create();
        $evaluation = MedicalEvaluation::factory()->enEspera()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $otroRemitente->id,
        ]);

        $this->patchJson("/api/v1/medical-evaluations/{$evaluation->id}/confirmar", [
            'terms_accepted'    => true,
            'patient_signature' => 'data:image/png;base64,fake',
        ])->assertForbidden();
    }

    // ─────────────────────────────────────────────
    // Cancelar
    // ─────────────────────────────────────────────

    public function test_remitente_puede_cancelar_su_evaluacion(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->enEspera()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $this->patchJson("/api/v1/medical-evaluations/{$evaluation->id}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.data.status', 'CANCELADO');

        $this->assertDatabaseHas('medical_evaluations', [
            'id'     => $evaluation->id,
            'status' => 'CANCELADO',
        ]);
    }

    public function test_cancelar_limpia_datos_de_confirmacion(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->confirmado()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $this->patchJson("/api/v1/medical-evaluations/{$evaluation->id}/cancelar");

        $evaluation->refresh();
        $this->assertNull($evaluation->confirmed_at);
        $this->assertNull($evaluation->confirmed_by_user_id);
        $this->assertNotNull($evaluation->canceled_at);
    }

    public function test_cancelar_es_idempotente(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->cancelado()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $this->patchJson("/api/v1/medical-evaluations/{$evaluation->id}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.message', 'La valoración ya está cancelada');
    }

    // ─────────────────────────────────────────────
    // Actualizar
    // ─────────────────────────────────────────────

    public function test_no_puede_editar_evaluacion_confirmada(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->confirmado()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $this->putJson("/api/v1/medical-evaluations/{$evaluation->id}", [
            'weight' => 70,
        ])->assertForbidden();
    }

    public function test_actualizar_recalcula_bmi(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->enEspera()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
            'weight'     => 65,
            'height'     => 1.65,
        ]);

        $this->putJson("/api/v1/medical-evaluations/{$evaluation->id}", [
            'weight' => 80,
        ]);

        // BMI = 80 / (1.65^2) = 29.38
        $this->assertDatabaseHas('medical_evaluations', [
            'id'  => $evaluation->id,
            'bmi' => 29.38,
        ]);
    }
}