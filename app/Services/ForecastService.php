<?php

namespace App\Services;

use App\Models\Procedure;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ForecastService
 *
 * Regresión exponencial pura en PHP: y = a * e^(bx)
 * Técnica: linearizar con ln(y), aplicar mínimos cuadrados, recuperar a y b.
 * Equivalente a MathPHP\Statistics\Regression\Exponential sin dependencia externa.
 */
class ForecastService
{
    /**
     * Ingresos proyectados para los próximos N meses, con histórico + SMA-3.
     *
     * @param  int  $periodsAhead  Meses a proyectar (default: 3)
     * @return array{
     *   model: string,
     *   r_squared: float|null,
     *   capacity_ceiling: float,
     *   historical: array,
     *   predictions: array
     * }
     */
    public function forecastRevenue(int $periodsAhead = 3): array
    {
        $historical = $this->getMonthlyRevenue();

        if (count($historical) < 2) {
            return [
                'model'            => 'exponential',
                'r_squared'        => null,
                'capacity_ceiling' => $this->capacityCeiling(),
                'historical'       => $historical,
                'predictions'      => [],
                'notice'           => 'Se necesitan al menos 2 meses de datos para proyectar.',
            ];
        }

        ['a' => $a, 'b' => $b, 'r_squared' => $r2] = $this->exponentialRegression($historical);
        $ceiling  = $this->capacityCeiling();
        $lastPeriod = count($historical);

        $predictions = [];
        for ($i = 1; $i <= $periodsAhead; $i++) {
            $period = $lastPeriod + $i;
            $raw    = $a * exp($b * $period);
            $date   = Carbon::now()->addMonths($i)->startOfMonth();

            $predictions[] = [
                'period'     => $period,
                'month'      => $date->month,
                'year'       => $date->year,
                'month_name' => $date->locale('es')->monthName,
                'predicted'  => round(min($raw, $ceiling), 2),
                'capped'     => $raw > $ceiling,
            ];
        }

        return [
            'model'            => 'exponential',
            'r_squared'        => $r2 !== null ? round($r2, 4) : null,
            'capacity_ceiling' => $ceiling,
            'historical'       => $this->withMovingAverage($historical),
            'predictions'      => $predictions,
        ];
    }

    // ─────────────────────────────────────────────
    // Datos históricos — agrupados por mes
    // ─────────────────────────────────────────────

    /**
     * Ingresos reales mensuales desde el primer procedimiento confirmado.
     * Devuelve [[period, year, month, month_name, revenue], ...].
     */
    private function getMonthlyRevenue(): array
    {
        $driver = DB::getDriverName();

        $rows = DB::table('procedures')
            ->join('medical_evaluations', 'procedures.medical_evaluation_id', '=', 'medical_evaluations.id')
            ->where('medical_evaluations.status', 'CONFIRMADO')
            ->select(
                DB::raw($driver === 'sqlite' ? "strftime('%Y', procedure_date) as year" : 'YEAR(procedure_date) as year'),
                DB::raw($driver === 'sqlite' ? "strftime('%m', procedure_date) as month" : 'MONTH(procedure_date) as month'),
                DB::raw('SUM(procedures.total_amount) as revenue')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $result = [];
        foreach ($rows as $index => $row) {
            $date     = Carbon::create((int) $row->year, (int) $row->month);
            $result[] = [
                'period'     => $index + 1,
                'year'       => (int) $row->year,
                'month'      => (int) $row->month,
                'month_name' => $date->locale('es')->monthName,
                'revenue'    => (float) $row->revenue,
            ];
        }

        return $result;
    }

    /**
     * Agrega SMA-3 (promedio móvil de 3 períodos) a cada punto histórico.
     */
    private function withMovingAverage(array $data): array
    {
        return array_map(function (array $point, int $i) use ($data): array {
            $window = array_slice($data, max(0, $i - 2), min(3, $i + 1));
            $sma    = array_sum(array_column($window, 'revenue')) / count($window);

            return array_merge($point, ['sma_3' => round($sma, 2)]);
        }, $data, array_keys($data));
    }

    // ─────────────────────────────────────────────
    // Regresión exponencial — pure PHP
    // ─────────────────────────────────────────────

    /**
     * Ajusta y = a * e^(bx) usando mínimos cuadrados sobre ln(y).
     * Descarta puntos con revenue <= 0 (no linealizables).
     *
     * @param  array  $data  Output de getMonthlyRevenue()
     * @return array{a: float, b: float, r_squared: float|null}
     */
    private function exponentialRegression(array $data): array
    {
        $points = array_filter($data, fn($p) => $p['revenue'] > 0);
        $points = array_values($points);
        $n      = count($points);

        if ($n < 2) {
            return ['a' => 0, 'b' => 0, 'r_squared' => null];
        }

        // Linearizar: Y = ln(revenue), X = period
        $xs  = array_column($points, 'period');
        $lnYs = array_map(fn($p) => log($p['revenue']), $points);

        // Mínimos cuadrados sobre (X, ln(Y))
        $sumX   = array_sum($xs);
        $sumY   = array_sum($lnYs);
        $sumXY  = array_sum(array_map(fn($x, $y) => $x * $y, $xs, $lnYs));
        $sumX2  = array_sum(array_map(fn($x) => $x ** 2, $xs));

        $denom = $n * $sumX2 - $sumX ** 2;
        if ($denom == 0) {
            return ['a' => 0, 'b' => 0, 'r_squared' => null];
        }

        $b         = ($n * $sumXY - $sumX * $sumY) / $denom;
        $lnA       = ($sumY - $b * $sumX) / $n;
        $a         = exp($lnA);
        $r_squared = $this->rSquared($xs, $lnYs, $b, $lnA);

        return compact('a', 'b', 'r_squared');
    }

    /**
     * R² sobre los valores linearizados (ln space).
     */
    private function rSquared(array $xs, array $ys, float $b, float $lnA): float
    {
        $n      = count($ys);
        $meanY  = array_sum($ys) / $n;
        $ssTot  = array_sum(array_map(fn($y) => ($y - $meanY) ** 2, $ys));
        $ssRes  = array_sum(array_map(
            fn($x, $y) => ($y - ($lnA + $b * $x)) ** 2,
            $xs, $ys
        ));

        return $ssTot > 0 ? 1 - $ssRes / $ssTot : 1.0;
    }

    // ─────────────────────────────────────────────
    // Techo de capacidad
    // ─────────────────────────────────────────────

    /**
     * Ingreso mensual máximo teórico por capacidad instalada.
     * 3 salas × 8 h/día × 22 días/mes × $150.000 COP/sesión = $79.200.000
     *
     * Ajustar según capacidad real de la clínica.
     */
    private function capacityCeiling(): float
    {
        return 80_000_000.0;
    }
}
