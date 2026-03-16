<?php

namespace Tests\Unit;

use App\Services\MedicalEvaluationService;
use App\Services\StatsService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests unitarios puros — sin DB, sin HTTP.
 * Solo verifica la lógica de dominio de los services.
 */
class MedicalEvaluationServiceTest extends TestCase
{
    private MedicalEvaluationService $evalService;
    private StatsService $statsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evalService  = new MedicalEvaluationService();
        $this->statsService = new StatsService();
    }

    // ─────────────────────────────────────────────
    // calculateBmi
    // ─────────────────────────────────────────────

    public function test_calcula_bmi_correctamente(): void
    {
        // 65 / (1.65^2) = 23.875... → round a 2 decimales = 23.88
        $this->assertEquals(23.88, $this->evalService->calculateBmi(65, 1.65));
    }

    public function test_bmi_se_redondea_a_2_decimales(): void
    {
        $bmi      = $this->evalService->calculateBmi(70, 1.75);
        $decimals = strlen(substr(strrchr((string) $bmi, '.'), 1));
        $this->assertLessThanOrEqual(2, $decimals);
    }

    // ─────────────────────────────────────────────
    // getBmiStatus
    // ─────────────────────────────────────────────

    #[DataProvider('bmiStatusProvider')]
    public function test_clasifica_bmi_correctamente(float $bmi, string $expected): void
    {
        $this->assertEquals($expected, $this->evalService->getBmiStatus($bmi));
    }

    public static function bmiStatusProvider(): array
    {
        return [
            'delgadez severa'    => [15.0, 'Delgadez severa (< 16.0)'],
            'delgadez moderada'  => [16.5, 'Delgadez moderada (16.0–16.9)'],
            'delgadez leve'      => [17.5, 'Delgadez leve (17.0–18.4)'],
            'peso normal'        => [22.0, 'Peso normal (18.5–24.9)'],
            'sobrepeso'          => [27.0, 'Sobrepeso (25.0–29.9)'],
            'obesidad grado I'   => [32.0, 'Obesidad grado I (30.0–34.9)'],
            'obesidad grado II'  => [37.0, 'Obesidad grado II (35.0–39.9)'],
            'obesidad grado III' => [42.0, 'Obesidad grado III (≥ 40)'],
            // Bordes exactos
            'borde 16.0'         => [16.0, 'Delgadez moderada (16.0–16.9)'],
            'borde 18.5'         => [18.5, 'Peso normal (18.5–24.9)'],
            'borde 25.0'         => [25.0, 'Sobrepeso (25.0–29.9)'],
            'borde 40.0'         => [40.0, 'Obesidad grado III (≥ 40)'],
        ];
    }

    // ─────────────────────────────────────────────
    // variation — vive en StatsService
    // ─────────────────────────────────────────────

    public function test_variation_calcula_porcentaje_correcto(): void
    {
        // (120 - 100) / 100 * 100 = 20%
        $this->assertEquals(20.0, $this->statsService->variation(120, 100));
    }

    public function test_variation_negativa(): void
    {
        // (80 - 100) / 100 * 100 = -20%
        $this->assertEquals(-20.0, $this->statsService->variation(80, 100));
    }

    public function test_variation_devuelve_null_si_base_es_cero(): void
    {
        $this->assertNull($this->statsService->variation(100, 0));
    }

    public function test_variation_se_redondea_a_2_decimales(): void
    {
        $result = $this->statsService->variation(133, 100);
        $this->assertEquals(33.0, $result);
    }
}