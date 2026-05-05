<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\PasswordResetRequest;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    /**
     * List registered users.
     */
    public function index(Request $request)
    {
        $query = User::withCount(['passwordResetRequests as pending_resets_count' => function ($query) {
            $query->where('status', 'pending');
        }]);

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        return response()->json($query->latest()->paginate($request->get('per_page', 15)));
    }

    /**
     * View user details (read-only).
     */
    public function show($id)
    {
        $user = User::with(['orders', 'wishlists'])->findOrFail($id);
        return response()->json($user);
    }

    /**
     * Delete a user (soft delete). Admin can delete but NOT edit.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting admin/superadmin users
        if (in_array($user->role, ['admin', 'superadmin'])) {
            return response()->json(['error' => 'Cannot delete admin accounts'], 403);
        }

        $user->delete();
        ActivityLog::log('deleted', 'User', $id, "User '{$user->name}' ({$user->email}) deleted");

        return response()->json(['message' => 'User deleted']);
    }

    /**
     * Create staff account.
     */
    public function createStaff(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:staff,pharmacist',
            'role_id' => 'nullable|exists:roles,id',
            'phone' => 'nullable|string|max:50',
            'gender' => 'nullable|in:male,female,others',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        // Generate random password
        $password = $this->generateRandomPassword();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($password),
            'role' => $request->role,
            'role_id' => $request->role_id ?? Role::where('name', $request->role)->first()?->id,
            'phone' => $request->phone,
            'gender' => $request->gender,
            'address' => $request->address ?? '',
        ]);

        // TODO: Send credentials via email when SMTP is configured
        // Mail::to($user->email)->send(new StaffCreatedMail($user, $password));

        ActivityLog::log('created', 'User', $user->id, "Staff '{$user->name}' ({$user->role}) created");

        return response()->json([
            'message' => 'Staff created successfully',
            'user' => $user,
            'temporary_password' => $password, // Return in response for admin to share
        ], 201);
    }

    /**
     * Update staff.
     */
    public function updateStaff(Request $request, $id)
    {
        $staff = User::whereIn('role', ['staff', 'pharmacist'])->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:50',
            'role' => 'sometimes|in:staff,pharmacist',
            'role_id' => 'nullable|exists:roles,id',
            'phone' => 'nullable|string|max:50',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $staff->update($request->only(['name', 'role', 'role_id', 'phone']));
        ActivityLog::log('updated', 'User', $id, "Staff '{$staff->name}' updated");

        return response()->json(['message' => 'Staff updated', 'user' => $staff]);
    }

    /**
     * Reset password — generates random password and (will) email it.
     */
    public function resetPassword($id)
    {
        $user = User::findOrFail($id);
        $password = $this->generateRandomPassword();

        $user->update([
            'password' => Hash::make($password),
            'must_change_password' => true
        ]);

        // Mark any pending reset requests as completed
        PasswordResetRequest::where('user_id', $id)
            ->where('status', 'pending')
            ->update(['status' => 'completed']);

        // TODO: Send email with new password when SMTP is configured
        // Mail::to($user->email)->send(new PasswordResetMail($user, $password));

        ActivityLog::log('password_reset', 'User', $id, "Password reset for '{$user->name}'");

        return response()->json([
            'message' => 'Password reset successfully',
            'new_password' => $password, // Return for admin until email is configured
        ]);
    }

    /**
     * List pending password reset requests.
     */
    public function passwordResetRequests()
    {
        return response()->json(
            PasswordResetRequest::with('user')
                ->where('status', 'pending')
                ->latest()
                ->get()
        );
    }

    /**
     * Generate a random secure password.
     */
    private function generateRandomPassword($length = 10): string
    {
        $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lowercase = 'abcdefghjkmnpqrstuvwxyz';
        $numbers = '23456789';
        $specials = '!@#$%&*';

        $password = $uppercase[random_int(0, strlen($uppercase) - 1)]
            . $lowercase[random_int(0, strlen($lowercase) - 1)]
            . $numbers[random_int(0, strlen($numbers) - 1)]
            . $specials[random_int(0, strlen($specials) - 1)];

        $all = $uppercase . $lowercase . $numbers . $specials;
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }
}
