<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\ProcedureItem;
use App\Models\MedicalEvaluation;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class StatsController extends Controller
{
    /**
     * KPIs generales
     */
    public function summary()
    {
        $now = Carbon::now();
        $lastMonth = $now->copy()->subMonth();

        $startOfThisMonth = $now->copy()->startOfMonth();
        $endOfThisMonth = $now->copy()->endOfMonth();

        $startOfLastMonth = $lastMonth->copy()->startOfMonth();
        $endOfLastMonth = $lastMonth->copy()->endOfMonth();

        // Mes actual
        $thisMonthIncome = Procedure::conEvaluacionConfirmada()
            ->whereBetween('procedure_date', [$startOfThisMonth, $endOfThisMonth])
            ->sum('total_amount');

        $thisMonthPatients = MedicalEvaluation::where('status', 'CONFIRMADO')
            ->whereHas('procedures', function ($q) use ($startOfThisMonth, $endOfThisMonth) {
                $q->whereBetween('procedure_date', [$startOfThisMonth, $endOfThisMonth]);
            })
            ->distinct('patient_id')
            ->count('patient_id');

        $thisMonthSessions = Procedure::conEvaluacionConfirmada()
            ->whereBetween('procedure_date', [$startOfThisMonth, $endOfThisMonth])
            ->count();

        $thisMonthProcedures = ProcedureItem::whereHas('procedure', function ($q) use ($startOfThisMonth, $endOfThisMonth) {
                $q->whereBetween('procedure_date', [$startOfThisMonth, $endOfThisMonth])
                ->whereHas('medicalEvaluation', fn($q) => $q->where('status', 'CONFIRMADO'));
            })
            ->count();

        // Mes anterior
        $lastMonthIncome = Procedure::conEvaluacionConfirmada()
            ->whereBetween('procedure_date', [$startOfLastMonth, $endOfLastMonth])
            ->sum('total_amount');

        $lastMonthPatients = MedicalEvaluation::where('status', 'CONFIRMADO')
            ->whereHas('procedures', function ($q) use ($startOfLastMonth, $endOfLastMonth) {
                $q->whereBetween('procedure_date', [$startOfLastMonth, $endOfLastMonth]);
            })
            ->distinct('patient_id')
            ->count('patient_id');

        $lastMonthSessions = Procedure::conEvaluacionConfirmada()
            ->whereBetween('procedure_date', [$startOfLastMonth, $endOfLastMonth])
            ->count();

        $lastMonthProcedures = ProcedureItem::whereHas('procedure', function ($q) use ($startOfLastMonth, $endOfLastMonth) {
                $q->whereBetween('procedure_date', [$startOfLastMonth, $endOfLastMonth])
                ->whereHas('medicalEvaluation', fn($q) => $q->where('status', 'CONFIRMADO'));
            })
            ->count();

        // Variaciones
        $incomeVariation = $lastMonthIncome > 0
            ? round((($thisMonthIncome - $lastMonthIncome) / $lastMonthIncome) * 100, 2)
            : null;

        $patientsVariation = $lastMonthPatients > 0
            ? round((($thisMonthPatients - $lastMonthPatients) / $lastMonthPatients) * 100, 2)
            : null;

        $sessionsVariation = $lastMonthSessions > 0
            ? round((($thisMonthSessions - $lastMonthSessions) / $lastMonthSessions) * 100, 2)
            : null;

        $proceduresVariation = $lastMonthProcedures > 0
            ? round((($thisMonthProcedures - $lastMonthProcedures) / $lastMonthProcedures) * 100, 2)
            : null;

        return response()->json([
            'total_patients' => MedicalEvaluation::where('status', 'CONFIRMADO')
                    ->distinct('patient_id')
                    ->count('patient_id'),

            'total_sessions' => Procedure::conEvaluacionConfirmada()->count(),

            'total_procedures' => ProcedureItem::whereHas('procedure.medicalEvaluation', 
                fn($q) => $q->where('status', 'CONFIRMADO')
            )->count(),

            'total_income' => Procedure::conEvaluacionConfirmada()->sum('total_amount'),

            'this_month_income' => $thisMonthIncome,
            'last_month_income' => $lastMonthIncome,
            'income_variation' => $incomeVariation,

            'this_month_patients' => $thisMonthPatients,
            'last_month_patients' => $lastMonthPatients,
            'patients_variation' => $patientsVariation,

            'this_month_sessions' => $thisMonthSessions,
            'last_month_sessions' => $lastMonthSessions,
            'sessions_variation' => $sessionsVariation,

            'this_month_procedures' => $thisMonthProcedures,
            'last_month_procedures' => $lastMonthProcedures,
            'procedures_variation' => $proceduresVariation,
        ]);
    }

    /**
     * remitente y admin, sus ingresos, pacientes,etc
     */
    public function referrerStats()
    {
        $now = Carbon::now();

        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth   = $now->copy()->endOfMonth();

        $startOfYear = $now->copy()->startOfYear();
        $endOfYear   = $now->copy()->endOfYear();

        $stats = DB::table('medical_evaluations')
        ->leftJoin('procedures', 'medical_evaluations.id', '=', 'procedures.medical_evaluation_id')
        ->select(
            'medical_evaluations.referrer_name',

            // Pacientes únicos del mes — usa procedure_date
            DB::raw("
                COUNT(DISTINCT CASE
                    WHEN medical_evaluations.status = 'CONFIRMADO'
                    AND procedures.procedure_date 
                        BETWEEN '{$startOfMonth}' AND '{$endOfMonth}'
                    THEN medical_evaluations.patient_id
                END) as total_patients_month
            "),

            // Registros confirmados del mes — usa procedure_date
            DB::raw("
                SUM(CASE
                    WHEN medical_evaluations.status = 'CONFIRMADO'
                    AND procedures.procedure_date 
                        BETWEEN '{$startOfMonth}' AND '{$endOfMonth}'
                    THEN 1 ELSE 0
                END) as total_confirmed_month
            "),

            // Registros cancelados del mes — usa procedure_date
            DB::raw("
                SUM(CASE
                    WHEN medical_evaluations.status = 'CANCELADO'
                    AND procedures.procedure_date 
                        BETWEEN '{$startOfMonth}' AND '{$endOfMonth}'
                    THEN 1 ELSE 0
                END) as total_canceled_month
            "),

            // Ingresos del mes
            DB::raw("
                SUM(CASE
                    WHEN medical_evaluations.status = 'CONFIRMADO'
                    AND procedures.procedure_date 
                        BETWEEN '{$startOfMonth}' AND '{$endOfMonth}'
                    THEN procedures.total_amount
                    ELSE 0
                END) as confirmed_income_month
            "),

            // Ingresos del año
            DB::raw("
                SUM(CASE
                    WHEN medical_evaluations.status = 'CONFIRMADO'
                    AND procedures.procedure_date 
                        BETWEEN '{$startOfYear}' AND '{$endOfYear}'
                    THEN procedures.total_amount
                    ELSE 0
                END) as confirmed_income_year
            ")
        )
        ->whereNotNull('medical_evaluations.referrer_name')
        ->groupBy('medical_evaluations.referrer_name')
        ->orderByDesc('confirmed_income_year')
        ->get();

        return response()->json($stats);
    }

    /**
     * Top 5 procedimientos por CANTIDAD (Demanda) del mes actual
     */
    public function topByDemand()
    {
        $now = Carbon::now();

        $data = ProcedureItem::whereHas('procedure', function ($q) use ($now) {
                $q->whereMonth('procedure_date', $now->month)
                ->whereYear('procedure_date', $now->year)
                ->whereHas('medicalEvaluation', fn($q) => $q->where('status', 'CONFIRMADO'));
            })
            ->select('item_name', DB::raw('COUNT(*) as total_count'))
            ->groupBy('item_name')
            ->orderByDesc('total_count')
            ->limit(5)
            ->get();

        return response()->json($data);
    }

    /**
     * Top 5 procedimientos por INGRESOS (Valor) del mes actual
     */
    public function topByIncome()
    {
        $now = Carbon::now();

        $data = ProcedureItem::whereHas('procedure', function ($q) use ($now) {
                $q->whereMonth('procedure_date', $now->month)
                ->whereYear('procedure_date', $now->year)
                ->whereHas('medicalEvaluation', fn($q) => $q->where('status', 'CONFIRMADO'));
            })
            ->select('item_name', DB::raw('SUM(price) as total_revenue'))
            ->groupBy('item_name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        return response()->json($data);
    }

    /**
     * Registros: CONFIRMADO - EN_ESPERA - CANCELADO
     */
    public function conversionRate()
    {
        $now = Carbon::now();

        $counts = MedicalEvaluation::whereHas('procedures', function ($q) use ($now) {
                $q->whereMonth('procedure_date', $now->month)
                ->whereYear('procedure_date', $now->year);
            })
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $confirmed = (int) ($counts['CONFIRMADO'] ?? 0);
        $canceled  = (int) ($counts['CANCELADO']  ?? 0);
        $pending   = (int) ($counts['EN_ESPERA']  ?? 0);
        $total     = $confirmed + $canceled + $pending;
        $rate      = ($confirmed + $canceled) > 0
            ? round(($confirmed / ($confirmed + $canceled)) * 100, 2)
            : 0;

        return response()->json([
            'total'     => $total,
            'confirmed' => $confirmed,
            'canceled'  => $canceled,
            'pending'   => $pending,
            'rate'      => $rate,
        ]);
    }

    /**
     * Comparar totales - 12 meses 
     */
    public function annualComparison()
    {
        $now = Carbon::now();
        $year = $now->year;

        $months = collect(range(1, 12))->map(function ($month) use ($year) {
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end   = Carbon::create($year, $month, 1)->endOfMonth();

            $income = Procedure::conEvaluacionConfirmada()
                ->whereBetween('procedure_date', [$start, $end])
                ->sum('total_amount');

            $patients = MedicalEvaluation::where('status', 'CONFIRMADO')
                ->whereHas('procedures', function ($q) use ($start, $end) {
                    $q->whereBetween('procedure_date', [$start, $end]);
                })
                ->distinct('patient_id')
                ->count('patient_id');

            $sessions = Procedure::conEvaluacionConfirmada()
                ->whereBetween('procedure_date', [$start, $end])
                ->count();

            $procedures = ProcedureItem::whereHas('procedure', function ($q) use ($start, $end) {
                    $q->whereBetween('procedure_date', [$start, $end])
                    ->whereHas('medicalEvaluation', fn($q) => $q->where('status', 'CONFIRMADO'));
                })
                ->count();

            return [
                'month'      => $month,
                'month_name' => $start->locale('es')->monthName,
                'income'     => (float) $income,
                'patients'   => $patients,
                'sessions'   => $sessions,
                'procedures' => $procedures,
            ];
        });

        return response()->json([
            'year'   => $year,
            'months' => $months,
        ]);
    }

    /**
     * Comparar mes actual vs mes anterior
     */
    public function monthComparison()
    {
        $now  = Carbon::now();
        $days = $now->daysInMonth;

        $days_data = collect(range(1, $days))->map(function ($day) use ($now) {
            $date     = Carbon::create($now->year, $now->month, $day)->startOfDay();
            $dateEnd  = $date->copy()->endOfDay();

            $prevDate    = $date->copy()->subMonth()->startOfDay();
            $prevDateEnd = $prevDate->copy()->endOfDay();

            $calc = function ($start, $end) {
                $income = Procedure::conEvaluacionConfirmada()
                    ->whereBetween('procedure_date', [$start, $end])
                    ->sum('total_amount');

                $patients = MedicalEvaluation::where('status', 'CONFIRMADO')
                    ->whereHas('procedures', fn($q) => $q->whereBetween('procedure_date', [$start, $end]))
                    ->distinct('patient_id')
                    ->count('patient_id');

                $sessions = Procedure::conEvaluacionConfirmada()
                    ->whereBetween('procedure_date', [$start, $end])
                    ->count();

                $procedures = ProcedureItem::whereHas('procedure', fn($q) =>
                    $q->whereBetween('procedure_date', [$start, $end])
                    ->whereHas('medicalEvaluation', fn($q) => $q->where('status', 'CONFIRMADO'))
                )->count();

                return [
                    'income'     => (float) $income,
                    'patients'   => (int) $patients,
                    'sessions'   => (int) $sessions,
                    'procedures' => (int) $procedures,
                ];
            };

            return [
                'day'      => $day,
                'current'  => $calc($date, $dateEnd),
                'previous' => $calc($prevDate, $prevDateEnd),
            ];
        });

        return response()->json([
            'current_month'  => $now->locale('es')->monthName,
            'previous_month' => $now->copy()->subMonth()->locale('es')->monthName,
            'days'           => $days_data,
        ]);
    }
    /*------------------------------------------------------------------*/

    /**
     * Ingresos por tipo de procedimiento
     */
    public function incomeByProcedureType()
    {
        $data = ProcedureItem::select(
                'item_name',
                DB::raw('SUM(price) as total_income')
            )
            ->groupBy('item_name')
            ->orderByDesc('total_income')
            ->get();

        return response()->json($data);
    }

    /**
     * Ingresos mensuales
     */
    public function incomeMonthly()
    {
        $data = Procedure::select(
                DB::raw('YEAR(procedure_date) as year'),
                DB::raw('MONTH(procedure_date) as month'),
                DB::raw('SUM(total_amount) as total_income')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return response()->json($data);
    }

    /**
     * Ingresos semanales (por día)
     */
    public function incomeWeekly()
    {
        $start = Carbon::now()->startOfWeek();
        $end   = Carbon::now()->endOfWeek();

        $data = Procedure::whereBetween('procedure_date', [$start, $end])
            ->select(
                DB::raw('DATE(procedure_date) as date'),
                DB::raw('SUM(total_amount) as total_income')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json($data);
    }

    /**
     * Pacientes nuevos por mes (últimos 12 meses)
     */
    public function patientsMonthly()
    {
        $start = Carbon::now()->subMonths(11)->startOfMonth();

        $data = MedicalEvaluation::where('status', 'CONFIRMADO')
            ->where('created_at', '>=', $start)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(DISTINCT patient_id) as new_patients')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return response()->json($data);
    }
}
