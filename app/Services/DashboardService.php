<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardService
{
    public function summary(int $monthsBack = 6): array
    {
        Carbon::setLocale('es');

        $today        = Carbon::today();
        $startMonth   = $today->copy()->startOfMonth();
        $prevMonth    = $startMonth->copy()->subMonth();
        $sixMonthsAgo = $startMonth->copy()->subMonths($monthsBack - 1);

        // KPIs
        $activeClients = (int) DB::table('clientes')->where('activo', 1)->count();

        $grantedThisMonth = (int) DB::table('bolsas_puntos')
            ->whereBetween('fecha_asignacion', [$startMonth, $startMonth->copy()->endOfMonth()])
            ->sum('puntaje_asignado');

        $grantedPrevMonth = (int) DB::table('bolsas_puntos')
            ->whereBetween('fecha_asignacion', [$prevMonth, $prevMonth->copy()->endOfMonth()])
            ->sum('puntaje_asignado');

        $redeemedThisMonth = (int) DB::table('uso_puntos_cab')
            ->whereBetween('fecha', [$startMonth, $startMonth->copy()->endOfMonth()])
            ->sum('puntaje_utilizado');

        $redeemedPrevMonth = (int) DB::table('uso_puntos_cab')
            ->whereBetween('fecha', [$prevMonth, $prevMonth->copy()->endOfMonth()])
            ->sum('puntaje_utilizado');

        $expiringNext = (int) DB::table('bolsas_puntos')
            ->whereNotNull('fecha_caducidad')
            ->whereBetween('fecha_caducidad', [$today, $today->copy()->addDays(28)])
            ->sum('saldo_puntos');

        $expiringPrev = (int) DB::table('bolsas_puntos')
            ->whereNotNull('fecha_caducidad')
            ->whereBetween('fecha_caducidad', [$today->copy()->subDays(28), $today])
            ->sum('saldo_puntos');

        // Series últimos N meses
        $assignedSeries = DB::table('bolsas_puntos')
            ->selectRaw("DATE_FORMAT(fecha_asignacion, '%Y-%m-01') as mes, SUM(puntaje_asignado) as total")
            ->where('fecha_asignacion', '>=', $sixMonthsAgo)
            ->groupBy('mes')
            ->orderBy('mes')
            ->pluck('total', 'mes');

        $redeemedSeries = DB::table('uso_puntos_cab')
            ->selectRaw("DATE_FORMAT(fecha, '%Y-%m-01') as mes, SUM(puntaje_utilizado) as total")
            ->where('fecha', '>=', $sixMonthsAgo)
            ->groupBy('mes')
            ->orderBy('mes')
            ->pluck('total', 'mes');

        $months = collect(range(0, $monthsBack - 1))
            ->map(fn($i) => $sixMonthsAgo->copy()->addMonths($i)->format('Y-m-01'));

        $labels = $months->map(fn($m) => Carbon::parse($m)->isoFormat('MMM'))->values();
        $assignedData = $months->map(fn($m) => (int) ($assignedSeries[$m] ?? 0))->values();
        $redeemedData = $months->map(fn($m) => (int) ($redeemedSeries[$m] ?? 0))->values();

        // Distribución por concepto (últimos N meses)
        $byConcept = DB::table('uso_puntos_cab as c')
            ->join('conceptos_uso as cu', 'cu.id', '=', 'c.concepto_uso_id')
            ->selectRaw('cu.descripcion_concepto as concepto, SUM(c.puntaje_utilizado) as total')
            ->where('c.fecha', '>=', $sixMonthsAgo)
            ->groupBy('cu.descripcion_concepto')
            ->orderByDesc('total')
            ->get();

        $distLabels = $byConcept->pluck('concepto')->values();
        $distValues = $byConcept->pluck('total')->map(fn($v) => (int) $v)->values();
        $distPct    = $this->percentages($distValues->toArray());

        // Actividad reciente
        $lastClient = DB::table('clientes')->select('nombre','apellido','fecha_alta')->orderByDesc('fecha_alta')->first();
        $lastRedeem = DB::table('uso_puntos_cab as c')
            ->join('clientes as cl','cl.id','=','c.cliente_id')
            ->select('c.fecha','c.puntaje_utilizado','cl.nombre','cl.apellido')
            ->orderByDesc('c.fecha')->first();
        $lastRule = DB::table('reglas_asignacion')->select('descripcion')->orderByDesc('id')->first();
        $nextExpiring = DB::table('bolsas_puntos as b')
            ->join('clientes as cl','cl.id','=','b.cliente_id')
            ->select('b.fecha_caducidad','b.saldo_puntos','cl.nombre','cl.apellido')
            ->whereNotNull('b.fecha_caducidad')->where('b.saldo_puntos','>',0)
            ->orderBy('b.fecha_caducidad')->first();

        $activity = collect([
            $lastClient ? [
                'icon'=>'🟢','title'=>'Nuevo cliente registrado',
                'subtitle'=>($lastClient->nombre.' '.$lastClient->apellido),
                'time'=>Carbon::parse($lastClient->fecha_alta)->diffForHumans()
            ] : null,
            $lastRedeem ? [
                'icon'=>'🔵','title'=>'Puntos canjeados',
                'subtitle'=>($lastRedeem->nombre.' '.$lastRedeem->apellido.' – '.$lastRedeem->puntaje_utilizado.' pts'),
                'time'=>Carbon::parse($lastRedeem->fecha)->diffForHumans()
            ] : null,
            $lastRule ? [
                'icon'=>'🟠','title'=>'Regla de puntos actualizada',
                'subtitle'=>$lastRule->descripcion ?? 'Regla','time'=>'Reciente'
            ] : null,
            $nextExpiring ? [
                'icon'=>'🔴','title'=>'Puntos por vencer',
                'subtitle'=>($nextExpiring->nombre.' '.$nextExpiring->apellido.' – '.$nextExpiring->saldo_puntos.' pts'),
                'time'=>Carbon::parse($nextExpiring->fecha_caducidad)->diffForHumans()
            ] : null,
        ])->filter()->values();

        return [
            'kpis' => [
                'activeClients' => $activeClients,
                'granted'       => $grantedThisMonth,
                'grantedMoM'    => $this->pctChange($grantedThisMonth, $grantedPrevMonth),
                'redeemed'      => $redeemedThisMonth,
                'redeemedMoM'   => $this->pctChange($redeemedThisMonth, $redeemedPrevMonth),
                'expiring'      => $expiringNext,
                'expiringMoM'   => $this->pctChange($expiringNext, $expiringPrev),
            ],
            'trend' => [
                'labels'       => $labels,
                'assignedData' => $assignedData,
                'redeemedData' => $redeemedData,
            ],
            'distribution' => [
                'labels' => $distLabels,
                'values' => $distValues,
                'pct'    => $distPct,
            ],
            'activity' => $activity,
        ];
    }

    // ---------------- helpers ----------------

    private function pctChange($current, $previous)
    {
        if ((int)$previous === 0) return $current > 0 ? 100 : 0;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function percentages(array $values)
    {
        $sum = array_sum($values);
        if ($sum <= 0) return array_fill(0, count($values), 0);
        return array_map(fn($v) => round(($v / $sum) * 100), $values);
    }
}
