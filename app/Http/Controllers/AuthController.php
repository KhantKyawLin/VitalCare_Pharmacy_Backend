<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\PasswordResetRequest;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Login — returns JWT token.
     */
    public function login()
    {
        $credentials = request(['email', 'password']);
        \Illuminate\Support\Facades\Log::info('Login attempt:', $credentials);

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Register a new user.
     */
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

    /**
     * Get authenticated user.
     */
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

    /**
     * Logout.
     */
    public function logout()
    {
        auth('api')->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh token.
     */
    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    /**
     * Update own profile.
     */
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

    /**
     * Force user to change their password (used after manual admin reset).
     */
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

    /**
     * Forgot password — creates a reset request for admin.
     */
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
