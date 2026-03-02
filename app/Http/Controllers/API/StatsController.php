<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\ProcedureItem;
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
            ->whereBetween('created_at', [$startOfThisMonth, $endOfThisMonth])
            ->distinct('patient_id')
            ->count('patient_id');

        $thisMonthSessions = Procedure::conEvaluacionConfirmada()
            ->whereBetween('procedure_date', [$startOfThisMonth, $endOfThisMonth])
            ->count();

        $thisMonthProcedures = ProcedureItem::whereHas('procedure.medicalEvaluation', function ($q) {
                $q->where('status', 'CONFIRMADO');
            })
            ->whereBetween('created_at', [$startOfThisMonth, $endOfThisMonth])
            ->count();

        // Mes anterior
        $lastMonthIncome = Procedure::conEvaluacionConfirmada()
            ->whereBetween('procedure_date', [$startOfLastMonth, $endOfLastMonth])
            ->sum('total_amount');

        $lastMonthPatients = MedicalEvaluation::where('status', 'CONFIRMADO')
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->distinct('patient_id')
            ->count('patient_id');

        $lastMonthSessions = Procedure::conEvaluacionConfirmada()
            ->whereBetween('procedure_date', [$startOfLastMonth, $endOfLastMonth])
            ->count();

        $lastMonthProcedures = ProcedureItem::whereHas('procedure.medicalEvaluation', function ($q) {
                $q->where('status', 'CONFIRMADO');
            })
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
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
            'total_patients' => Patient::count(),

            'total_sessions' => Procedure::conEvaluacionConfirmada()->count(),

            'total_procedures' => ProcedureItem::count(),

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
                // Pacientes únicos del mes
                DB::raw("
                    COUNT(DISTINCT CASE
                        WHEN medical_evaluations.status = 'CONFIRMADO'
                        AND medical_evaluations.created_at 
                            BETWEEN '{$startOfMonth}' AND '{$endOfMonth}'
                        THEN medical_evaluations.patient_id
                    END) as total_patients_month
                "),

                // Registros confirmados del mes
                DB::raw("
                    SUM(CASE
                        WHEN medical_evaluations.status = 'CONFIRMADO'
                        AND medical_evaluations.created_at 
                            BETWEEN '{$startOfMonth}' AND '{$endOfMonth}'
                        THEN 1 ELSE 0
                    END) as total_confirmed_month
                "),

                // Registros cancelados del mes
                DB::raw("
                    SUM(CASE
                        WHEN medical_evaluations.status = 'CANCELADO'
                        AND medical_evaluations.created_at 
                            BETWEEN '{$startOfMonth}' AND '{$endOfMonth}'
                        THEN 1 ELSE 0
                    END) as total_canceled_month
                "),

                // Ingresos confirmados del mes
                DB::raw("
                    SUM(CASE
                        WHEN medical_evaluations.status = 'CONFIRMADO'
                        AND procedures.procedure_date 
                            BETWEEN '{$startOfMonth}' AND '{$endOfMonth}'
                        THEN procedures.total_amount
                        ELSE 0
                    END) as confirmed_income_month
                "),
                // Ingresos confirmados del año actual
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
     * Top 5 procedimientos por CANTIDAD (Demanda) del mes actual
     */
    public function topByDemand()
    {
        $now = Carbon::now();

        $data = ProcedureItem::whereHas('procedure.medicalEvaluation', function ($query) {
                $query->confirmado();
            })
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->select(
                'item_name',
                DB::raw('COUNT(*) as total_count')
            )
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

        $data = ProcedureItem::whereHas('procedure.medicalEvaluation', function ($query) {
                $query->confirmado();
            })
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->select(
                'item_name',
                DB::raw('SUM(price) as total_revenue')
            )
            ->groupBy('item_name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        return response()->json($data);
    }

    
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
}
