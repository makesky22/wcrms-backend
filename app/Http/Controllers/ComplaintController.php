<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Notification;
use App\Models\User;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ComplaintController extends Controller
{
    /**
     * List complaints.
     * Admin      → sees ALL complaints
     * Supervisor → sees only their ward's complaints
     */
    public function index(Request $request): JsonResponse
    {
        $query = Complaint::with(['resident', 'ward', 'resolvedBy']);

        if ($request->user()->role === 'supervisor') {
            $query->where('ward_id', $request->user()->ward_id);
        }

        if ($request->status)  $query->where('status',  $request->status);
        if ($request->ward_id) $query->where('ward_id', $request->ward_id);

        return response()->json($query->latest()->paginate(50));
    }

    /**
     * Resident submits a complaint.
     */
    public function store(Request $request): JsonResponse
    {
        // Fetch full user record fresh from DB — never rely on cached auth user
        $user   = User::find($request->user()->id);
        $wardId = $user->ward_id;

        // Safety check
        if (!$wardId) {
            return response()->json([
                'message' => 'Your account has no ward assigned. Contact the administrator.',
            ], 422);
        }

        // Rate limit: max 3 complaints per 24h per ward
        $recentCount = Complaint::where('resident_id', $user->id)
            ->where('ward_id', $wardId)
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->count();

        if ($recentCount >= 3) {
            return response()->json([
                'message' => 'You have reached the maximum of 3 complaints per 24 hours.',
            ], 422);
        }

        // Validate — type MUST be one of these three values
        $validated = $request->validate([
            'type'        => 'required|in:missed,delayed,other',
            'description' => 'required|string|max:500',
            'photo'       => 'nullable|image|mimes:jpeg,png|max:5120',
        ]);

        // Handle optional photo upload
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('complaint-photos', 'public');
        }

        // Create complaint — all 4 required fields explicitly set
        $complaint = Complaint::create([
            'resident_id' => $user->id,
            'ward_id'     => $wardId,
            'type'        => $validated['type'],
            'description' => $validated['description'],
            'photo_path'  => $photoPath,
            'status'      => 'open',
        ]);

        // Notify supervisor of this ward
        $supervisor = User::where('role', 'supervisor')
            ->where('ward_id', $wardId)
            ->first();

        if ($supervisor) {
            Notification::create([
                'user_id' => $supervisor->id,
                'title'   => 'New Complaint Submitted',
                'message' => "A {$complaint->type} complaint from {$user->name} in {$complaint->ward->name}.",
                'type'    => 'new_complaint',
            ]);
        }

        // Notify all admins
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title'   => 'New Complaint Submitted',
                'message' => "A {$complaint->type} complaint from {$user->name} in {$complaint->ward->name}.",
                'type'    => 'new_complaint',
            ]);
        }

        AuditTrail::log($user, 'submit_complaint', 'Complaint', $complaint->id, [
            'type' => $complaint->type,
            'ward' => $complaint->ward->name ?? null,
        ]);

        return response()->json($complaint->load(['resident', 'ward']), 201);
    }

    /**
     * Show a single complaint.
     */
    public function show(Complaint $complaint): JsonResponse
    {
        return response()->json($complaint->load(['resident', 'ward', 'resolvedBy']));
    }

    /**
     * Resident views their own complaints.
     */
    public function myComplaints(Request $request): JsonResponse
    {
        $complaints = Complaint::with(['ward'])
            ->where('resident_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($complaints);
    }

    /**
     * Supervisor/Admin updates complaint status.
     */
    public function updateStatus(Request $request, Complaint $complaint): JsonResponse
    {
        $data = $request->validate([
            'status'          => 'required|in:open,in_progress,resolved,rejected',
            'resolution_note' => 'nullable|string|max:500',
        ]);

        $complaint->update([
            'status'          => $data['status'],
            'resolution_note' => $data['resolution_note'] ?? $complaint->resolution_note,
            'resolved_by'     => in_array($data['status'], ['resolved', 'rejected']) ? $request->user()->id : $complaint->resolved_by,
            'resolved_at'     => in_array($data['status'], ['resolved', 'rejected']) ? now() : $complaint->resolved_at,
        ]);

        // Notify the resident
        Notification::create([
            'user_id' => $complaint->resident_id,
            'title'   => 'Complaint Status Updated',
            'message' => "Your complaint status has been updated to: {$data['status']}.",
            'type'    => 'complaint_update',
        ]);

        AuditTrail::log($request->user(), 'update_complaint_status', 'Complaint', $complaint->id, [
            'status'          => $data['status'],
            'resolution_note' => $data['resolution_note'] ?? null,
        ]);

        return response()->json($complaint->fresh()->load(['resident', 'ward', 'resolvedBy']));
    }
}