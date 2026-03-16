<?php

namespace App\Services;

use App\Models\MedicalEvaluation;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClinicalRecordService
{
    public function __construct(
        private readonly MedicalEvaluationService $evaluationService,
        private readonly ProcedureService $procedureService,
    ) {}

    /**
     * Flujo 1 — Pantalla "Registrar paciente"
     *
     * Crea paciente + evaluación + procedimiento en una transacción atómica.
     * Si el paciente ya existe por cédula, devuelve error controlado
     * para que el frontend redirija al historial del paciente existente.
     *
     * @return array{patient: Patient, evaluation: MedicalEvaluation}
     * @throws \RuntimeException si el paciente ya existe
     */
    public function createWithPatient(array $data, User $user): array
    {
        return DB::transaction(function () use ($data, $user) {
            // Si ya existe, lanzar excepción con el paciente para que
            // el controller pueda devolver sus datos al frontend
            $existing = Patient::where('cedula', $data['patient']['cedula'])->first();
            if ($existing) {
                throw new \RuntimeException(
                    json_encode([
                        'code'    => 'PATIENT_EXISTS',
                        'message' => 'El paciente ya está registrado en el sistema.',
                        'patient' => $existing,
                    ])
                );
            }

            $patient = $this->createPatient($data['patient'], $user);

            $evaluation = $this->createEvaluationAndProcedure(
                $patient->id,
                $data['evaluation'],
                $data['procedure'],
                $user
            );

            return compact('patient', 'evaluation');
        });
    }

    /**
     * Flujo 2 — Pantalla "Historial del paciente" → "Nuevo registro"
     *
     * El paciente ya existe — solo crea evaluación + procedimiento.
     * El patient_id viene de la URL, verificado por route model binding.
     *
     * @return array{evaluation: MedicalEvaluation}
     */
    public function createForExistingPatient(
        Patient $patient,
        array $data,
        User $user
    ): array {
        return DB::transaction(function () use ($patient, $data, $user) {
            $evaluation = $this->createEvaluationAndProcedure(
                $patient->id,
                $data['evaluation'],
                $data['procedure'],
                $user
            );

            return compact('evaluation');
        });
    }

    // ─────────────────────────────────────────────
    // Privado
    // ─────────────────────────────────────────────

    /**
     * Lógica compartida entre los dos flujos:
     * crear evaluación médica + procedimiento para un patient_id dado.
     */
    private function createEvaluationAndProcedure(
        int $patientId,
        array $evaluationData,
        array $procedureData,
        User $user
    ): MedicalEvaluation {
        $evaluation = $this->evaluationService->create(
            array_merge($evaluationData, ['patient_id' => $patientId]),
            $user
        );

        $this->procedureService->create(
            array_merge($procedureData, [
                'medical_evaluation_id' => $evaluation->id,
            ])
        );

        return $evaluation;
    }

    private function createPatient(array $data, User $user): Patient
    {
        return Patient::create([
            'user_id'        => $user->id,
            'first_name'     => $this->formatName($data['first_name']),
            'last_name'      => $this->formatName($data['last_name']),
            'cellphone'      => $data['cellphone'],
            'date_of_birth'  => $data['date_of_birth'],
            'biological_sex' => $data['biological_sex'],
            'document_type'  => $data['document_type'],
            'cedula'         => $data['cedula'],
        ]);
    }

    private function formatName(string $name): string
    {
        return implode(' ', array_map(
            fn(string $word) => mb_strtoupper(mb_substr($word, 0, 1))
                              . mb_strtolower(mb_substr($word, 1)),
            explode(' ', trim($name))
        ));
    }
}