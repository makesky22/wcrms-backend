<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Payment;
use App\Models\RecyclableLog;
use App\Models\RouteCompletion;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * GET /api/reports/dashboard-summary
     * Weekly KPI cards — scoped to supervisor's ward if role=supervisor.
     */
    public function dashboardSummary(Request $request): JsonResponse
    {
        $user        = $request->user();
        $wardId      = $user->role === 'supervisor' ? $user->ward_id : null;
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek   = Carbon::now()->endOfWeek();

        $schedulesQ   = Schedule::where('status', 'active');
        $completionsQ = RouteCompletion::whereBetween('completed_at', [$startOfWeek, $endOfWeek]);
        $complaintsQ  = Complaint::where('status', 'open')
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek]);

        if ($wardId) {
            $schedulesQ->where('ward_id', $wardId);
            $completionsQ->where('ward_id', $wardId);
            $complaintsQ->where('ward_id', $wardId);
        }

        $totalSchedules   = $schedulesQ->count();
        $totalCompletions = $completionsQ->count();

        return response()->json([
            'total_schedules'    => $totalSchedules,
            'total_completions'  => $totalCompletions,
            'missed_collections' => max(0, $totalSchedules - $totalCompletions),
            'open_complaints'    => $complaintsQ->count(),
            'week_start'         => $startOfWeek->toDateString(),
            'week_end'           => $endOfWeek->toDateString(),
        ]);
    }

    /**
     * GET /api/reports/supervisor-dashboard
     * Full supervisor dashboard payload — one request, all panels.
     */
    public function supervisorDashboard(Request $request): JsonResponse
    {
        $user   = $request->user();
        $wardId = $user->role === 'supervisor' ? $user->ward_id : null;
        $now    = Carbon::now();

        $startOfWeek = $now->copy()->startOfWeek();
        $endOfWeek   = $now->copy()->endOfWeek();
        $startOfDay  = $now->copy()->startOfDay();
        $endOfDay    = $now->copy()->endOfDay();

        // ── KPI counts ────────────────────────────────────────────────────
        $schedulesQ   = Schedule::where('status', 'active');
        $completionsQ = RouteCompletion::whereBetween('completed_at', [$startOfWeek, $endOfWeek]);
        $complaintsQ  = Complaint::where('status', 'open');

        if ($wardId) {
            $schedulesQ->where('ward_id', $wardId);
            $completionsQ->where('ward_id', $wardId);
            $complaintsQ->where('ward_id', $wardId);
        }

        $totalSchedules   = $schedulesQ->count();
        $totalCompletions = $completionsQ->count();
        $openComplaints   = $complaintsQ->count();

        // ── Today's collections ───────────────────────────────────────────
        $todayQ = RouteCompletion::with(['officer:id,name', 'ward:id,name'])
            ->whereBetween('completed_at', [$startOfDay, $endOfDay])
            ->orderByDesc('completed_at');
        if ($wardId) $todayQ->where('ward_id', $wardId);

        $todayCollections = $todayQ->get()->map(fn($rc) => [
            'officer_name' => $rc->officer->name ?? '—',
            'ward_name'    => $rc->ward->name ?? '—',
            'completed_at' => $rc->completed_at?->format('H:i'),
            'lat'          => $rc->lat,
            'lng'          => $rc->lng,
        ]);

        // ── Officer performance this week ─────────────────────────────────
        $officerQuery = User::where('role', 'officer')->where('is_active', true);
        if ($wardId) {
            $officerQuery->whereHas('schedules', fn($q) => $q->where('ward_id', $wardId));
        }

        $performance = $officerQuery->get()->map(function ($officer) use ($startOfWeek, $endOfWeek, $wardId) {
            $scheduledQ = Schedule::where('officer_id', $officer->id)->where('status', 'active');
            $completedQ = RouteCompletion::where('officer_id', $officer->id)
                ->whereBetween('completed_at', [$startOfWeek, $endOfWeek]);
            if ($wardId) {
                $scheduledQ->where('ward_id', $wardId);
                $completedQ->where('ward_id', $wardId);
            }
            $scheduled = $scheduledQ->count();
            $completed = $completedQ->count();
            return [
                'officer_id'      => $officer->id,
                'officer_name'    => $officer->name,
                'total_scheduled' => $scheduled,
                'total_completed' => $completed,
                'completion_rate' => $scheduled > 0
                    ? round(($completed / $scheduled) * 100, 1) : 0,
            ];
        })->sortByDesc('completion_rate')->values();

        // ── Unpaid residents ──────────────────────────────────────────────
        $unpaidQ = Payment::selectRaw('resident_id, COUNT(*) as months_owed, SUM(amount) as total_arrears')
            ->where('status', 'unpaid')
            ->groupBy('resident_id')
            ->with('resident:id,name,ward_id', 'resident.ward:id,name')
            ->orderByRaw('SUM(amount) DESC');
        if ($wardId) {
            $unpaidQ->whereHas('resident', fn($q) => $q->where('ward_id', $wardId));
        }
        $unpaidResidents = $unpaidQ->get()->map(fn($p) => [
            'resident_name' => $p->resident->name ?? '—',
            'ward_name'     => $p->resident->ward->name ?? '—',
            'months_owed'   => (int) $p->months_owed,
            'total_arrears' => (float) $p->total_arrears,
        ]);

        // ── Open complaints list ──────────────────────────────────────────
        $complaintListQ = Complaint::with(['resident:id,name', 'ward:id,name'])
            ->where('status', 'open')
            ->latest();
        if ($wardId) $complaintListQ->where('ward_id', $wardId);
        $complaintList = $complaintListQ->take(10)->get()->map(fn($c) => [
            'id'            => $c->id,
            'type'          => $c->type,
            'description'   => $c->description,
            'resident_name' => $c->resident->name ?? '—',
            'ward_name'     => $c->ward->name ?? '—',
            'created_at'    => $c->created_at?->format('Y-m-d H:i'),
        ]);

        // ── Charts ────────────────────────────────────────────────────────
        $complaintChartQ = Complaint::selectRaw('ward_id, COUNT(*) as total')
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->groupBy('ward_id')
            ->with('ward:id,name');
        if ($wardId) $complaintChartQ->where('ward_id', $wardId);

        $recyclableTrends = RecyclableLog::selectRaw(
            'category, YEAR(logged_at) as year, MONTH(logged_at) as month, SUM(quantity_kg) as total_kg'
        )
        ->where('logged_at', '>=', $now->copy()->subMonths(6))
        ->groupBy('category', 'year', 'month')
        ->orderByRaw('year ASC, month ASC')
        ->get();

        return response()->json([
            'total_schedules'     => $totalSchedules,
            'total_completions'   => $totalCompletions,
            'missed_collections'  => max(0, $totalSchedules - $totalCompletions),
            'open_complaints'     => $openComplaints,
            'week_start'          => $startOfWeek->toDateString(),
            'week_end'            => $endOfWeek->toDateString(),
            'today_collections'   => $todayCollections,
            'officer_performance' => $performance,
            'unpaid_residents'    => $unpaidResidents,
            'complaint_list'      => $complaintList,
            'complaint_chart'     => $complaintChartQ->get(),
            'recyclable_trends'   => $recyclableTrends,
        ]);
    }

    /**
     * GET /api/reports/officer-performance
     * Officer completion rates for a given date range.
     */
    public function officerPerformance(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $start = Carbon::parse($request->start_date)->startOfDay();
        $end   = Carbon::parse($request->end_date)->endOfDay();

        $officers = User::where('role', 'officer')->get();

        $performance = $officers->map(function ($officer) use ($start, $end) {
            $scheduled = Schedule::where('officer_id', $officer->id)
                ->where('status', 'active')
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $completed = RouteCompletion::where('officer_id', $officer->id)
                ->whereBetween('completed_at', [$start, $end])
                ->count();

            return [
                'officer_id'      => $officer->id,
                'officer_name'    => $officer->name,
                'total_scheduled' => $scheduled,
                'total_completed' => $completed,
                'completion_rate' => $scheduled > 0
                    ? round(($completed / $scheduled) * 100, 1) : 0,
            ];
        })->sortByDesc('completion_rate')->values();

        return response()->json($performance);
    }

    /**
     * GET /api/reports/complaint-volumes
     * Complaint counts per ward for the last 30 days.
     */
    public function complaintVolumes(Request $request): JsonResponse
    {
        $since = Carbon::now()->subDays(30);

        $volumes = Complaint::selectRaw('ward_id, COUNT(*) as total')
            ->where('created_at', '>=', $since)
            ->groupBy('ward_id')
            ->with('ward:id,name')
            ->get();

        return response()->json($volumes);
    }

    /**
     * GET /api/reports/recyclable-trends
     * Recyclables collected by category and month for the last 6 months.
     */
    public function recyclableTrends(Request $request): JsonResponse
    {
        $since = Carbon::now()->subMonths(6);

        $trends = RecyclableLog::selectRaw(
            'category, YEAR(logged_at) as year, MONTH(logged_at) as month, SUM(quantity_kg) as total_kg'
        )
        ->where('logged_at', '>=', $since)
        ->groupBy('category', 'year', 'month')
        ->orderByRaw('year ASC, month ASC')
        ->get();

        return response()->json($trends);
    }

    /**
     * GET /api/payments/unpaid-summary
     * Residents with unpaid bills — for supervisor/admin billing panel.
     */
    public function unpaidSummary(Request $request): JsonResponse
    {
        $user   = $request->user();
        $wardId = $user->role === 'supervisor' ? $user->ward_id : null;

        $query = Payment::selectRaw('resident_id, COUNT(*) as months_owed, SUM(amount) as total_arrears')
            ->where('status', 'unpaid')
            ->groupBy('resident_id')
            ->with('resident:id,name,ward_id', 'resident.ward:id,name')
            ->orderByRaw('SUM(amount) DESC');

        if ($wardId) {
            $query->whereHas('resident', fn($q) => $q->where('ward_id', $wardId));
        }

        $result = $query->get()->map(fn($p) => [
            'resident_id'   => $p->resident_id,
            'resident_name' => $p->resident->name ?? '—',
            'ward_name'     => $p->resident->ward->name ?? '—',
            'months_owed'   => (int) $p->months_owed,
            'total_arrears' => (float) $p->total_arrears,
        ]);

        return response()->json($result);
    }
}
