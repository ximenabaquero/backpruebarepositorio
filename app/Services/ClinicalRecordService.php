<?php

namespace App\Services;

use App\Models\MedicalEvaluation;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ClinicalRecordService
{
    public function __construct(
        private readonly MedicalEvaluationService $evaluationService,
        private readonly ProcedureService $procedureService,
    ) {}

    // ─────────────────────────────────────────────
    // LECTURA
    // ─────────────────────────────────────────────

    /**
     * Vista 1 — Perfil del paciente con tarjetas de evaluaciones.
     *
     * Devuelve los datos básicos del paciente y la lista de tarjetas.
     * Cada tarjeta muestra: fecha del procedimiento, nombre del remitente, estado.
     *
     * Se cargan TODAS las evaluaciones del paciente — incluso las de
     * otros remitentes — porque el paciente puede haber sido atendido
     * por múltiples remitentes. El remitente que consulta ya fue
     * verificado como dueño del paciente en el controller.
     *
     * @return array{patient: Patient, evaluations: Collection}
     */
    public function getPatientProfile(Patient $patient): array
    {
        $evaluations = MedicalEvaluation::query()
            ->select([
                'id',
                'patient_id',
                'user_id',
                'status',
                'referrer_name',
            ])
            // Fecha del procedimiento para la tarjeta — subquery eficiente
            // evita cargar el objeto Procedure completo solo para la fecha
            ->selectSub(
                Procedure::select('procedure_date')
                    ->whereColumn('medical_evaluation_id', 'medical_evaluations.id')
                    ->latest('procedure_date')
                    ->limit(1),
                'procedure_date'
            )
            ->where('patient_id', $patient->id)
            ->orderByDesc('procedure_date')
            ->get();

        return [
            'patient'     => $patient->only([
                'id',
                'full_name',    // accessor del modelo
                'document_type',
                'cedula',
                'cellphone',
                'date_of_birth',
                'age',          // accessor del modelo
                'biological_sex',
            ]),
            'evaluations' => $evaluations,
        ];
    }

    /**
     * Vista 2 — Registro clínico completo.
     *
     * Devuelve la evaluación con todos sus datos clínicos,
     * los datos completos del paciente (incluyendo edad calculada),
     * procedimientos e items.
     *
     * El controller se encarga de ocultar precios si la evaluación
     * es de otro remitente.
     */
    public function getFullRecord(MedicalEvaluation $evaluation): MedicalEvaluation
    {
        $evaluation->load([
            // Datos completos del paciente — mismos campos que Vista 1
            // más antecedentes médicos que en las tarjetas no se muestran
            'patient:id,user_id,first_name,last_name,document_type,cedula,cellphone,date_of_birth,biological_sex',
            'procedures:id,medical_evaluation_id,procedure_date,total_amount,notes',
            'procedures.items:id,procedure_id,item_name,price',
            'user:id,name,first_name,last_name,brand_name',
            'confirmedBy:id,first_name,last_name',
            'canceledBy:id,first_name,last_name',
        ]);

        // Appends calculados del modelo Patient que no viven en DB
        // age es accessors — hay que forzarlos en la serialización
        $evaluation->patient->append(['age']);

        return $evaluation;
    }

    // ─────────────────────────────────────────────
    // ESCRITURA
    // ─────────────────────────────────────────────

    /**
     * Flujo 1 — Pantalla "Registrar paciente"
     *
     * Crea paciente + evaluación + procedimiento en una transacción atómica.
     * Si el paciente ya existe por cédula, lanza RuntimeException con los
     * datos del paciente para que el controller redirija al historial.
     *
     * @return array{patient: Patient, evaluation: MedicalEvaluation}
     * @throws \RuntimeException si el paciente ya existe
     */
    public function createWithPatient(array $data, User $user): array
    {
        return DB::transaction(function () use ($data, $user) {
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