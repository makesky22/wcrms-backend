<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new resident account.
     * Supervisor and officer accounts are created by the admin only.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'ward_id'  => 'required|exists:wards,id',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role'     => 'resident',   // Self-registration = resident only
            'ward_id'  => $data['ward_id'],
        ]);

        $token = $user->createToken('wcrms-token')->plainTextToken;

        AuditTrail::log($user, 'register', 'User', $user->id, ['email' => $user->email]);

        return response()->json([
            'user'  => $user->load('ward'),
            'token' => $token,
        ], 201);
    }

    /**
     * Authenticate a user and return a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Your account has been deactivated. Please contact the administrator.'], 403);
        }

        // Revoke previous tokens (single-session policy)
        $user->tokens()->delete();

        $token = $user->createToken('wcrms-token')->plainTextToken;

        AuditTrail::log($user, 'login', 'User', $user->id, ['email' => $user->email]);

        return response()->json([
            'user'  => $user->load('ward'),
            'token' => $token,
        ]);
    }

    /**
     * Logout — revoke the current token.
     */
    public function logout(Request $request): JsonResponse
    {
        AuditTrail::log($request->user(), 'logout', 'User', $request->user()->id);
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Return the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('ward'));
    }

    /**
     * Update the authenticated user's own profile (name, phone, email).
     * PUT /api/auth/profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
        ]);

        $old = $user->only(['name', 'email', 'phone']);
        $user->update($data);

        AuditTrail::log($user, 'update_profile', 'User', $user->id, [
            'before' => $old,
            'after'  => $data,
        ]);

        return response()->json($user->fresh()->load('ward'));
    }

    /**
     * Change the authenticated user's own password.
     * PUT /api/auth/password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        AuditTrail::log($user, 'change_password', 'User', $user->id);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    /**
     * Send a password reset link.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Password reset link sent to your email.'])
            : response()->json(['message' => 'Unable to send reset link. Please check your email address.'], 400);
    }

    /**
     * Reset the password using a valid token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successfully.'])
            : response()->json(['message' => 'Invalid or expired reset token.'], 400);
    }
}
