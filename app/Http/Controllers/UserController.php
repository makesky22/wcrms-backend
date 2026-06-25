<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List all users â€” Admin sees ALL, filter by role supported
     * GET /api/admin/users
     */
    public function index(Request $request)
    {
        $query = User::with('ward')->latest();

        // Filter by role if provided
        if ($request->role) {
            $query->where('role', $request->role);
        }

        $users = $query->get();

        // Log admin viewing users
        AuditTrail::log(
            $request->user(),
            'view_users',
            'User',
            null,
            ['filter_role' => $request->role ?? 'all', 'count' => $users->count()]
        );

        return response()->json($users);
    }

    /**
     * Create a new user
     * POST /api/admin/users
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role'     => 'required|in:admin,supervisor,officer,resident',
            'ward_id'  => 'nullable|exists:wards,id',
            'phone'    => 'nullable|string|max:20',
        ]);

        $validated['password']  = bcrypt($validated['password']);
        $validated['is_active'] = true;

        $user = User::create($validated);

        // Log user creation
        AuditTrail::log(
            $request->user(),
            'create_user',
            'User',
            $user->id,
            ['name' => $user->name, 'email' => $user->email, 'role' => $user->role]
        );

        return response()->json($user->load('ward'), 201);
    }

    /**
     * Update an existing user
     * PUT /api/admin/users/{id}
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|string|email|unique:users,email,' . $user->id,
            'role'     => 'sometimes|in:admin,supervisor,officer,resident',
            'ward_id'  => 'nullable|exists:wards,id',
            'phone'    => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $old = $user->only(['name', 'email', 'role', 'ward_id']);

        $passwordChanged = false;
        if (! empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
            $passwordChanged = true;
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        // Log user update (never log the password value itself)
        $logAfter = collect($validated)->except('password')->all();
        AuditTrail::log(
            $request->user(),
            'update_user',
            'User',
            $user->id,
            ['before' => $old, 'after' => $logAfter, 'password_reset' => $passwordChanged]
        );

        return response()->json($user->fresh()->load('ward'));
    }

    /**
     * Activate a user account
     * PATCH /api/admin/users/{id}/activate
     */
    public function activate(Request $request, User $user)
    {
        $user->update(['is_active' => true]);

        AuditTrail::log(
            $request->user(),
            'activate_user',
            'User',
            $user->id,
            ['name' => $user->name, 'email' => $user->email]
        );

        return response()->json(['message' => 'User activated successfully.']);
    }

    /**
     * Deactivate a user account
     * PATCH /api/admin/users/{id}/deactivate
     */
    public function deactivate(Request $request, User $user)
    {
        // Prevent admin from deactivating themselves
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        $user->update(['is_active' => false]);

        AuditTrail::log(
            $request->user(),
            'deactivate_user',
            'User',
            $user->id,
            ['name' => $user->name, 'email' => $user->email]
        );

        return response()->json(['message' => 'User deactivated successfully.']);
    }

    /**
     * Permanently delete a user account.
     * DELETE /api/users/{id}
     */
    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $name  = $user->name;
        $email = $user->email;
        $id    = $user->id;

        $user->delete();

        AuditTrail::log(
            $request->user(),
            'delete_user',
            'User',
            $id,
            ['name' => $name, 'email' => $email]
        );

        return response()->json(['message' => 'User deleted successfully.']);
    }
}