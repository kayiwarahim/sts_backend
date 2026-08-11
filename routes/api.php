<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LandlordRegistrationController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {

    Route::post(
        '/login',
        [AuthController::class, 'login']
    );

    Route::post(
        '/register/landlord',
        [LandlordRegistrationController::class, 'register']
    );

});


/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/me',
        [AuthController::class, 'me']
    );

    Route::post(
        '/auth/logout',
        [AuthController::class, 'logout']
    );


    /*
    |--------------------------------------------------------------------------
    | Organization Scoped Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('organization')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Properties
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/properties',
            function () {
                return response()->json([
                    'message' => 'Properties endpoint',
                ]);
            }
        )->middleware(
            'permission:properties.view'
        );


        Route::post(
            '/properties',
            function () {
                return response()->json([
                    'message' => 'Create property endpoint',
                ]);
            }
        )->middleware(
            'permission:properties.create'
        );


        /*
        |--------------------------------------------------------------------------
        | Tenants
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/tenants',
            function () {
                return response()->json([
                    'message' => 'Tenants endpoint',
                ]);
            }
        )->middleware(
            'permission:tenants.view'
        );


        /*
        |--------------------------------------------------------------------------
        | Meters
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/meters',
            function () {
                return response()->json([
                    'message' => 'Meters endpoint',
                ]);
            }
        )->middleware(
            'permission:meters.view'
        );


        /*
        |--------------------------------------------------------------------------
        | Payments
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/payments',
            function () {
                return response()->json([
                    'message' => 'Payments endpoint',
                ]);
            }
        )->middleware(
            'permission:payments.view'
        );


        /*
        |--------------------------------------------------------------------------
        | STS
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/meters/{meter}/generate-token',
            function () {
                return response()->json([
                    'message' => 'STS token generation endpoint',
                ]);
            }
        )->middleware(
            'permission:sts.generate-token'
        );


        Route::post(
            '/meters/{meter}/clear-tamper',
            function () {
                return response()->json([
                    'message' => 'STS tamper clearing endpoint',
                ]);
            }
        )->middleware(
            'permission:sts.clear-tamper'
        );


        /*
        |--------------------------------------------------------------------------
        | Landlord Withdrawals
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/withdrawals',
            function () {
                return response()->json([
                    'message' => 'Withdrawals endpoint',
                ]);
            }
        )->middleware(
            'permission:withdrawals.view'
        );


        Route::post(
            '/withdrawals',
            function () {
                return response()->json([
                    'message' => 'Create withdrawal endpoint',
                ]);
            }
        )->middleware(
            'permission:withdrawals.create'
        );

    });
});


/*
|--------------------------------------------------------------------------
| Super Admin
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'role:Super Admin',
])->prefix('admin')->group(function () {

    Route::get(
        '/dashboard',
        function () {
            return response()->json([
                'message' => 'Super Admin Dashboard',
            ]);
        }
    );

});