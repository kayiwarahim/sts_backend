<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/', function () {
    return redirect('/up');
});

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Common Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:Super Admin')
        ->prefix('admin')
        ->group(function () {

            Route::get('/dashboard', function () {
                return view('admin.dashboard');
            })->name('admin.dashboard');

        });

    /*
    |--------------------------------------------------------------------------
    | Landlord
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'role:Landlord',
        'organization',
    ])
        ->prefix('landlord')
        ->group(function () {

            Route::get('/dashboard', function () {
                return view('landlord.dashboard');
            })->name('landlord.dashboard');

        });

    /*
    |--------------------------------------------------------------------------
    | Property Manager
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'role:Property Manager',
        'organization',
    ])
        ->prefix('manager')
        ->group(function () {

            Route::get('/dashboard', function () {
                return view('manager.dashboard');
            })->name('manager.dashboard');

        });

    /*
    |--------------------------------------------------------------------------
    | Staff
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'role:Staff',
        'organization',
    ])
        ->prefix('staff')
        ->group(function () {

            Route::get('/dashboard', function () {
                return view('staff.dashboard');
            })->name('staff.dashboard');

        });

    /*
    |--------------------------------------------------------------------------
    | Finance
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'role:Finance',
        'organization',
    ])
        ->prefix('finance')
        ->group(function () {

            Route::get('/dashboard', function () {
                return view('finance.dashboard');
            })->name('finance.dashboard');

        });

    /*
    |--------------------------------------------------------------------------
    | Support
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'role:Support',
        'organization',
    ])
        ->prefix('support')
        ->group(function () {

            Route::get('/dashboard', function () {
                return view('support.dashboard');
            })->name('support.dashboard');

        });

    /*
    |--------------------------------------------------------------------------
    | Tenant
    |--------------------------------------------------------------------------
    */

    Route::middleware([
        'role:Tenant',
        'organization',
    ])
        ->prefix('tenant')
        ->group(function () {

            Route::get('/dashboard', function () {
                return view('tenant.dashboard');
            })->name('tenant.dashboard');

        });

});
