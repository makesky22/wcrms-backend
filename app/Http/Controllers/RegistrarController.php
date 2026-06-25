<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Payment;
use App\Models\Ward;
use App\Models\Notification;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class RegistrarController extends Controller
{
    // Property billing rates
    const RATES = [
        'residential' => ['amount' => 2000,  'cycle' => 'monthly', 'label' => 'Nyumba ya Makazi'],
        'shop'        => ['amount' => 5000,  'cycle' => 'monthly', 'label' => 'Duka'],
        'market'      => ['amount' => 50000, 'cycle' => 'daily',   'label' => 'Soko'],
    ];

    /**
     * GET /api/registrar/residents
     * List all residents in registrar's ward with full details
     */
    public function residents(Request $request): JsonResponse
    {
        $registrar = $request->user();
        $query = User::with('ward')
            ->where('role', 'resident')
            ->where('ward_id', $registrar->ward_id)
            ->select('id','name','email','phone','ward_id','property_type','is_active','created_at')
            ->latest();

        // Speed: cache for 30 seconds
        $residents = $query->get()->map(fn($r) => [
            'id'            => $r->id,
            'name'          => $r->name,
            'email'         => $r->email,
            'phone'         => $r->phone,
            'ward'          => $r->ward->name ?? '—',
            'property_type' => $r->property_type ?? 'residential',
            'property_label'=> self::RATES[$r->property_type ?? 'residential']['label'],
            'rate'          => self::RATES[$r->property_type ?? 'residential']['amount'],
            'cycle'         => self::RATES[$r->property_type ?? 'residential']['cycle'],
            'is_active'     => $r->is_active,
            'registered'    => $r->created_at->format('d M Y'),
            'qr_payload'    => "WCRMS_RESIDENT:{$r->id}:{$r->ward_id}",
        ]);

        return response()->json(['residents' => $residents, 'total' => $residents->count()]);
    }

    /**
     * POST /api/registrar/residents
     * Register a new resident with property type
     */
    public function registerResident(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'phone'         => 'required|string|max:20',
            'password'      => 'required|string|min:8',
            'property_type' => 'required|in:residential,shop,market',
            'ward_id'       => 'nullable|exists:wards,id',
        ]);

        $wardId = $data['ward_id'] ?? $request->user()->ward_id;

        $resident = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'phone'         => $data['phone'],
            'password'      => Hash::make($data['password']),
            'role'          => 'resident',
            'ward_id'       => $wardId,
            'property_type' => $data['property_type'],
            'is_active'     => true,
        ]);

        // Auto-generate first bill
        $rate = self::RATES[$data['property_type']];
        $billingMonth = Carbon::now()->format('Y-m');
        Payment::create([
            'resident_id'   => $resident->id,
            'ward_id'       => $wardId,
            'billing_month' => $billingMonth,
            'amount'        => $rate['amount'],
            'status'        => 'unpaid',
            'property_type' => $data['property_type'],
            'registered_by' => $request->user()->name,
        ]);

        Notification::create([
            'user_id' => $resident->id,
            'title'   => 'Karibu WCRMS',
            'message' => "Akaunti yako imesajiliwa na {$request->user()->name}. Bili yako ya kwanza: TZS " . number_format($rate['amount']) . ".",
            'type'    => 'account_created',
        ]);

        AuditTrail::log($request->user(), 'register_resident', 'User', $resident->id, [
            'name' => $resident->name, 'property_type' => $data['property_type'],
        ]);

        return response()->json([
            'message'  => 'Resident registered successfully.',
            'resident' => $resident->load('ward'),
            'qr_payload' => "WCRMS_RESIDENT:{$resident->id}:{$resident->ward_id}",
        ], 201);
    }

    /**
     * PUT /api/registrar/residents/{id}
     * Update resident details
     */
    public function updateResident(Request $request, User $user): JsonResponse
    {
        if ($user->role !== 'resident' || $user->ward_id !== $request->user()->ward_id) {
            return response()->json(['message' => 'Not authorized for this resident.'], 403);
        }

        $data = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'phone'         => 'sometimes|string|max:20',
            'property_type' => 'sometimes|in:residential,shop,market',
        ]);

        $user->update($data);

        AuditTrail::log($request->user(), 'update_resident', 'User', $user->id, $data);

        return response()->json($user->fresh()->load('ward'));
    }

    /**
     * GET /api/registrar/billing
     * Registrar's billing view — residents in their ward with bill status
     */
    public function billing(Request $request): JsonResponse
    {
        $registrar = $request->user();
        $month = $request->month ?? Carbon::now()->format('Y-m');

        // Speed: single optimized query with join
        $bills = Payment::with(['resident:id,name,phone,property_type,ward_id', 'resident.ward:id,name'])
            ->whereHas('resident', fn($q) => $q->where('ward_id', $registrar->ward_id))
            ->where('billing_month', $month)
            ->select('id','resident_id','ward_id','billing_month','amount','status','paid_at','payment_ref','property_type','registered_by')
            ->latest()
            ->get()
            ->map(fn($b) => [
                'id'             => $b->id,
                'resident_name'  => $b->resident->name ?? '—',
                'resident_phone' => $b->resident->phone ?? '—',
                'property_type'  => $b->property_type ?? $b->resident->property_type ?? 'residential',
                'property_label' => self::RATES[$b->property_type ?? 'residential']['label'],
                'amount'         => (float) $b->amount,
                'status'         => $b->status,
                'paid_at'        => $b->paid_at?->format('d M Y H:i'),
                'payment_ref'    => $b->payment_ref,
                'registered_by'  => $b->registered_by,
                'billing_month'  => $b->billing_month,
                'qr_payload'     => $b->resident ? "WCRMS_RESIDENT:{$b->resident_id}:{$b->resident->ward_id}" : null,
            ]);

        $summary = [
            'total'    => $bills->count(),
            'paid'     => $bills->where('status', 'paid')->count(),
            'unpaid'   => $bills->where('status', 'unpaid')->count(),
            'collected'=> $bills->where('status', 'paid')->sum('amount'),
        ];

        return response()->json(['bills' => $bills, 'summary' => $summary, 'month' => $month]);
    }

    /**
     * PATCH /api/registrar/billing/{payment}/mark-paid
     * Registrar confirms payment for a resident in their ward
     */
    public function markPaid(Request $request, Payment $payment): JsonResponse
    {
        // Ensure registrar only handles their ward
        if ($payment->ward_id !== $request->user()->ward_id) {
            return response()->json(['message' => 'Not authorized for this payment.'], 403);
        }

        if ($payment->status === 'paid') {
            return response()->json(['message' => 'Already paid.'], 422);
        }

        $data = $request->validate([
            'payment_ref' => 'nullable|string|max:100',
            'notes'       => 'nullable|string|max:255',
        ]);

        $payment->update([
            'status'      => 'paid',
            'paid_at'     => now(),
            'payment_ref' => $data['payment_ref'] ?? null,
            'notes'       => $data['notes'] ?? null,
            'marked_by'   => $request->user()->id,
            'registered_by' => $request->user()->name,
        ]);

        Notification::create([
            'user_id' => $payment->resident_id,
            'title'   => 'Malipo Yamethibitishwa',
            'message' => "Malipo yako ya {$payment->billing_month} (TZS " . number_format($payment->amount) . ") yamethibitishwa na {$request->user()->name}. YAS: " . ($data['payment_ref'] ?? '—'),
            'type'    => 'payment_confirmed',
        ]);

        AuditTrail::log($request->user(), 'registrar_mark_paid', 'Payment', $payment->id, [
            'resident_id' => $payment->resident_id,
            'amount' => (float) $payment->amount,
            'ref' => $data['payment_ref'] ?? null,
        ]);

        return response()->json(['message' => 'Payment confirmed.', 'payment' => $payment->fresh()]);
    }

    /**
     * POST /api/registrar/scan-qr
     * Registrar scans resident QR to verify & view billing
     */
    public function scanQR(Request $request): JsonResponse
    {
        $data = $request->validate(['payload' => 'required|string']);
        $parts = explode(':', $data['payload']);

        if (count($parts) < 3 || $parts[0] !== 'WCRMS_RESIDENT') {
            return response()->json(['valid' => false, 'message' => 'QR code si sahihi.'], 422);
        }

        $resident = User::with('ward')->find((int) $parts[1]);
        if (!$resident || $resident->role !== 'resident') {
            return response()->json(['valid' => false, 'message' => 'Mkaazi hapatikani.'], 422);
        }

        $currentMonth = Carbon::now()->format('Y-m');
        $currentBill  = Payment::where('resident_id', $resident->id)->where('billing_month', $currentMonth)->first();
        $allBills     = Payment::where('resident_id', $resident->id)->orderBy('billing_month','desc')->take(6)->get();
        $rate         = self::RATES[$resident->property_type ?? 'residential'];

        return response()->json([
            'valid'          => true,
            'resident_id'    => $resident->id,
            'resident_name'  => $resident->name,
            'resident_phone' => $resident->phone,
            'ward'           => $resident->ward->name ?? '—',
            'property_type'  => $resident->property_type ?? 'residential',
            'property_label' => $rate['label'],
            'monthly_rate'   => $rate['amount'],
            'current_bill'   => $currentBill ? [
                'id'          => $currentBill->id,
                'amount'      => (float) $currentBill->amount,
                'status'      => $currentBill->status,
                'paid_at'     => $currentBill->paid_at?->format('d M Y'),
                'payment_ref' => $currentBill->payment_ref,
            ] : null,
            'months_owed'    => Payment::where('resident_id', $resident->id)->where('status','unpaid')->count(),
            'total_arrears'  => (float) Payment::where('resident_id', $resident->id)->where('status','unpaid')->sum('amount'),
            'recent_bills'   => $allBills->map(fn($b) => [
                'month'  => $b->billing_month,
                'amount' => (float) $b->amount,
                'status' => $b->status,
            ]),
        ]);
    }

    /**
     * GET /api/registrar/rates
     * Return property billing rates
     */
    public function rates(): JsonResponse
    {
        return response()->json(self::RATES);
    }
}
