<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class LandlordRegistrationController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'organization_name' => [
                'required',
                'string',
                'max:255',
            ],

            'registration_number' => [
                'nullable',
                'string',
                'max:100',
                'unique:organizations,registration_number',
            ],

            'phone' => [
                'required',
                'string',
                'max:30',
            ],

            'address' => [
                'nullable',
                'string',
                'max:500',
            ],

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

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $result = DB::transaction(function () use ($data) {

            $organization = Organization::create([
                'name' => $data['organization_name'],
                'registration_number' => $data['registration_number'] ?? null,
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address'] ?? null,
                'status' => 'active',
            ]);

            $user = User::create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('Landlord');

            return [
                'organization' => $organization,
                'user' => $user,
            ];
        });

        Log::info(
            'New landlord registered',
            [
                'organization_id' => $result['organization']->id,
                'user_id' => $result['user']->id,
            ]
        );

        return response()->json([
            'message' => 'Landlord account created successfully.',
            'organization_id' => $result['organization']->id,
            'user' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
                'roles' => $result['user']->getRoleNames(),
            ],
        ], 201);
    }
}
