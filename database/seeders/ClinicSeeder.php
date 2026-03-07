<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Patient;
use App\Models\MedicalEvaluation;
use App\Models\Procedure;
use App\Models\ProcedureItem;
use Carbon\Carbon;
use Faker\Factory as FakerFactory;

class ClinicSeeder extends Seeder
{
    private $faker;

    public function __construct()
    {
        $this->faker = FakerFactory::create();
    }

    public function run()
    {
        // ── Usuarios ─────────────────────────────────────────────
        $admin = User::factory()->admin()->create([
            'email' => 'admin@clinic.com',
        ]);

        $remitentesActivos    = User::factory()->remitente()->count(3)->create();
        $remitentesInactivos  = User::factory()->remitente()->inactivo()->count(2)->create();
        $remitentesDespedidos = User::factory()->remitente()->despedido()->count(2)->create();

        // ── Pacientes ─────────────────────────────────────────────
        $patientsAdmin      = Patient::factory()->count(3)->create(['user_id' => $admin->id]);
        $patientsRemitentes = Patient::factory()->count(5)->create();
        $patients           = $patientsAdmin->concat($patientsRemitentes);

        // Meses disponibles: enero del año actual → mes actual
        $now             = Carbon::now();
        $currentYear     = $now->year;
        $currentMonth    = $now->month;
        $availableMonths = range(1, $currentMonth); // [1, 2, ..., N]

        // ── Evaluaciones y procedimientos ─────────────────────────
        foreach ($patients as $patient) {
            // Más registros por paciente para tener datos ricos en las gráficas
            $count = rand(2, 4);

            for ($i = 0; $i < $count; $i++) {
                $randomState = rand(1, 100);

                if ($randomState <= 60) {
                    $factory = MedicalEvaluation::factory()->confirmado();
                } elseif ($randomState <= 85) {
                    $factory = MedicalEvaluation::factory()->cancelado();
                } else {
                    $factory = MedicalEvaluation::factory()->enEspera();
                }

                $medical = $factory->create([
                    'user_id'    => $patient->user_id,
                    'patient_id' => $patient->id,
                ]);

                $targetMonth = $this->faker->randomElement($availableMonths);
                $daysInMonth = Carbon::create($currentYear, $targetMonth, 1)->daysInMonth;

                // Si es el mes actual no pasar del día de hoy
                $maxDay = ($targetMonth === $currentMonth) ? $now->day : $daysInMonth;

                $procedureDate = Carbon::create(
                    $currentYear,
                    $targetMonth,
                    rand(1, max(1, $maxDay))
                )->toDateString();

                $procedures = Procedure::factory()
                    ->count(rand(1, 3))
                    ->create([
                        'medical_evaluation_id' => $medical->id,
                        'brand_slug'            => config('app.brand_slug'),
                        'procedure_date'        => $procedureDate,
                    ]);

                foreach ($procedures as $procedure) {
                    $items = ProcedureItem::factory()
                        ->count(rand(1, 3))
                        ->create(['procedure_id' => $procedure->id]);

                    $procedure->update([
                        'total_amount' => $items->sum('price'),
                    ]);
                }
            }
        }
    }
}