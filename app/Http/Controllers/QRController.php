<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Notification;
use App\Models\RouteCompletion;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class QRController extends Controller
{
    /**
     * Officer scans a resident's QR code during waste collection.
     * POST /api/qr/scan-resident
     * Body: { payload: "WCRMS_RESIDENT:42:7", schedule_id: 5 }
     */
    public function scanResident(Request $request): JsonResponse
    {
        $request->validate([
            'payload'     => 'required|string',
            'schedule_id' => 'required|exists:schedules,id',
        ]);

        $parts = explode(':', $request->payload);

        if (count($parts) < 3 || $parts[0] !== 'WCRMS_RESIDENT') {
            return response()->json(['valid' => false, 'message' => 'Not a valid WCRMS resident QR code.'], 422);
        }

        $residentId = (int) $parts[1];
        $wardId     = (int) $parts[2];

        $resident = User::with('ward')->find($residentId);
        if (!$resident || $resident->role !== 'resident') {
            return response()->json(['valid' => false, 'message' => 'Resident not found.'], 422);
        }

        $schedule = Schedule::find($request->schedule_id);
        if (!$schedule || $schedule->status !== 'active') {
            return response()->json(['valid' => false, 'message' => 'No active schedule found.'], 422);
        }

        // Log the collection
        RouteCompletion::firstOrCreate(
            ['schedule_id' => $schedule->id, 'officer_id' => $request->user()->id, 'ward_id' => $resident->ward_id],
            ['lat' => $request->lat ?? null, 'lng' => $request->lng ?? null, 'completed_at' => now()]
        );

        // Notify resident
        Notification::create([
            'user_id' => $resident->id,
            'title'   => 'Waste Collected',
            'message' => 'Your waste has been collected by Officer ' . $request->user()->name . '.',
            'type'    => 'collection_confirmed',
        ]);

        AuditTrail::log($request->user(), 'scan_resident_qr', 'User', $resident->id, [
            'schedule_id' => $schedule->id,
            'ward'        => $resident->ward->name ?? null,
        ]);

        // Payment status
        $currentMonth = Carbon::now()->format('Y-m');
        $currentBill  = Payment::where('resident_id', $residentId)->where('billing_month', $currentMonth)->first();
        $monthsOwed   = Payment::where('resident_id', $residentId)->where('status', 'unpaid')->count();
        $totalArrears = Payment::where('resident_id', $residentId)->where('status', 'unpaid')->sum('amount');

        return response()->json([
            'valid'          => true,
            'resident_name'  => $resident->name,
            'resident_phone' => $resident->phone,
            'ward'           => $resident->ward->name ?? '—',
            'payment_status' => $currentBill ? $currentBill->status : 'no_bill',
            'amount_due'     => $currentBill ? (float) $currentBill->amount : 0,
            'months_owed'    => $monthsOwed,
            'total_arrears'  => (float) $totalArrears,
            'billing_month'  => $currentMonth,
            'collection_time'=> Carbon::now()->format('H:i'),
            'collection_date'=> Carbon::now()->format('d M Y'),
        ]);
    }

    /**
     * Officer: today's scans.
     * GET /api/qr/today-scans
     */
    public function todayScans(Request $request): JsonResponse
    {
        $scans = RouteCompletion::with('ward')
            ->where('officer_id', $request->user()->id)
            ->whereDate('completed_at', Carbon::today())
            ->latest('completed_at')
            ->get()
            ->map(fn($s) => [
                'ward' => $s->ward->name ?? '—',
                'time' => Carbon::parse($s->completed_at)->format('H:i'),
                'lat'  => $s->lat,
                'lng'  => $s->lng,
            ]);
        return response()->json($scans);
    }

    /**
     * Resident gets their personal QR payload.
     * GET /api/qr/my-code
     */
    public function myCode(Request $request): JsonResponse
    {
        $resident = $request->user();
        if ($resident->role !== 'resident') {
            return response()->json(['message' => 'Only residents have a personal QR code.'], 403);
        }
        $payload = "WCRMS_RESIDENT:{$resident->id}:{$resident->ward_id}";
        return response()->json([
            'payload'       => $payload,
            'resident_name' => $resident->name,
            'ward_id'       => $resident->ward_id,
            'ward_name'     => $resident->ward->name ?? '—',
        ]);
    }
}
