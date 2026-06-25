<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuditTrailController extends Controller
{
    /**
     * Admin views ALL audit logs from ALL users
     * GET /api/admin/audit-trail
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditTrail::with('user:id,name,email,role')
            ->latest();

        // Filter by user
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by action
        if ($request->action) {
            $query->where('action', 'like', $request->action . '%');
        }

        // Filter by date range
        if ($request->from) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->to) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return response()->json($query->paginate(100));
    }
}