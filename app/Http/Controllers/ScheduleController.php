<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\RouteCompletion;
use App\Models\Notification;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    /**
     * List all schedules (supervisor/admin).
     * Supports filters: ward_id, officer_id, vehicle_id, status, date.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Schedule::with(['ward','vehicle','officer','supervisor']);

        if ($request->ward_id)    $query->where('ward_id',    $request->ward_id);
        if ($request->officer_id) $query->where('officer_id', $request->officer_id);
        if ($request->vehicle_id) $query->where('vehicle_id', $request->vehicle_id);
        if ($request->status)     $query->where('status',     $request->status);

        return response()->json($query->latest()->paginate(20));
    }

    /**
     * Create a new schedule.
     * Checks for vehicle time-window conflicts on the same day.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ward_id'         => 'required|exists:wards,id',
            'vehicle_id'      => 'required|exists:vehicles,id',
            'officer_id'      => 'required|exists:users,id',
            'collection_days' => 'required|string',
            'start_time'      => 'required|date_format:H:i',
            'end_time'        => 'required|date_format:H:i|after:start_time',
            'notes'           => 'nullable|string',
        ]);

        // Vehicle conflict check
        $days = explode(',', $data['collection_days']);
        $conflict = Schedule::where('vehicle_id', $data['vehicle_id'])
            ->where('status', 'active')
            ->get()
            ->filter(function ($s) use ($days, $data) {
                $sDays = explode(',', $s->collection_days);
                $dayOverlap = array_intersect($days, $sDays);
                if (empty($dayOverlap)) return false;
                // Time overlap check
                return $data['start_time'] < $s->end_time &&
                       $data['end_time']   > $s->start_time;
            });

        if ($conflict->isNotEmpty()) {
            return response()->json([
                'message' => 'Vehicle is already scheduled during this time window.',
                'conflict' => $conflict->first()
            ], 422);
        }

        $data['supervisor_id'] = $request->user()->id;
        $schedule = Schedule::create($data);

        // Notify the assigned officer
        Notification::create([
            'user_id' => $data['officer_id'],
            'title'   => 'New Schedule Assigned',
            'message' => "You have been assigned a new collection schedule for {$schedule->ward->name}.",
            'type'    => 'schedule_change',
        ]);

        AuditTrail::log($request->user(), 'create_schedule', 'Schedule', $schedule->id, [
            'ward'      => $schedule->ward->name ?? null,
            'officer_id'=> $schedule->officer_id,
        ]);

        return response()->json($schedule->load(['ward','vehicle','officer']), 201);
    }

    /**
     * Show a single schedule.
     */
    public function show(Schedule $schedule): JsonResponse
    {
        return response()->json($schedule->load(['ward','vehicle','officer','supervisor','completions']));
    }

    /**
     * Update a schedule. Notifies the officer of changes.
     */
    public function update(Request $request, Schedule $schedule): JsonResponse
    {
        $data = $request->validate([
            'ward_id'         => 'sometimes|exists:wards,id',
            'vehicle_id'      => 'sometimes|exists:vehicles,id',
            'officer_id'      => 'sometimes|exists:users,id',
            'collection_days' => 'sometimes|string',
            'start_time'      => 'sometimes|date_format:H:i',
            'end_time'        => 'sometimes|date_format:H:i',
            'status'          => 'sometimes|in:active,cancelled',
            'notes'           => 'nullable|string',
        ]);

        $schedule->update($data);

        // Notify officer of change
        Notification::create([
            'user_id' => $schedule->officer_id,
            'title'   => 'Schedule Updated',
            'message' => "Your collection schedule for {$schedule->ward->name} has been updated.",
            'type'    => 'schedule_change',
        ]);

        AuditTrail::log($request->user(), 'update_schedule', 'Schedule', $schedule->id, $data);

        return response()->json($schedule->load(['ward','vehicle','officer']));
    }

    /**
     * Cancel (soft-delete via status) a schedule.
     */
    public function destroy(Request $request, Schedule $schedule): JsonResponse
    {
        $schedule->update(['status' => 'cancelled']);
        AuditTrail::log($request->user(), 'cancel_schedule', 'Schedule', $schedule->id, [
            'ward_id' => $schedule->ward_id,
        ]);
        return response()->json(['message' => 'Schedule cancelled.']);
    }

    /**
     * Officer: view my schedule for today + next 7 days.
     */
    public function mySchedule(Request $request): JsonResponse
    {
        $today   = strtolower(Carbon::now()->englishDayOfWeek);
        $officer = $request->user();

        $schedules = Schedule::with(['ward','vehicle'])
            ->where('officer_id', $officer->id)
            ->where('status', 'active')
            ->get()
            ->filter(function ($s) use ($today) {
                $days = array_map('strtolower', explode(',', $s->collection_days));
                // Return schedules for today and next 7 days
                $upcomingDays = [];
                for ($i = 0; $i < 7; $i++) {
                    $upcomingDays[] = strtolower(Carbon::now()->addDays($i)->englishDayOfWeek);
                }
                return !empty(array_intersect($days, $upcomingDays));
            })
            ->values();

        return response()->json($schedules);
    }

    /**
     * Officer: mark a ward stop as completed.
     */
    public function markComplete(Request $request, Schedule $schedule): JsonResponse
    {
        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $completion = RouteCompletion::create([
            'schedule_id'  => $schedule->id,
            'officer_id'   => $request->user()->id,
            'ward_id'      => $schedule->ward_id,
            'lat'          => $data['lat'],
            'lng'          => $data['lng'],
            'completed_at' => now(),
        ]);

        AuditTrail::log($request->user(), 'complete_route', 'Schedule', $schedule->id, [
            'ward_id' => $schedule->ward_id,
            'lat'     => $data['lat'],
            'lng'     => $data['lng'],
        ]);

        return response()->json($completion, 201);
    }

    /**
     * List completions for a specific schedule.
     */
    public function completions(Schedule $schedule): JsonResponse
    {
        return response()->json($schedule->completions()->with('officer')->get());
    }

    /**
     * Public schedule lookup — no auth required.
     */
    public function publicByWard(Request $request, $wardId): JsonResponse
    {
        $schedules = Schedule::with(['vehicle'])
            ->where('ward_id', $wardId)
            ->where('status', 'active')
            ->select('id','collection_days','start_time','end_time')
            ->get();

        return response()->json($schedules);
    }
}
