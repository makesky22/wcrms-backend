<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use App\Models\Notification;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class PaymentController extends Controller
{
    /** Resident: view all their bills. GET /api/payments/my-bills */
    public function myBills(Request $request): JsonResponse
    {
        $bills = Payment::where('resident_id', $request->user()->id)
            ->orderBy('billing_month', 'desc')
            ->get()
            ->map(fn($b) => [
                'id'            => $b->id,
                'billing_month' => $b->billing_month,
                'amount'        => (float) $b->amount,
                'status'        => $b->status,
                'paid_at'       => $b->paid_at?->format('d M Y'),
                'payment_ref'   => $b->payment_ref,
                'notes'         => $b->notes,
                'due_date'      => Carbon::parse($b->billing_month . '-01')->endOfMonth()->format('d M Y'),
                'overdue'       => $b->status === 'unpaid' && Carbon::parse($b->billing_month . '-01')->endOfMonth()->isPast(),
            ]);

        $summary = [
            'total_paid'        => (float) Payment::where('resident_id', $request->user()->id)->where('status', 'paid')->sum('amount'),
            'total_unpaid'      => (float) Payment::where('resident_id', $request->user()->id)->where('status', 'unpaid')->sum('amount'),
            'months_in_arrears' => Payment::where('resident_id', $request->user()->id)->where('status', 'unpaid')->count(),
        ];

        return response()->json(['bills' => $bills, 'summary' => $summary]);
    }

    /** Admin: generate monthly bills. POST /api/payments/generate */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'billing_month' => 'required|date_format:Y-m',
            'amount'        => 'required|numeric|min:1',
            'ward_id'       => 'nullable|exists:wards,id',
        ]);

        $query = User::where('role', 'resident')->where('is_active', true);
        if ($data['ward_id'] ?? null) $query->where('ward_id', $data['ward_id']);

        $created = $skipped = 0;
        foreach ($query->get() as $resident) {
            $exists = Payment::where('resident_id', $resident->id)->where('billing_month', $data['billing_month'])->exists();
            if ($exists) { $skipped++; continue; }

            Payment::create([
                'resident_id'   => $resident->id,
                'ward_id'       => $resident->ward_id,
                'billing_month' => $data['billing_month'],
                'amount'        => $data['amount'],
                'status'        => 'unpaid',
            ]);

            Notification::create([
                'user_id' => $resident->id,
                'title'   => 'New Bill Generated',
                'message' => "Your waste collection bill for {$data['billing_month']} is TZS " . number_format($data['amount']) . ". Please pay before end of month.",
                'type'    => 'bill_generated',
            ]);
            $created++;
        }

        AuditTrail::log($request->user(), 'generate_bills', 'Payment', null, [
            'billing_month' => $data['billing_month'],
            'amount'        => $data['amount'],
            'created'       => $created,
            'skipped'       => $skipped,
        ]);

        return response()->json(['message' => "Bills generated: {$created} new, {$skipped} already existed.", 'created' => $created, 'skipped' => $skipped]);
    }

    /** Admin: mark a bill as paid. PATCH /api/payments/{payment}/mark-paid */
    public function markPaid(Request $request, Payment $payment): JsonResponse
    {
        $data = $request->validate(['payment_ref' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:255']);

        if ($payment->status === 'paid') {
            return response()->json(['message' => 'Already marked as paid.'], 422);
        }

        $payment->update([
            'status'      => 'paid',
            'paid_at'     => now(),
            'payment_ref' => $data['payment_ref'] ?? null,
            'notes'       => $data['notes'] ?? null,
            'marked_by'   => $request->user()->id,
        ]);

        Notification::create([
            'user_id' => $payment->resident_id,
            'title'   => 'Payment Confirmed',
            'message' => "Payment for {$payment->billing_month} (TZS " . number_format($payment->amount) . ") confirmed.",
            'type'    => 'payment_confirmed',
        ]);

        AuditTrail::log($request->user(), 'mark_payment_paid', 'Payment', $payment->id, [
            'billing_month' => $payment->billing_month,
            'amount'        => (float) $payment->amount,
        ]);

        return response()->json($payment->fresh()->load('resident'));
    }

    /** Admin/Supervisor: list all bills. GET /api/payments */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['resident', 'resident.ward']);
        if ($request->user()->role === 'supervisor') $query->where('ward_id', $request->user()->ward_id);
        if ($request->ward_id) $query->where('ward_id', $request->ward_id);
        if ($request->status)  $query->where('status', $request->status);
        if ($request->month)   $query->where('billing_month', $request->month);
        return response()->json($query->orderBy('billing_month', 'desc')->paginate(50));
    }
}
