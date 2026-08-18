<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetController extends Controller
{
    public function forgotPassword(
        Request $request
    ): JsonResponse {
        $validated =
            $request->validate([
                'email' => [
                    'required',
                    'email',
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | Do not reveal whether account exists
        |--------------------------------------------------------------------------
        */

        $user =
            User::query()
                ->where(
                    'email',
                    $validated['email']
                )
                ->first();

        if (!$user) {
            return response()->json([
                'success' =>
                    true,

                'message' =>
                    'If an account exists for this email address, a password reset link has been sent.',
            ]);
        }

        $status =
            Password::sendResetLink([
                'email' =>
                    $validated['email'],
            ]);

        if (
            $status ===
            Password::RESET_LINK_SENT
        ) {
            return response()->json([
                'success' =>
                    true,

                'message' =>
                    'If an account exists for this email address, a password reset link has been sent.',
            ]);
        }

        return response()->json([
            'success' =>
                false,

            'message' =>
                __($status),
        ], 422);
    }

    public function resetPassword(
        Request $request
    ): JsonResponse {
        $validated =
            $request->validate([
                'token' => [
                    'required',
                    'string',
                ],

                'email' => [
                    'required',
                    'email',
                ],

                'password' => [
                    'required',
                    'confirmed',

                    PasswordRule::min(8)
                        ->letters()
                        ->mixedCase()
                        ->numbers(),
                ],

                'password_confirmation' => [
                    'required',
                    'string',
                ],
            ]);

        $status =
            Password::reset(
                [
                    'email' =>
                        $validated['email'],

                    'password' =>
                        $validated['password'],

                    'password_confirmation' =>
                        $validated['password_confirmation'],

                    'token' =>
                        $validated['token'],
                ],
                function (
                    User $user,
                    string $password
                ) {
                    $user->forceFill([
                        'password' =>
                            Hash::make(
                                $password
                            ),

                        'remember_token' =>
                            Str::random(
                                60
                            ),
                    ])->save();

                    /*
                    |--------------------------------------------------------------------------
                    | Invalidate old API tokens
                    |--------------------------------------------------------------------------
                    */

                    $user->tokens()
                        ->delete();

                    event(
                        new PasswordReset(
                            $user
                        )
                    );
                }
            );

        if (
            $status ===
            Password::PASSWORD_RESET
        ) {
            return response()->json([
                'success' =>
                    true,

                'message' =>
                    'Your password has been reset successfully. You can now log in using your new password.',
            ]);
        }

        return response()->json([
            'success' =>
                false,

            'message' =>
                __($status),
        ], 422);
    }
}