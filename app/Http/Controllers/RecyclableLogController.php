<?php

namespace App\Http\Controllers;

use App\Models\RecyclableLog;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RecyclableLogController extends Controller
{
    /**
     * Officer: log recyclable materials at a ward stop.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'schedule_id'           => 'required|exists:schedules,id',
            'ward_id'               => 'required|exists:wards,id',
            'entries'               => 'required|array|min:1',
            'entries.*.category'    => 'required|in:plastic,metal,glass,paper,organic,other',
            'entries.*.quantity_kg' => 'required|numeric|min:0.01',
        ]);

        $logs = [];
        foreach ($data['entries'] as $entry) {
            $logs[] = RecyclableLog::create([
                'officer_id'  => $request->user()->id,
                'ward_id'     => $data['ward_id'],
                'schedule_id' => $data['schedule_id'],
                'category'    => $entry['category'],
                'quantity_kg' => $entry['quantity_kg'],
                'logged_at'   => now(),
            ]);
        }

        AuditTrail::log($request->user(), 'log_recyclables', 'Schedule', $data['schedule_id'], [
            'ward_id' => $data['ward_id'],
            'entries' => count($data['entries']),
        ]);

        return response()->json($logs, 201);
    }

    /**
     * Officer: view their own recent recyclable logs.
     */
    public function myLogs(Request $request): JsonResponse
    {
        $logs = RecyclableLog::with(['ward', 'schedule'])
            ->where('officer_id', $request->user()->id)
            ->latest('logged_at')
            ->take(50)
            ->get();

        return response()->json($logs);
    }

    /**
     * Supervisor: recyclable summary per ward per month.
     */
    public function summary(Request $request): JsonResponse
    {
        $query = RecyclableLog::query()
            ->selectRaw('ward_id, category,
                          YEAR(logged_at)  as year,
                          MONTH(logged_at) as month,
                          SUM(quantity_kg) as total_kg')
            ->groupBy('ward_id', 'category', 'year', 'month')
            ->orderByRaw('year DESC, month DESC')
            ->with('ward:id,name');

        if ($request->ward_id) {
            $query->where('ward_id', $request->ward_id);
        }

        return response()->json($query->get());
    }
}