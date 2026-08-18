<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        $query =
            User::query()
                ->with([
                    'roles:id,name',
                    'organization:id,name',
                ]);

        if (
            $request->filled(
                'search'
            )
        ) {
            $search =
                trim(
                    $request->input(
                        'search'
                    )
                );

            $query->where(
                function ($q) use (
                    $search
                ) {
                    $q
                        ->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'email',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if (
            $request->filled(
                'role'
            )
        ) {
            $query->role(
                $request->input(
                    'role'
                )
            );
        }

        if (
            $request->filled(
                'organization_id'
            )
        ) {
            $query->where(
                'organization_id',
                $request->input(
                    'organization_id'
                )
            );
        }

        $users =
            $query
                ->latest()
                ->paginate(
                    min(
                        max(
                            (int)
                            $request->input(
                                'per_page',
                                20
                            ),
                            1
                        ),
                        100
                    )
                );

        return response()->json([
            'success' => true,

            'data' => $users,
        ]);
    }

    public function meta(): JsonResponse
    {
        return response()->json([
            'success' => true,

            'data' => [
                'roles' =>
                    Role::query()
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                        ]),

                'organizations' =>
                    Organization::query()
                        ->where(
                            'status',
                            'active'
                        )
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                        ]),
            ],
        ]);
    }

    public function store(
        Request $request
    ): JsonResponse {
        $validated =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'email' => [
                    'required',
                    'email',
                    'max:255',
                    'unique:users,email',
                ],

                'organization_id' => [
                    'nullable',
                    'exists:organizations,id',
                ],

                'role' => [
                    'required',
                    'string',
                    Rule::exists(
                        'roles',
                        'name'
                    ),
                ],

                'is_active' => [
                    'nullable',
                    'boolean',
                ],
            ]);

        $roleName =
            $validated['role'];

        /*
        |--------------------------------------------------------------------------
        | Super Admin should not belong to an organization
        |--------------------------------------------------------------------------
        */

        if (
            $roleName ===
            'Super Admin'
        ) {
            $validated[
                'organization_id'
            ] = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Organization-required roles
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $roleName,
                [
                    'Landlord',
                    'Tenant',
                    'Manager',
                    'Staff',
                    'Finance',
                    'Support',
                ],
                true
            )
            &&
            empty(
                $validated[
                    'organization_id'
                ]
            )
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'An organization is required for this role.',
            ], 422);
        }

        $user =
            DB::transaction(
                function () use (
                    $validated,
                    $request
                ) {
                    $temporaryPassword =
                        Str::password(
                            32
                        );

                    $user =
                        User::create([
                            'organization_id' =>
                                $validated[
                                    'organization_id'
                                ]
                                ?? null,

                            'name' =>
                                $validated[
                                    'name'
                                ],

                            'email' =>
                                strtolower(
                                    $validated[
                                        'email'
                                    ]
                                ),

                            'password' =>
                                Hash::make(
                                    $temporaryPassword
                                ),

                            'is_active' =>
                                $validated[
                                    'is_active'
                                ]
                                ?? true,
                        ]);

                    $user->syncRoles([
                        $validated[
                            'role'
                        ],
                    ]);

                    AuditLog::create([
                        'user_id' =>
                            $request
                                ->user()
                                ->id,

                        'organization_id' =>
                            $user
                                ->organization_id,

                        'action' =>
                            'user_created',

                        'auditable_type' =>
                            User::class,

                        'auditable_id' =>
                            $user->id,

                        'old_values' =>
                            null,

                        'new_values' => [
                            'name' =>
                                $user->name,

                            'email' =>
                                $user->email,

                            'organization_id' =>
                                $user
                                    ->organization_id,

                            'role' =>
                                $validated[
                                    'role'
                                ],

                            'is_active' =>
                                $user
                                    ->is_active,
                        ],

                        'ip_address' =>
                            $request
                                ->ip(),

                        'user_agent' =>
                            $request
                                ->userAgent(),

                        'description' =>
                            "Created user {$user->email}.",
                    ]);

                    return $user;
                }
            );

        /*
        |--------------------------------------------------------------------------
        | Send password setup/reset link
        |--------------------------------------------------------------------------
        */

        Password::sendResetLink([
            'email' =>
                $user->email,
        ]);

        return response()->json([
            'success' =>
                true,

            'message' =>
                'User created successfully. A password setup link has been sent to the user.',

            'data' =>
                $user->load([
                    'roles:id,name',
                    'organization:id,name',
                ]),
        ], 201);
    }

    public function show(
        User $user
    ): JsonResponse {
        return response()->json([
            'success' => true,

            'data' =>
                $user->load([
                    'roles:id,name',
                    'organization:id,name',
                ]),
        ]);
    }

    public function update(
        Request $request,
        User $user
    ): JsonResponse {
        $validated =
            $request->validate([
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                ],

                'email' => [
                    'sometimes',
                    'required',
                    'email',
                    'max:255',

                    Rule::unique(
                        'users',
                        'email'
                    )->ignore(
                        $user->id
                    ),
                ],

                'organization_id' => [
                    'nullable',
                    'exists:organizations,id',
                ],

                'role' => [
                    'sometimes',
                    'required',
                    'string',

                    Rule::exists(
                        'roles',
                        'name'
                    ),
                ],

                'is_active' => [
                    'sometimes',
                    'boolean',
                ],
            ]);

        /*
        |--------------------------------------------------------------------------
        | Prevent accidental self lockout
        |--------------------------------------------------------------------------
        */

        if (
            $request
                ->user()
                ->id ===
                $user->id
            &&
            array_key_exists(
                'is_active',
                $validated
            )
            &&
            $validated[
                'is_active'
            ] === false
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'You cannot deactivate your own Super Admin account.',
            ], 422);
        }

        $oldValues = [
            'name' =>
                $user->name,

            'email' =>
                $user->email,

            'organization_id' =>
                $user
                    ->organization_id,

            'roles' =>
                $user
                    ->roles
                    ->pluck('name')
                    ->values()
                    ->all(),

            'is_active' =>
                $user
                    ->is_active,
        ];

        DB::transaction(
            function () use (
                $validated,
                $user
            ) {
                $roleName =
                    $validated[
                        'role'
                    ]
                    ??
                    $user
                        ->roles
                        ->first()
                        ?->name;

                if (
                    $roleName ===
                    'Super Admin'
                ) {
                    $validated[
                        'organization_id'
                    ] = null;
                }

                $updateData =
                    collect(
                        $validated
                    )
                        ->except(
                            'role'
                        )
                        ->toArray();

                if (
                    isset(
                        $updateData[
                            'email'
                        ]
                    )
                ) {
                    $updateData[
                        'email'
                    ] =
                        strtolower(
                            $updateData[
                                'email'
                            ]
                        );
                }

                $user->update(
                    $updateData
                );

                if (
                    isset(
                        $validated[
                            'role'
                        ]
                    )
                ) {
                    $user->syncRoles([
                        $validated[
                            'role'
                        ],
                    ]);
                }
            }
        );

        $user->refresh();

        AuditLog::create([
            'user_id' =>
                $request
                    ->user()
                    ->id,

            'organization_id' =>
                $user
                    ->organization_id,

            'action' =>
                'user_updated',

            'auditable_type' =>
                User::class,

            'auditable_id' =>
                $user->id,

            'old_values' =>
                $oldValues,

            'new_values' => [
                'name' =>
                    $user->name,

                'email' =>
                    $user->email,

                'organization_id' =>
                    $user
                        ->organization_id,

                'roles' =>
                    $user
                        ->roles()
                        ->pluck(
                            'name'
                        )
                        ->all(),

                'is_active' =>
                    $user
                        ->is_active,
            ],

            'ip_address' =>
                $request
                    ->ip(),

            'user_agent' =>
                $request
                    ->userAgent(),

            'description' =>
                "Updated user {$user->email}.",
        ]);

        return response()->json([
            'success' =>
                true,

            'message' =>
                'User updated successfully.',

            'data' =>
                $user->load([
                    'roles:id,name',
                    'organization:id,name',
                ]),
        ]);
    }

    public function resendPasswordSetup(
        Request $request,
        User $user
    ): JsonResponse {
        $status =
            Password::sendResetLink([
                'email' =>
                    $user->email,
            ]);

        if (
            $status !==
            Password::RESET_LINK_SENT
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    __($status),
            ], 422);
        }

        AuditLog::create([
            'user_id' =>
                $request
                    ->user()
                    ->id,

            'organization_id' =>
                $user
                    ->organization_id,

            'action' =>
                'password_setup_link_sent',

            'auditable_type' =>
                User::class,

            'auditable_id' =>
                $user->id,

            'old_values' =>
                null,

            'new_values' => [
                'email' =>
                    $user->email,
            ],

            'ip_address' =>
                $request
                    ->ip(),

            'user_agent' =>
                $request
                    ->userAgent(),

            'description' =>
                "Password setup link sent to {$user->email}.",
        ]);

        return response()->json([
            'success' =>
                true,

            'message' =>
                'Password setup/reset link sent successfully.',
        ]);
    }

    public function destroy(
        Request $request,
        User $user
    ): JsonResponse {
        if (
            $request
                ->user()
                ->id ===
                $user->id
        ) {
            return response()->json([
                'success' =>
                    false,

                'message' =>
                    'You cannot delete your own account.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Soft approach:
        | deactivate instead of deleting identity/history
        |--------------------------------------------------------------------------
        */

        $user->update([
            'is_active' =>
                false,
        ]);

        $user->tokens()
            ->delete();

        AuditLog::create([
            'user_id' =>
                $request
                    ->user()
                    ->id,

            'organization_id' =>
                $user
                    ->organization_id,

            'action' =>
                'user_deactivated',

            'auditable_type' =>
                User::class,

            'auditable_id' =>
                $user->id,

            'old_values' => [
                'is_active' =>
                    true,
            ],

            'new_values' => [
                'is_active' =>
                    false,
            ],

            'ip_address' =>
                $request
                    ->ip(),

            'user_agent' =>
                $request
                    ->userAgent(),

            'description' =>
                "Deactivated user {$user->email}.",
        ]);

        return response()->json([
            'success' =>
                true,

            'message' =>
                'User deactivated successfully.',
        ]);
    }
}