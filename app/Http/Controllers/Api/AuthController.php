<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /*Login*/
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required','email',],
            'password' => ['required','string', ],
            'device_name' => ['nullable','string','max:100',],
        ]);

        $user = User::where('email', $credentials['email'] )->first();



        if (!$user || !Hash::check( $credentials['password'], $user->password )
        ) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /*Account Status*/
        if (
            !$user->is_active
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Your account has been deactivated. Please contact an administrator.',
            ], 403);
        }

        $user->forceFill([
            'last_login_at' =>
                now(),
        ])->save();

        /*Create Token*/
        $token = $user->createToken(
            $credentials['device_name'] ?? 'api'
        )->plainTextToken;

        /*Response*/
        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'organization_id' => $user->organization_id,
            ],
        ]);
    }

    /*Logout*/
    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /*Current User*/
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()
                    ->pluck('name'),
                'organization_id' => $user->organization_id,
                'organization' => $user->organization,
            ],
        ]);
    }
}