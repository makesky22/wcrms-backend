<?php

namespace App\Http\Controllers;

use App\Models\GpsLog;
use App\Models\Schedule;
use App\Models\Vehicle;
use App\Models\Notification;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class GpsLogController extends Controller
{
    /**
     * Officer transmits GPS coordinates.
     * Called every 120 seconds from the mobile interface.
     */
    public function transmit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat'         => 'required|numeric|between:-90,90',
            'lng'         => 'required|numeric|between:-180,180',
            'vehicle_id'  => 'required|exists:vehicles,id',
            'schedule_id' => 'required|exists:schedules,id',
        ]);

        // Verify this officer owns this schedule
        $schedule = Schedule::where('id', $data['schedule_id'])
            ->where('officer_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (! $schedule) {
            return response()->json(['message' => 'No active schedule found for this officer.'], 403);
        }

        $log = GpsLog::create([
            'officer_id'     => $request->user()->id,
            'vehicle_id'     => $data['vehicle_id'],
            'schedule_id'    => $data['schedule_id'],
            'lat'            => $data['lat'],
            'lng'            => $data['lng'],
            'transmitted_at' => now(),
        ]);

        return response()->json($log, 201);
    }

    /**
     * Start a GPS session — validates officer has an active schedule assigned.
     * (Time-window restriction removed: tracking is now allowed any time the
     * officer has at least one active schedule, regardless of day/hour.)
     */
    public function startSession(Request $request): JsonResponse
    {
        $officer = $request->user();

        $schedule = Schedule::where('officer_id', $officer->id)
            ->where('status', 'active')
            ->first();

        if (! $schedule) {
            return response()->json([
                'message' => 'No active schedule is assigned to you.',
                'can_track' => false,
            ], 422);
        }

        AuditTrail::log($officer, 'start_gps_session', 'Schedule', $schedule->id, [
            'vehicle_id' => $schedule->vehicle_id,
        ]);

        return response()->json([
            'message'     => 'GPS session started.',
            'can_track'   => true,
            'schedule_id' => $schedule->id,
            'vehicle_id'  => $schedule->vehicle_id,
        ]);
    }

    /**
     * Stop a GPS session.
     */
    public function stopSession(Request $request): JsonResponse
    {
        // Nothing to store — client simply stops transmitting.
        AuditTrail::log($request->user(), 'stop_gps_session', 'User', $request->user()->id);
        return response()->json(['message' => 'GPS session stopped.']);
    }

    /**
     * Supervisor: get live positions of all vehicles on an active schedule.
     * (Time-window restriction removed: shows every active schedule's latest
     * position regardless of day/hour, not just those currently "in window".)
     */
    public function livePositions(Request $request): JsonResponse
    {
        $activeSchedules = Schedule::with(['vehicle', 'officer', 'ward'])
            ->where('status', 'active')
            ->get();

        $positions = $activeSchedules->map(function ($schedule) {
            $latest = GpsLog::where('vehicle_id', $schedule->vehicle_id)
                ->where('schedule_id', $schedule->id)
                ->latest('transmitted_at')
                ->first();

            $cutoff   = Carbon::now()->subMinutes(15);
            $inactive = $latest && Carbon::parse($latest->transmitted_at)->lt($cutoff);

            return [
                'schedule_id'       => $schedule->id,
                'officer_name'      => $schedule->officer->name,
                'vehicle_reg'       => $schedule->vehicle->registration,
                'ward_name'         => $schedule->ward->name,
                'lat'               => $latest?->lat,
                'lng'               => $latest?->lng,
                'last_transmission' => $latest?->transmitted_at,
                'is_inactive'       => $inactive || !$latest,
            ];
        });

        return response()->json($positions->values());
    }

    /**
     * Supervisor: replay historical GPS track for a vehicle on a date.
     */
    public function replay(Request $request, Vehicle $vehicle): JsonResponse
    {
        $request->validate([
            'date'        => 'required|date',
            'schedule_id' => 'sometimes|exists:schedules,id',
        ]);

        $date  = Carbon::parse($request->date);
        $query = GpsLog::where('vehicle_id', $vehicle->id)
            ->whereDate('transmitted_at', $date)
            ->orderBy('transmitted_at');

        if ($request->schedule_id) {
            $query->where('schedule_id', $request->schedule_id);
        }

        $logs = $query->get(['lat','lng','transmitted_at']);
        return response()->json($logs);
    }

    /**
     * Supervisor: get inactive vehicle alerts.
     * Vehicles on an active schedule with no transmission in the last 15 min.
     * (Time-window restriction removed: checks every active schedule, not
     * just those currently "in window".)
     */
    public function inactiveAlerts(Request $request): JsonResponse
    {
        $cutoff = Carbon::now()->subMinutes(15);

        $activeSchedules = Schedule::with(['vehicle', 'officer', 'ward'])
            ->where('status', 'active')
            ->get();

        $alerts = $activeSchedules->filter(function ($schedule) use ($cutoff) {
            $latest = GpsLog::where('schedule_id', $schedule->id)
                ->latest('transmitted_at')->first();
            return !$latest || Carbon::parse($latest->transmitted_at)->lt($cutoff);
        })->map(function ($schedule) {
            return [
                'schedule_id'  => $schedule->id,
                'officer_name' => $schedule->officer->name,
                'vehicle_reg'  => $schedule->vehicle->registration,
                'ward_name'    => $schedule->ward->name,
            ];
        });

        return response()->json($alerts->values());
    }
}
