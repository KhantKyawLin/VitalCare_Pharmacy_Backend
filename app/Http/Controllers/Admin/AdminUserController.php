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
use OpenApi\Attributes as OA;

class AdminUserController extends Controller
{
    #[OA\Get(
        path: '/api/admin/users',
        summary: 'List users and customer accounts (Admin only)',
        tags: ['Admin Users & Staff'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'role', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['user', 'staff', 'pharmacist', 'admin'])),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', example: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated user list with pending password resets count'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
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

    #[OA\Get(
        path: '/api/admin/users/{id}',
        summary: 'Get customer/user details with past orders and wishlists',
        tags: ['Admin Users & Staff'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'User profile with related records'),
            new OA\Response(response: 404, description: 'User not found')
        ]
    )]
    public function show($id)
    {
        $user = User::with(['orders', 'wishlists'])->findOrFail($id);
        return response()->json($user);
    }

    #[OA\Delete(
        path: '/api/admin/users/{id}',
        summary: 'Delete user account (Admin only - cannot delete admin)',
        tags: ['Admin Users & Staff'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'User deleted'),
            new OA\Response(response: 403, description: 'Cannot delete admin accounts'),
            new OA\Response(response: 404, description: 'User not found')
        ]
    )]
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

    #[OA\Post(
        path: '/api/admin/staff',
        summary: 'Create a new staff or pharmacist account with temporary password',
        tags: ['Admin Users & Staff'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Daw Khin'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'staff1@vitalcare.com'),
                    new OA\Property(property: 'role', type: 'string', enum: ['staff', 'pharmacist'], example: 'pharmacist'),
                    new OA\Property(property: 'role_id', type: 'integer', example: 2),
                    new OA\Property(property: 'phone', type: 'string', example: '+95912345678'),
                    new OA\Property(property: 'gender', type: 'string', enum: ['male', 'female', 'others'], example: 'female'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Staff created with generated temporary password'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
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

    #[OA\Put(
        path: '/api/admin/staff/{id}',
        summary: 'Update staff or pharmacist account info or role',
        tags: ['Admin Users & Staff'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Daw Khin'),
                    new OA\Property(property: 'role', type: 'string', enum: ['staff', 'pharmacist'], example: 'pharmacist'),
                    new OA\Property(property: 'role_id', type: 'integer', example: 2),
                    new OA\Property(property: 'phone', type: 'string', example: '+95912345678'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Staff updated'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Staff not found')
        ]
    )]
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

    #[OA\Post(
        path: '/api/admin/users/{id}/reset-password',
        summary: 'Manually reset a user password and flag must_change_password',
        tags: ['Admin Users & Staff'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 1))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Password reset with newly generated password'),
            new OA\Response(response: 404, description: 'User not found')
        ]
    )]
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

    #[OA\Get(
        path: '/api/admin/password-reset-requests',
        summary: 'List pending user password reset requests',
        tags: ['Admin Users & Staff'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'List of pending password reset requests')
        ]
    )]
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
