<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\PasswordResetRequest;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/auth/login',
        summary: 'User & Admin login',
        description: 'Authenticates user credentials and returns a JWT Bearer token.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@vitalcare.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Admin@122'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful login',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'access_token', type: 'string', example: 'eyJhbGciOi...'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                        new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid email or password')
        ]
    )]
    public function login()
    {
        $credentials = request(['email', 'password']);
        \Illuminate\Support\Facades\Log::info('Login attempt:', $credentials);

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = auth('api')->user();
        \App\Models\ActivityLog::log('login', 'User', $user->id, "User logged in: {$user->name} ({$user->role})");
        
        if ($user->role !== 'user') {
            event(new \App\Events\StaffLoggedIn($user));
        }

        return $this->respondWithToken($token);
    }

    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Register a new customer account',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'gender', 'phone', 'address'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Secret123!'),
                    new OA\Property(property: 'gender', type: 'string', enum: ['male', 'female', 'others'], example: 'male'),
                    new OA\Property(property: 'phone', type: 'string', example: '+95912345678'),
                    new OA\Property(property: 'address', type: 'string', example: '45 Bogyoke Rd, Yangon'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User registered and authenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'access_token', type: 'string', example: 'eyJhbGciOi...'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                        new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation error (e.g. email already exists)')
        ]
    )]
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'nullable|in:admin,user',
            'gender' => 'required|in:male,female,others',
            'phone' => 'required|string|max:50',
            'address' => 'required|string',
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(), 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'user',
            'gender' => $request->gender,
            'phone' => $request->phone,
            'address' => $request->address,
        ]);

        $token = auth('api')->login($user);
        return $this->respondWithToken($token);
    }

    #[OA\Get(
        path: '/api/auth/me',
        summary: 'Get authenticated user profile and permissions',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User details with assigned permissions',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'System Admin'),
                        new OA\Property(property: 'email', type: 'string', example: 'admin@vitalcare.com'),
                        new OA\Property(property: 'role', type: 'string', example: 'admin'),
                        new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string', example: 'categories.crud'))
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function me()
    {
        $user = auth('api')->user();
        $data = $user->toArray();
        $data['permissions'] = [];

        // Include role permissions for frontend
        if ($user->roleModel) {
            $data['permissions'] = $user->roleModel->permissions->pluck('name');
        }

        // Admin/superadmin gets all permissions
        if ($user->isAdmin()) {
            $data['permissions'] = \App\Models\Permission::pluck('name');
        }

        return response()->json($data);
    }

    #[OA\Post(
        path: '/api/auth/logout',
        summary: 'Log out current user and invalidate token',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Successfully logged out'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function logout()
    {
        auth('api')->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    #[OA\Post(
        path: '/api/auth/refresh',
        summary: 'Refresh expired or expiring JWT token',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'New token issued',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'access_token', type: 'string', example: 'eyJhbGciOi...'),
                        new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                        new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated or invalid token')
        ]
    )]
    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    #[OA\Post(
        path: '/api/auth/profile',
        summary: 'Update current user profile information and avatar',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                        new OA\Property(property: 'phone', type: 'string', example: '+95912345678'),
                        new OA\Property(property: 'address', type: 'string', example: '45 Bogyoke Rd, Yangon'),
                        new OA\Property(property: 'gender', type: 'string', enum: ['male', 'female', 'others'], example: 'male'),
                        new OA\Property(property: 'current_password', type: 'string', format: 'password', description: 'Required if changing password'),
                        new OA\Property(property: 'password', type: 'string', format: 'password'),
                        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                        new OA\Property(property: 'profile_image', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profile updated successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function updateProfile(Request $request)
    {
        $user = auth('api')->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:50',
            'phone' => 'sometimes|string|max:50',
            'address' => 'sometimes|string',
            'gender' => 'sometimes|in:male,female,others',
            'current_password' => 'required_with:password|string',
            'password' => 'sometimes|string|min:6|confirmed',
            'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $data = $request->only(['name', 'phone', 'address', 'gender']);

        if ($request->has('password')) {
            // Check current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json(['current_password' => ['The current password does not match our records.']], 422);
            }
            $data['password'] = Hash::make($request->password);
        }

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $imagePath = $file->path();
            $mime = $file->getMimeType();
            $image = null;
            
            if ($mime == 'image/jpeg' || $mime == 'image/jpg') {
                $image = @imagecreatefromjpeg($imagePath);
            } elseif ($mime == 'image/png') {
                $image = @imagecreatefrompng($imagePath);
            }
            
            if ($image) {
                // Resize if too large
                $width = imagesx($image);
                $height = imagesy($image);
                if ($width > 800 || $height > 800) {
                    $ratio = min(800 / $width, 800 / $height);
                    $newWidth = $width * $ratio;
                    $newHeight = $height * $ratio;
                    $resized = imagecreatetruecolor($newWidth, $newHeight);
                    
                    // Handle transparency for PNGs
                    if ($mime == 'image/png') {
                        imagealphablending($resized, false);
                        imagesavealpha($resized, true);
                        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
                    }
                    
                    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    imagedestroy($image);
                    $image = $resized;
                }

                $filename = 'profiles/' . uniqid() . '_' . time() . '.jpg';
                $fullPath = storage_path('app/public/' . $filename);
                
                if (!file_exists(dirname($fullPath))) {
                    mkdir(dirname($fullPath), 0755, true);
                }
                
                imagejpeg($image, $fullPath, 75); // 75% quality
                imagedestroy($image);
                $data['profile'] = $filename;
            } else {
                $data['profile'] = $file->store('profiles', 'public');
            }
        }

        $user->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully', 
            'user' => $user->fresh()
        ]);
    }

    #[OA\Post(
        path: '/api/auth/force-change-password',
        summary: 'Force change password on temporary admin reset',
        tags: ['Authentication'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'NewSecurePass123!'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'NewSecurePass123!'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password updated successfully'),
            new OA\Response(response: 400, description: 'Password change not required'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function forceChangePassword(Request $request)
    {
        $user = auth('api')->user();

        if (!$user->must_change_password) {
            return response()->json(['message' => 'Password change is not required.'], 400);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false
        ]);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    #[OA\Post(
        path: '/api/forgot-password',
        summary: 'Submit a password reset request to administrator',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'customer@example.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password reset request submitted'),
            new OA\Response(response: 409, description: 'A password reset request is already pending'),
            new OA\Response(response: 422, description: 'Email not found or invalid')
        ]
    )]
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);
        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $user = User::where('email', $request->email)->first();

        // Check if there's already a pending request
        $existing = PasswordResetRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json(['message' => 'A password reset request is already pending.'], 409);
        }

        PasswordResetRequest::create([
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Password reset request submitted. The admin will process your request and send you a new password via email.']);
    }

    /**
     * Return token response.
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => auth('api')->user()
        ]);
    }
}
