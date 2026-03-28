<?php

namespace Tests\Feature;

use App\Models\MedicalEvaluation;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\ProcedureItem;
use App\Models\User;
use Tests\TestCase;

/**
 * ClinicalRecordViewTest
 *
 * Tests para los dos endpoints de lectura de ClinicalRecordController:
 *
 *   Vista 1 — GET /patients/{patient}/clinical-records
 *   Vista 2 — GET /patients/{patient}/clinical-records/{evaluation}
 *
 * Cubre: acceso, datos devueltos, autorización y visibilidad de precios.
 */
class ClinicalRecordViewTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Vista 1 — patientProfile
    // ─────────────────────────────────────────────

    public function test_vista1_devuelve_datos_basicos_del_paciente(): void
    {
        $remitente = $this->actingAsRemitente();
        $paciente  = Patient::factory()->forRemitente($remitente)->create([
            'first_name'     => 'Juana',
            'last_name'      => 'García',
            'cedula'         => '1234567890',
            'document_type'  => 'Cédula de Ciudadanía',
            'cellphone'      => '3001234567',
            'biological_sex' => 'Femenino',
            'date_of_birth'  => '1990-01-01',
        ]);

        $response = $this->getJson("/api/v1/patients/{$paciente->id}/clinical-records");

        $response->assertOk();

        // Verifica que los campos requeridos por la vista están presentes
        $patient = $response->json('data.patient');
        $this->assertArrayHasKey('full_name',      $patient);
        $this->assertArrayHasKey('document_type',  $patient);
        $this->assertArrayHasKey('cedula',         $patient);
        $this->assertArrayHasKey('cellphone',      $patient);
        $this->assertArrayHasKey('date_of_birth',  $patient);
        $this->assertArrayHasKey('age',            $patient);
        $this->assertArrayHasKey('biological_sex', $patient);

        // first_name y last_name no deben viajar — el frontend usa full_name
        $this->assertArrayNotHasKey('first_name', $patient);
        $this->assertArrayNotHasKey('last_name',  $patient);
    }

    public function test_vista1_devuelve_tarjetas_de_evaluaciones(): void
    {
        $remitente = $this->actingAsRemitente();
        $paciente  = Patient::factory()->forRemitente($remitente)->create();

        MedicalEvaluation::factory()->count(3)->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $response = $this->getJson("/api/v1/patients/{$paciente->id}/clinical-records");

        $response->assertOk();
        $this->assertCount(3, $response->json('data.evaluations'));
    }

    public function test_vista1_tarjeta_tiene_campos_correctos(): void
    {
        $remitente = $this->actingAsRemitente();
        $paciente  = Patient::factory()->forRemitente($remitente)->create();

        MedicalEvaluation::factory()->create([
            'patient_id'    => $paciente->id,
            'user_id'       => $remitente->id,
            'referrer_name' => $remitente->name,
        ]);

        $response = $this->getJson("/api/v1/patients/{$paciente->id}/clinical-records");

        $tarjeta = $response->json('data.evaluations.0');

        // Campos necesarios para la tarjeta
        $this->assertArrayHasKey('status',         $tarjeta);
        $this->assertArrayHasKey('referrer_name',  $tarjeta);
        $this->assertArrayHasKey('procedure_date', $tarjeta);

        // Precios no deben viajar en las tarjetas
        $this->assertArrayNotHasKey('total_amount', $tarjeta);
    }

    public function test_vista1_muestra_evaluaciones_de_todos_los_remitentes(): void
    {
        // El paciente fue atendido por dos remitentes distintos
        $remitente1 = $this->actingAsRemitente();
        $remitente2 = User::factory()->remitente()->create();

        $paciente = Patient::factory()->forRemitente($remitente1)->create();

        MedicalEvaluation::factory()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente1->id,
        ]);

        MedicalEvaluation::factory()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente2->id,
        ]);

        $response = $this->getJson("/api/v1/patients/{$paciente->id}/clinical-records");

        $response->assertOk();

        // Vista 1 debe mostrar las 2 evaluaciones — de ambos remitentes
        $this->assertCount(2, $response->json('data.evaluations'));
    }

    public function test_vista1_devuelve_403_si_paciente_es_de_otro_remitente(): void
    {
        $this->actingAsRemitente();

        $otroRemitente = User::factory()->remitente()->create();
        $pacienteAjeno = Patient::factory()->forRemitente($otroRemitente)->create();

        $this->getJson("/api/v1/patients/{$pacienteAjeno->id}/clinical-records")
            ->assertForbidden();
    }

    public function test_vista1_admin_puede_ver_cualquier_paciente(): void
    {
        $this->actingAsAdmin();

        $remitente = User::factory()->remitente()->create();
        $paciente  = Patient::factory()->forRemitente($remitente)->create();

        $this->getJson("/api/v1/patients/{$paciente->id}/clinical-records")
            ->assertOk();
    }

    public function test_vista1_devuelve_evaluaciones_vacias_si_no_hay_registros(): void
    {
        $remitente = $this->actingAsRemitente();
        $paciente  = Patient::factory()->forRemitente($remitente)->create();

        $response = $this->getJson("/api/v1/patients/{$paciente->id}/clinical-records");

        $response->assertOk();
        $this->assertCount(0, $response->json('data.evaluations'));
    }

    // ─────────────────────────────────────────────
    // Vista 2 — registro clínico completo
    // ─────────────────────────────────────────────

    public function test_vista2_devuelve_registro_clinico_completo(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $response = $this->getJson(
            "/api/v1/patients/{$paciente->id}/clinical-records/{$evaluation->id}"
        );

        $response->assertOk();

        // Datos del paciente
        $this->assertArrayHasKey('patient', $response->json('data'));

        // Datos clínicos de la evaluación
        $this->assertArrayHasKey('weight',              $response->json('data'));
        $this->assertArrayHasKey('height',              $response->json('data'));
        $this->assertArrayHasKey('bmi',                 $response->json('data'));
        $this->assertArrayHasKey('medical_background',  $response->json('data'));

        // Procedimientos
        $this->assertArrayHasKey('procedures', $response->json('data'));
    }

    public function test_vista2_remitente_ve_precios_en_su_propia_evaluacion(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $response = $this->getJson(
            "/api/v1/patients/{$paciente->id}/clinical-records/{$evaluation->id}"
        );

        $response->assertOk();

        // Si hay procedimientos, los precios deben ser visibles
        foreach ($response->json('data.procedures') as $procedure) {
            $this->assertArrayHasKey('total_amount', $procedure);
            foreach ($procedure['items'] as $item) {
                $this->assertArrayHasKey('price', $item);
            }
        }
    }

    public function test_vista2_remitente_no_ve_precios_en_evaluacion_ajena(): void
    {
        $remitente1 = $this->actingAsRemitente();
        $remitente2 = User::factory()->remitente()->create();

        // Paciente del remitente 1
        $paciente = Patient::factory()->forRemitente($remitente1)->create();

        // Evaluación creada por remitente 2 sobre el mismo paciente
        $evaluation = MedicalEvaluation::factory()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente2->id,
        ]);

        // Procedimiento con precio
        $procedure = Procedure::factory()->create([
            'medical_evaluation_id' => $evaluation->id,
            'total_amount'          => 300000,
        ]);

        $response = $this->getJson(
            "/api/v1/patients/{$paciente->id}/clinical-records/{$evaluation->id}"
        );

        $response->assertOk();

        // total_amount no debe viajar
        foreach ($response->json('data.procedures') as $proc) {
            $this->assertArrayNotHasKey('total_amount', $proc);
            foreach ($proc['items'] as $item) {
                $this->assertArrayNotHasKey('price', $item);
            }
        }
    }

    public function test_vista2_admin_siempre_ve_precios(): void
    {
        $this->actingAsAdmin();

        $remitente  = User::factory()->remitente()->create();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        Procedure::factory()->create([
            'medical_evaluation_id' => $evaluation->id,
            'total_amount'          => 300000,
        ]);

        $response = $this->getJson(
            "/api/v1/patients/{$paciente->id}/clinical-records/{$evaluation->id}"
        );

        $response->assertOk();

        foreach ($response->json('data.procedures') as $proc) {
            $this->assertArrayHasKey('total_amount', $proc);
        }
    }

    public function test_vista2_devuelve_404_si_evaluacion_no_corresponde_al_paciente(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente1  = Patient::factory()->forRemitente($remitente)->create();
        $paciente2  = Patient::factory()->forRemitente($remitente)->create();

        // Evaluación del paciente 2
        $evaluation = MedicalEvaluation::factory()->create([
            'patient_id' => $paciente2->id,
            'user_id'    => $remitente->id,
        ]);

        // Intentar acceder con el ID del paciente 1 pero la evaluación del paciente 2
        $this->getJson(
            "/api/v1/patients/{$paciente1->id}/clinical-records/{$evaluation->id}"
        )->assertNotFound();
    }

    public function test_vista2_devuelve_403_si_paciente_es_de_otro_remitente(): void
    {
        $this->actingAsRemitente();

        $otroRemitente = User::factory()->remitente()->create();
        $pacienteAjeno = Patient::factory()->forRemitente($otroRemitente)->create();
        $evaluation    = MedicalEvaluation::factory()->create([
            'patient_id' => $pacienteAjeno->id,
            'user_id'    => $otroRemitente->id,
        ]);

        $this->getJson(
            "/api/v1/patients/{$pacienteAjeno->id}/clinical-records/{$evaluation->id}"
        )->assertForbidden();
    }

    public function test_vista2_is_own_es_true_en_evaluacion_propia(): void
    {
        $remitente  = $this->actingAsRemitente();
        $paciente   = Patient::factory()->forRemitente($remitente)->create();
        $evaluation = MedicalEvaluation::factory()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente->id,
        ]);

        $response = $this->getJson(
            "/api/v1/patients/{$paciente->id}/clinical-records/{$evaluation->id}"
        );

        $response->assertOk()
            ->assertJsonPath('data.is_own', true);
    }

    public function test_vista2_is_own_es_false_en_evaluacion_ajena(): void
    {
        $remitente1 = $this->actingAsRemitente();
        $remitente2 = User::factory()->remitente()->create();

        $paciente   = Patient::factory()->forRemitente($remitente1)->create();
        $evaluation = MedicalEvaluation::factory()->create([
            'patient_id' => $paciente->id,
            'user_id'    => $remitente2->id,
        ]);

        $response = $this->getJson(
            "/api/v1/patients/{$paciente->id}/clinical-records/{$evaluation->id}"
        );

        $response->assertOk()
            ->assertJsonPath('data.is_own', false);
    }
}