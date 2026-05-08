<?php

namespace Tests\Feature;

use App\Models\MedicalEvaluation;
use App\Models\Patient;
use App\Models\Procedure;
use Carbon\Carbon;
use Tests\TestCase;

class ReferrerStatsTest extends TestCase
{
    public function test_referrer_stats_aggregates_month_and_year_totals_correctly(): void
    {
        Carbon::setTestNow('2026-04-15 10:00:00');

        $admin = $this->actingAsAdmin();
        $patient = Patient::factory()->create(['user_id' => $admin->id]);

        $confirmedThisMonth = MedicalEvaluation::factory()->confirmado()->create([
            'user_id' => $admin->id,
            'patient_id' => $patient->id,
            'referrer_name' => 'admin',
        ]);

        Procedure::create([
            'medical_evaluation_id' => $confirmedThisMonth->id,
            'procedure_date' => '2026-04-10',
            'brand_slug' => config('app.brand_slug'),
            'notes' => 'Procedimiento 1',
            'total_amount' => 150000,
        ]);

        Procedure::create([
            'medical_evaluation_id' => $confirmedThisMonth->id,
            'procedure_date' => '2026-04-12',
            'brand_slug' => config('app.brand_slug'),
            'notes' => 'Procedimiento 2',
            'total_amount' => 250000,
        ]);

        $confirmedThisYear = MedicalEvaluation::factory()->confirmado()->create([
            'user_id' => $admin->id,
            'patient_id' => Patient::factory()->create(['user_id' => $admin->id])->id,
            'referrer_name' => 'admin',
        ]);

        Procedure::create([
            'medical_evaluation_id' => $confirmedThisYear->id,
            'procedure_date' => '2026-02-20',
            'brand_slug' => config('app.brand_slug'),
            'notes' => 'Procedimiento 3',
            'total_amount' => 300000,
        ]);

        $response = $this->getJson('/api/v1/stats/referrer-stats');

        $response->assertOk();

        $referrer = collect($response->json('data'))->firstWhere('referrer_name', 'admin');

        $this->assertNotNull($referrer);
        $this->assertSame(1, (int) $referrer['total_patients_month']);
        $this->assertSame(1, (int) $referrer['total_confirmed_month']);
        $this->assertSame(400000, (int) round((float) $referrer['confirmed_income_month']));
        $this->assertSame(700000, (int) round((float) $referrer['confirmed_income_year']));
    }
}
