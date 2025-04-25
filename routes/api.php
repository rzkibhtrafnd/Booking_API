<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\OwnerController;
use App\Http\Controllers\API\Owner\PropertyController;
use App\Http\Controllers\API\Owner\RoomController;
use App\Http\Controllers\API\User\PublicPropertyController;

// 🔐 AUTH ROUTES
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// ✅ ROUTES WITH AUTHENTICATION
Route::middleware('auth:sanctum')->group(function () {

    // 🔓 Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // 🛡️ ADMIN ROUTES
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Welcome, Admin']);
        })->name('dashboard');

        Route::apiResource('owners', OwnerController::class)->names('owners');
    });

    // 🏠 OWNER ROUTES
    Route::middleware('role:owner')->prefix('owner')->name('owner.')->group(function () {
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Welcome, Owner']);
        })->name('dashboard');

        Route::apiResource('properties', PropertyController::class)->names('properties');
        Route::apiResource('rooms', RoomController::class)->names('rooms');
    });

    // 👤 USER ROUTES
    Route::middleware('role:user')->prefix('user')->name('user.')->group(function () {
        Route::get('/profile', function (Request $request) {
            return $request->user();
        })->name('profile');

        Route::get('properties', [PublicPropertyController::class, 'index']);
        Route::get('properties/{id}', [PublicPropertyController::class, 'show']);
        Route::get('rooms/{roomId}', [PublicPropertyController::class, 'roomDetail']);
    });
});
