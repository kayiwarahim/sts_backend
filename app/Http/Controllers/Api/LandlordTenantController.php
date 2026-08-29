<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class LandlordTenantController extends Controller
{
    /**
     * List tenants belonging to the authenticated landlord's organization.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $user =
            $request->user();

        abort_unless(
            $user->organization_id,
            403,
            'Your account is not linked to an organization.'
        );

        $query =
            Tenant::query()
                ->where(
                    'organization_id',
                    $user->organization_id
                )
                ->with([
                    'user:id,name,email,is_active,last_login_at',
                ])
                ->latest();

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

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
                            'first_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'last_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'email',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'phone',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Status filter
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled(
                'status'
            )
        ) {
            $query->where(
                'status',
                $request->input(
                    'status'
                )
            );
        }

        $tenants =
            $query->paginate(
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

            'data' => $tenants,
        ]);
    }

    /**
     * Register a tenant and create their login account.
     */
    public function store(
        Request $request
    ): JsonResponse {
        $landlord =
            $request->user();

        abort_unless(
            $landlord->organization_id,
            403,
            'Your account is not linked to an organization.'
        );

        $validated =
            $request->validate([
                'first_name' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'last_name' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'email' => [
                    'required',
                    'email',
                    'max:255',
                    'unique:users,email',
                    'unique:tenants,email',
                ],

                'phone' => [
                    'required',
                    'string',
                    'max:30',
                ],

                'national_id' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'status' => [
                    'nullable',
                    Rule::in([
                        'active',
                        'inactive',
                    ]),
                ],
            ]);

        try {

            $tenant =
                DB::transaction(
                    function () use (
                        $validated,
                        $landlord,
                        $request
                    ) {
                        /*
                        |--------------------------------------------------------------------------
                        | Create user
                        |--------------------------------------------------------------------------
                        |
                        | Random password is never shown to landlord or tenant.
                        | Tenant must choose their password from the emailed link.
                        |--------------------------------------------------------------------------
                        */

                        $temporaryPassword =
                            Str::password(
                                40
                            );

                        $fullName =
                            trim(
                                $validated[
                                    'first_name'
                                ].
                                ' '.
                                $validated[
                                    'last_name'
                                ]
                            );

                        $user =
                            User::create([
                                'organization_id' => $landlord
                                    ->organization_id,

                                'name' => $fullName,

                                'email' => strtolower(
                                    $validated[
                                        'email'
                                    ]
                                ),

                                'password' => Hash::make(
                                    $temporaryPassword
                                ),

                                'is_active' => (
                                    $validated[
                                        'status'
                                    ]
                                    ?? 'active'
                                ) === 'active',
                            ]);

                        /*
                        |--------------------------------------------------------------------------
                        | Role is fixed
                        |--------------------------------------------------------------------------
                        */

                        $user->assignRole(
                            'Tenant'
                        );

                        /*
                        |--------------------------------------------------------------------------
                        | Create tenant profile
                        |--------------------------------------------------------------------------
                        */

                        $tenant =
                            Tenant::create([
                                'organization_id' => $landlord
                                    ->organization_id,

                                'user_id' => $user->id,

                                'first_name' => $validated[
                                        'first_name'
                                    ],

                                'last_name' => $validated[
                                        'last_name'
                                    ],

                                'email' => strtolower(
                                    $validated[
                                        'email'
                                    ]
                                ),

                                'phone' => $validated[
                                        'phone'
                                    ],

                                'national_id' => $validated[
                                        'national_id'
                                    ]
                                    ?? null,

                                'status' => $validated[
                                        'status'
                                    ]
                                    ?? 'active',
                            ]);

                        /*
                        |--------------------------------------------------------------------------
                        | Audit trail
                        |--------------------------------------------------------------------------
                        */

                        AuditLog::create([
                            'user_id' => $landlord->id,

                            'organization_id' => $landlord
                                ->organization_id,

                            'action' => 'tenant_registered',

                            'auditable_type' => Tenant::class,

                            'auditable_id' => $tenant->id,

                            'old_values' => null,

                            'new_values' => [
                                'tenant_id' => $tenant->id,

                                'user_id' => $user->id,

                                'name' => $fullName,

                                'email' => $tenant->email,

                                'phone' => $tenant->phone,

                                'organization_id' => $tenant
                                    ->organization_id,

                                'role' => 'Tenant',
                            ],

                            'ip_address' => $request->ip(),

                            'user_agent' => $request
                                ->userAgent(),

                            'description' => "Landlord registered tenant {$tenant->email}.",
                        ]);

                        return $tenant;
                    }
                );

            /*
            |--------------------------------------------------------------------------
            | Send password setup email after DB commit
            |--------------------------------------------------------------------------
            */

            $passwordStatus =
                Password::sendResetLink([
                    'email' => $tenant->email,
                ]);

            $mailSent =
                $passwordStatus ===
                Password::RESET_LINK_SENT;

            return response()->json([
                'success' => true,

                'message' => $mailSent
                        ? 'Tenant registered successfully. A password setup link has been sent to the tenant.'
                        : 'Tenant registered successfully, but the password setup email could not be sent. You can resend it later.',

                'data' => $tenant->load([
                    'user:id,name,email,is_active,last_login_at',
                ]),

                'password_setup_sent' => $mailSent,
            ], 201);

        } catch (Throwable $exception) {

            report(
                $exception
            );

            return response()->json([
                'success' => false,

                'message' => 'Tenant registration could not be completed.',
            ], 500);
        }
    }

    /**
     * Show one tenant belonging to landlord organization.
     */
    public function show(
        Request $request,
        Tenant $tenant
    ): JsonResponse {
        $this->authorizeTenant(
            $request,
            $tenant
        );

        return response()->json([
            'success' => true,

            'data' => $tenant->load([
                'user:id,name,email,is_active,last_login_at',
            ]),
        ]);
    }

    /**
     * Update tenant profile and linked user.
     */
    public function update(
        Request $request,
        Tenant $tenant
    ): JsonResponse {
        $this->authorizeTenant(
            $request,
            $tenant
        );

        $validated =
            $request->validate([
                'first_name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:100',
                ],

                'last_name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:100',
                ],

                'email' => [
                    'sometimes',
                    'required',
                    'email',
                    'max:255',

                    Rule::unique(
                        'tenants',
                        'email'
                    )->ignore(
                        $tenant->id
                    ),

                    Rule::unique(
                        'users',
                        'email'
                    )->ignore(
                        $tenant->user_id
                    ),
                ],

                'phone' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:30',
                ],

                'national_id' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'status' => [
                    'sometimes',

                    Rule::in([
                        'active',
                        'inactive',
                    ]),
                ],
            ]);

        $oldValues =
            $tenant->only([
                'first_name',
                'last_name',
                'email',
                'phone',
                'national_id',
                'status',
            ]);

        DB::transaction(
            function () use (
                $tenant,
                $validated
            ) {
                $tenant->update(
                    $validated
                );

                if (
                    $tenant->user
                ) {
                    $tenant->user
                        ->update([
                            'name' => trim(
                                $tenant
                                    ->first_name.
                                ' '.
                                $tenant
                                    ->last_name
                            ),

                            'email' => strtolower(
                                $tenant
                                    ->email
                            ),

                            'is_active' => $tenant
                                ->status ===
                                'active',
                        ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Inactive tenant loses active API sessions
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $tenant->status !==
                        'active'
                    ) {
                        $tenant
                            ->user
                            ->tokens()
                            ->delete();
                    }
                }
            }
        );

        $tenant->refresh();

        AuditLog::create([
            'user_id' => $request
                ->user()
                ->id,

            'organization_id' => $tenant
                ->organization_id,

            'action' => 'tenant_updated',

            'auditable_type' => Tenant::class,

            'auditable_id' => $tenant->id,

            'old_values' => $oldValues,

            'new_values' => $tenant->only([
                'first_name',
                'last_name',
                'email',
                'phone',
                'national_id',
                'status',
            ]),

            'ip_address' => $request->ip(),

            'user_agent' => $request
                ->userAgent(),

            'description' => "Landlord updated tenant {$tenant->email}.",
        ]);

        return response()->json([
            'success' => true,

            'message' => 'Tenant updated successfully.',

            'data' => $tenant->load([
                'user:id,name,email,is_active,last_login_at',
            ]),
        ]);
    }

    /**
     * Resend tenant password setup/reset link.
     */
    public function resendPasswordSetup(
        Request $request,
        Tenant $tenant
    ): JsonResponse {
        $this->authorizeTenant(
            $request,
            $tenant
        );

        abort_unless(
            $tenant->user,
            422,
            'This tenant does not have a linked user account.'
        );

        $status =
            Password::sendResetLink([
                'email' => $tenant->user
                    ->email,
            ]);

        if (
            $status !==
            Password::RESET_LINK_SENT
        ) {
            return response()->json([
                'success' => false,

                'message' => __($status),
            ], 422);
        }

        AuditLog::create([
            'user_id' => $request
                ->user()
                ->id,

            'organization_id' => $tenant
                ->organization_id,

            'action' => 'tenant_password_setup_sent',

            'auditable_type' => Tenant::class,

            'auditable_id' => $tenant->id,

            'old_values' => null,

            'new_values' => [
                'email' => $tenant->email,
            ],

            'ip_address' => $request->ip(),

            'user_agent' => $request
                ->userAgent(),

            'description' => "Password setup link resent to tenant {$tenant->email}.",
        ]);

        return response()->json([
            'success' => true,

            'message' => 'Password setup/reset link sent successfully.',
        ]);
    }

    /**
     * Deactivate tenant.
     *
     * We deliberately do not physically delete the record because historical
     * payments, vending, meters and audit records may reference this tenant.
     */
    public function destroy(
        Request $request,
        Tenant $tenant
    ): JsonResponse {
        $this->authorizeTenant(
            $request,
            $tenant
        );

        DB::transaction(
            function () use (
                $tenant
            ) {
                $tenant->update([
                    'status' => 'inactive',
                ]);

                if (
                    $tenant->user
                ) {
                    $tenant
                        ->user
                        ->update([
                            'is_active' => false,
                        ]);

                    $tenant
                        ->user
                        ->tokens()
                        ->delete();
                }
            }
        );

        AuditLog::create([
            'user_id' => $request
                ->user()
                ->id,

            'organization_id' => $tenant
                ->organization_id,

            'action' => 'tenant_deactivated',

            'auditable_type' => Tenant::class,

            'auditable_id' => $tenant->id,

            'old_values' => [
                'status' => 'active',
            ],

            'new_values' => [
                'status' => 'inactive',
            ],

            'ip_address' => $request->ip(),

            'user_agent' => $request
                ->userAgent(),

            'description' => "Landlord deactivated tenant {$tenant->email}.",
        ]);

        return response()->json([
            'success' => true,

            'message' => 'Tenant deactivated successfully.',
        ]);
    }

    /**
     * Organization ownership guard.
     */
    protected function authorizeTenant(
        Request $request,
        Tenant $tenant
    ): void {
        $landlord =
            $request->user();

        abort_unless(
            $landlord->organization_id
            &&
            (int)
            $tenant->organization_id ===
            (int)
            $landlord->organization_id,
            403,
            'Unauthorized tenant access.'
        );
    }
}
