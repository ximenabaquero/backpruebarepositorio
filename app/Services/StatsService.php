<?php

namespace App\Services;

use App\Models\MedicalEvaluation;
use App\Models\Procedure;
use App\Models\ProcedureItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatsService
{
    // ─────────────────────────────────────────────
    // CORE: Método central de métricas por rango
    // ─────────────────────────────────────────────

    /**
     * Calcula las 4 métricas clave para un rango de fechas dado.
     * Punto único de verdad — elimina la duplicación en summary(),
     * annualComparison() y monthComparison().
     *
     * @param  Carbon  $start
     * @param  Carbon  $end
     * @param  string|null  $referrerName  Si se pasa, filtra por remitente
     * @return array{income: float, patients: int, sessions: int, procedures: int}
     */
    public function metricsForRange(Carbon $start, Carbon $end, ?string $referrerName = null): array
    {
        $income = Procedure::conEvaluacionConfirmada($referrerName)
            ->whereBetween('procedure_date', [$start, $end])
            ->sum('total_amount');

        $patients = MedicalEvaluation::where('status', 'CONFIRMADO')
            ->when($referrerName, fn($q) => $q->where('referrer_name', $referrerName))
            ->whereHas('procedures', fn($q) => $q->whereBetween('procedure_date', [$start, $end]))
            ->distinct('patient_id')
            ->count('patient_id');

        $sessions = Procedure::conEvaluacionConfirmada($referrerName)
            ->whereBetween('procedure_date', [$start, $end])
            ->count();

        $procedures = ProcedureItem::whereHas('procedure', function ($q) use ($start, $end, $referrerName) {
            $q->whereBetween('procedure_date', [$start, $end])
              ->whereHas('medicalEvaluation', function ($q) use ($referrerName) {
                  $q->where('status', 'CONFIRMADO');
                  if ($referrerName) {
                      $q->where('referrer_name', $referrerName);
                  }
              });
        })->count();

        return [
            'income'     => (float) $income,
            'patients'   => (int) $patients,
            'sessions'   => (int) $sessions,
            'procedures' => (int) $procedures,
        ];
    }

    /**
     * Calcula la variación porcentual entre dos valores.
     * Devuelve null si no hay base de comparación.
     */
    public function variation(float|int $current, float|int $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    // ─────────────────────────────────────────────
    // SUMMARY
    // ─────────────────────────────────────────────

    public function getSummary(): array
    {
        $now            = Carbon::now();
        $startThisMonth = $now->copy()->startOfMonth();
        $endThisMonth   = $now->copy()->endOfMonth();
        $startLastMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $endLastMonth   = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $thisMonth = $this->metricsForRange($startThisMonth, $endThisMonth);
        $lastMonth = $this->metricsForRange($startLastMonth, $endLastMonth);

        $totalPatients   = MedicalEvaluation::where('status', 'CONFIRMADO')->distinct('patient_id')->count('patient_id');
        $totalSessions   = Procedure::conEvaluacionConfirmada()->count();
        $totalProcedures = ProcedureItem::whereHas('procedure.medicalEvaluation', fn($q) => $q->where('status', 'CONFIRMADO'))->count();
        $totalIncome     = Procedure::conEvaluacionConfirmada()->sum('total_amount');

        return [
            'total_patients'   => $totalPatients,
            'total_sessions'   => $totalSessions,
            'total_procedures' => $totalProcedures,
            'total_income'     => (float) $totalIncome,

            'this_month_income'     => $thisMonth['income'],
            'last_month_income'     => $lastMonth['income'],
            'income_variation'      => $this->variation($thisMonth['income'], $lastMonth['income']),

            'this_month_patients'   => $thisMonth['patients'],
            'last_month_patients'   => $lastMonth['patients'],
            'patients_variation'    => $this->variation($thisMonth['patients'], $lastMonth['patients']),

            'this_month_sessions'   => $thisMonth['sessions'],
            'last_month_sessions'   => $lastMonth['sessions'],
            'sessions_variation'    => $this->variation($thisMonth['sessions'], $lastMonth['sessions']),

            'this_month_procedures' => $thisMonth['procedures'],
            'last_month_procedures' => $lastMonth['procedures'],
            'procedures_variation'  => $this->variation($thisMonth['procedures'], $lastMonth['procedures']),
        ];
    }

    // ─────────────────────────────────────────────
    // REFERRER STATS
    // ─────────────────────────────────────────────

    public function getAllReferrerStats(): \Illuminate\Support\Collection
    {
        $now        = Carbon::now();
        $startMonth = $now->copy()->startOfMonth()->toDateTimeString();
        $endMonth   = $now->copy()->endOfMonth()->toDateTimeString();
        $startYear  = $now->copy()->startOfYear()->toDateTimeString();
        $endYear    = $now->copy()->endOfYear()->toDateTimeString();

        return $this->buildReferrerQuery($startMonth, $endMonth, $startYear, $endYear)
            ->whereNotNull('medical_evaluations.referrer_name')
            ->groupBy('medical_evaluations.referrer_name')
            ->orderByDesc('confirmed_income_year')
            ->get();
    }

    public function getReferrerStatsByName(string $referrerName): object
    {
        $now        = Carbon::now();
        $startMonth = $now->copy()->startOfMonth()->toDateTimeString();
        $endMonth   = $now->copy()->endOfMonth()->toDateTimeString();
        $startYear  = $now->copy()->startOfYear()->toDateTimeString();
        $endYear    = $now->copy()->endOfYear()->toDateTimeString();

        return $this->buildReferrerQuery($startMonth, $endMonth, $startYear, $endYear)
            ->where('medical_evaluations.referrer_name', $referrerName)
            ->groupBy('medical_evaluations.referrer_name')
            ->firstOrFail();
    }

    private function buildReferrerQuery(
        string $startMonth,
        string $endMonth,
        string $startYear,
        string $endYear
    ): \Illuminate\Database\Query\Builder {
        $procedureStats = DB::table('procedures')
            ->select(
                'medical_evaluation_id',
                DB::raw("MAX(CASE
                    WHEN procedure_date BETWEEN ? AND ?
                    THEN 1 ELSE 0 END) as has_procedure_month"),
                DB::raw("MAX(CASE
                    WHEN procedure_date BETWEEN ? AND ?
                    THEN 1 ELSE 0 END) as has_procedure_year"),
                DB::raw("SUM(CASE
                    WHEN procedure_date BETWEEN ? AND ?
                    THEN total_amount ELSE 0 END) as confirmed_income_month"),
                DB::raw("SUM(CASE
                    WHEN procedure_date BETWEEN ? AND ?
                    THEN total_amount ELSE 0 END) as confirmed_income_year")
            )
            ->addBinding([
                $startMonth, $endMonth,
                $startYear,  $endYear,
                $startMonth, $endMonth,
                $startYear,  $endYear,
            ], 'select')
            ->groupBy('medical_evaluation_id');

        return DB::table('medical_evaluations')
            ->leftJoinSub($procedureStats, 'procedure_stats', function ($join) {
                $join->on('medical_evaluations.id', '=', 'procedure_stats.medical_evaluation_id');
            })
            ->select(
                'medical_evaluations.referrer_name',
                DB::raw("COUNT(DISTINCT CASE
                    WHEN medical_evaluations.status = 'CONFIRMADO'
                    AND COALESCE(procedure_stats.has_procedure_month, 0) = 1
                    THEN medical_evaluations.patient_id END) as total_patients_month"),
                DB::raw("SUM(CASE
                    WHEN medical_evaluations.status = 'CONFIRMADO'
                    AND COALESCE(procedure_stats.has_procedure_month, 0) = 1
                    THEN 1 ELSE 0 END) as total_confirmed_month"),
                DB::raw("SUM(CASE
                    WHEN medical_evaluations.status = 'CANCELADO'
                    AND COALESCE(procedure_stats.has_procedure_month, 0) = 1
                    THEN 1 ELSE 0 END) as total_canceled_month"),
                DB::raw("SUM(CASE
                    WHEN medical_evaluations.status = 'CONFIRMADO'
                    THEN COALESCE(procedure_stats.confirmed_income_month, 0) ELSE 0 END) as confirmed_income_month"),
                DB::raw("SUM(CASE
                    WHEN medical_evaluations.status = 'CONFIRMADO'
                    THEN COALESCE(procedure_stats.confirmed_income_year, 0) ELSE 0 END) as confirmed_income_year")
            );
    }

    // ─────────────────────────────────────────────
    // TOP PROCEDURES
    // ─────────────────────────────────────────────

    public function getTopByDemand(): \Illuminate\Support\Collection
    {
        $now = Carbon::now();

        return ProcedureItem::whereHas('procedure', fn($q) =>
            $q->whereMonth('procedure_date', $now->month)
              ->whereYear('procedure_date', $now->year)
              ->whereHas('medicalEvaluation', fn($q) => $q->where('status', 'CONFIRMADO'))
        )
        ->select('item_name', DB::raw('COUNT(*) as total_count'))
        ->groupBy('item_name')
        ->orderByDesc('total_count')
        ->limit(5)
        ->get();
    }

    public function getTopByIncome(): \Illuminate\Support\Collection
    {
        $now = Carbon::now();

        return ProcedureItem::whereHas('procedure', fn($q) =>
            $q->whereMonth('procedure_date', $now->month)
              ->whereYear('procedure_date', $now->year)
              ->whereHas('medicalEvaluation', fn($q) => $q->where('status', 'CONFIRMADO'))
        )
        ->select('item_name', DB::raw('SUM(price) as total_revenue'))
        ->groupBy('item_name')
        ->orderByDesc('total_revenue')
        ->limit(5)
        ->get();
    }

    // ─────────────────────────────────────────────
    // CONVERSION RATE
    // ─────────────────────────────────────────────

    public function getConversionRate(): array
    {
        $now = Carbon::now();

        $counts = MedicalEvaluation::whereHas('procedures', fn($q) =>
            $q->whereMonth('procedure_date', $now->month)
              ->whereYear('procedure_date', $now->year)
        )
        ->selectRaw('status, COUNT(*) as total')
        ->groupBy('status')
        ->pluck('total', 'status');

        $confirmed = (int) ($counts['CONFIRMADO'] ?? 0);
        $canceled  = (int) ($counts['CANCELADO']  ?? 0);
        $pending   = (int) ($counts['EN_ESPERA']   ?? 0);
        $total     = $confirmed + $canceled + $pending;
        $base      = $confirmed + $canceled;

        return [
            'total'     => $total,
            'confirmed' => $confirmed,
            'canceled'  => $canceled,
            'pending'   => $pending,
            'rate'      => $base > 0 ? round(($confirmed / $base) * 100, 2) : 0,
        ];
    }

    // ─────────────────────────────────────────────
    // ANNUAL COMPARISON — con caché
    // ─────────────────────────────────────────────

    public function getAnnualComparison(?string $referrerName = null): array
    {
        $now  = Carbon::now();
        $year = $now->year;

        $months = collect(range(1, 12))->map(function (int $month) use ($year, $now, $referrerName) {
            $start = Carbon::create($year, $month)->startOfMonth();
            $end   = Carbon::create($year, $month)->endOfMonth();

            $isCurrentMonth = $month === $now->month;
            $cacheKey = "annual_stats:{$year}:{$month}" . ($referrerName ? ":{$referrerName}" : '');

            $metrics = $isCurrentMonth
                ? $this->metricsForRange($start, $end, $referrerName)
                : Cache::remember($cacheKey, now()->addHours(6), fn() =>
                    $this->metricsForRange($start, $end, $referrerName)
                );

            return [
                'month'      => $month,
                'month_name' => $start->locale('es')->monthName,
                ...$metrics,
            ];
        });

        return [
            'year'   => $year,
            'months' => $months,
        ];
    }

    // ─────────────────────────────────────────────
    // MONTH COMPARISON — Fix N+1 + Fix only_full_group_by
    // ─────────────────────────────────────────────

    /**
     * Compara el mes actual vs el anterior, día a día.
     *
     * ANTES: ~480 queries (30 días × 8 queries/día).
     * AHORA: 8 queries totales — se agrupa todo en DB y se mapea en PHP.
     */
    public function getMonthComparison(?string $referrerName = null): array
    {
        $now  = Carbon::now();
        $days = $now->daysInMonth;

        $currentMonthStart  = $now->copy()->startOfMonth();
        $currentMonthEnd    = $now->copy()->endOfMonth();
        $previousMonthStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd   = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $currentData  = $this->aggregateMetricsByDay($currentMonthStart, $currentMonthEnd, $referrerName);
        $previousData = $this->aggregateMetricsByDay($previousMonthStart, $previousMonthEnd, $referrerName);

        $daysData = collect(range(1, $days))->map(fn(int $day) => [
            'day'      => $day,
            'current'  => $this->extractDayMetrics($currentData, $day),
            'previous' => $this->extractDayMetrics($previousData, $day),
        ]);

        return [
            'current_month'  => $now->locale('es')->monthName,
            'previous_month' => $now->copy()->subMonthNoOverflow()->locale('es')->monthName,
            'days'           => $daysData,
        ];
    }

    /**
     * Agrega income, patients, sessions y procedures agrupados por día.
     * Son 4 queries por período — no una por día.
     *
     * FIX 1 (MySQL only_full_group_by): Usa ->select() en vez de ->selectRaw()
     * para REEMPLAZAR el select list completo. Con ->selectRaw() Laravel agrega
     * al select existente dejando `procedures.*`, que MySQL en modo estricto rechaza
     * al agrupar solo por DAY().
     *
     * FIX 2 (SQLite): DAY() es sintaxis MySQL exclusiva. Se usa strftime('%d', ...)
     * en SQLite y DAY() en MySQL, detectando el driver en runtime.
     * Esto permite que los tests corran en SQLite sin cambios.
     *
     * @return array<string, \Illuminate\Support\Collection>
     */
    private function aggregateMetricsByDay(Carbon $start, Carbon $end, ?string $referrerName): array
    {
        $driver = DB::getDriverName();

        // strftime devuelve string con cero a la izquierda en SQLite ('01', '02'...)
        // CAST a INTEGER para que pluck() devuelva claves numéricas en ambos motores
        $dayExpr = $driver === 'sqlite'
            ? "CAST(strftime('%d', procedure_date) AS INTEGER)"
            : 'DAY(procedure_date)';

        // select() reemplaza el select list completo — no acumula procedures.*
        $income = Procedure::conEvaluacionConfirmada($referrerName)
            ->whereBetween('procedure_date', [$start, $end])
            ->select(DB::raw("{$dayExpr} as day, SUM(total_amount) as value"))
            ->groupBy(DB::raw($dayExpr))
            ->pluck('value', 'day');

        $sessions = Procedure::conEvaluacionConfirmada($referrerName)
            ->whereBetween('procedure_date', [$start, $end])
            ->select(DB::raw("{$dayExpr} as day, COUNT(*) as value"))
            ->groupBy(DB::raw($dayExpr))
            ->pluck('value', 'day');

        $patients = MedicalEvaluation::where('status', 'CONFIRMADO')
            ->when($referrerName, fn($q) => $q->where('referrer_name', $referrerName))
            ->join('procedures', 'medical_evaluations.id', '=', 'procedures.medical_evaluation_id')
            ->whereBetween('procedures.procedure_date', [$start, $end])
            ->select(DB::raw("{$dayExpr} as day, COUNT(DISTINCT medical_evaluations.patient_id) as value"))
            ->groupBy(DB::raw($dayExpr))
            ->pluck('value', 'day');

        $procedures = ProcedureItem::whereHas('procedure', function ($q) use ($start, $end, $referrerName) {
            $q->whereBetween('procedure_date', [$start, $end])
              ->whereHas('medicalEvaluation', function ($q) use ($referrerName) {
                  $q->where('status', 'CONFIRMADO');
                  if ($referrerName) {
                      $q->where('referrer_name', $referrerName);
                  }
              });
        })
        ->join('procedures', 'procedure_items.procedure_id', '=', 'procedures.id')
        ->select(DB::raw("{$dayExpr} as day, COUNT(*) as value"))
        ->groupBy(DB::raw($dayExpr))
        ->pluck('value', 'day');

        return compact('income', 'sessions', 'patients', 'procedures');
    }

    /**
     * Extrae las métricas de un día concreto del mapa agregado.
     *
     * @param  array<string, \Illuminate\Support\Collection>  $data
     */
    private function extractDayMetrics(array $data, int $day): array
    {
        return [
            'income'     => (float) ($data['income'][$day]     ?? 0),
            'patients'   => (int)   ($data['patients'][$day]   ?? 0),
            'sessions'   => (int)   ($data['sessions'][$day]   ?? 0),
            'procedures' => (int)   ($data['procedures'][$day] ?? 0),
        ];
    }

    // ─────────────────────────────────────────────
    // REVENUE TREND — window functions MySQL 8+
    // ─────────────────────────────────────────────

    /**
     * Ingresos mensuales del año con SMA-3 y delta mes a mes.
     * Usa window functions de MySQL 8+ (AVG OVER, LAG OVER).
     * Solo disponible cuando el driver es MySQL/MariaDB.
     */
    public function getRevenueTrend(): array
    {
        $year = Carbon::now()->year;

        $rows = DB::select("
            SELECT
                date,
                revenue,
                AVG(revenue) OVER (
                    ORDER BY date
                    ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
                ) AS sma_3,
                revenue - LAG(revenue) OVER (ORDER BY date) AS cambio_mes_a_mes
            FROM (
                SELECT
                    DATE_FORMAT(p.procedure_date, '%Y-%m-01') AS date,
                    COALESCE(SUM(p.total_amount), 0)          AS revenue
                FROM procedures p
                JOIN medical_evaluations me
                    ON p.medical_evaluation_id = me.id
                WHERE me.status = 'CONFIRMADO'
                  AND YEAR(p.procedure_date) = :year
                GROUP BY DATE_FORMAT(p.procedure_date, '%Y-%m-01')
            ) monthly
            ORDER BY date
        ", ['year' => $year]);

        return array_map(fn($row) => [
            'date'              => $row->date,
            'revenue'           => (float) $row->revenue,
            'sma_3'             => $row->sma_3 !== null ? round((float) $row->sma_3, 2) : null,
            'cambio_mes_a_mes'  => $row->cambio_mes_a_mes !== null ? (float) $row->cambio_mes_a_mes : null,
        ], $rows);
    }

    // ─────────────────────────────────────────────
    // REFERRER SELF-SUMMARY
    // ─────────────────────────────────────────────

    public function getReferrerSummary(string $referrerName): array
    {
        $now            = Carbon::now();
        $startThisMonth = $now->copy()->startOfMonth();
        $endThisMonth   = $now->copy()->endOfMonth();
        $startLastMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $endLastMonth   = $now->copy()->subMonthNoOverflow()->endOfMonth();
        $startYear      = $now->copy()->startOfYear();
        $endYear        = $now->copy()->endOfYear();

        $thisMonth  = $this->metricsForRange($startThisMonth, $endThisMonth, $referrerName);
        $lastMonth  = $this->metricsForRange($startLastMonth, $endLastMonth, $referrerName);
        $yearToDate = $this->metricsForRange($startYear, $endYear, $referrerName);

        return [
            'this_month_income'    => $thisMonth['income'],
            'this_month_patients'  => $thisMonth['patients'],
            'this_month_sessions'  => $thisMonth['sessions'],
            'income_variation'     => $this->variation($thisMonth['income'],   $lastMonth['income']),
            'patients_variation'   => $this->variation($thisMonth['patients'], $lastMonth['patients']),
            'sessions_variation'   => $this->variation($thisMonth['sessions'], $lastMonth['sessions']),
            'year_income'          => $yearToDate['income'],
            'year_patients'        => $yearToDate['patients'],
            'year_sessions'        => $yearToDate['sessions'],
        ];
    }
}