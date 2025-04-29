<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\OwnerController;
use App\Http\Controllers\API\Owner\PropertyController;
use App\Http\Controllers\API\Owner\RoomController;
use App\Http\Controllers\API\User\PublicPropertyController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\Owner\OwnerPaymentController;

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
        Route::prefix('properties/{property}')->group(function () {
            Route::apiResource('rooms', RoomController::class);
            
            Route::prefix('rooms/{room}')->group(function () {
                Route::get('availability', [RoomController::class, 'checkAvailability']);
            });
        });
        Route::get('bookings', [BookingController::class, 'ownerBookings']);
        Route::get('bookings/{booking}', [BookingController::class, 'ownerBookingDetail']);
        Route::post('bookings/{booking}/status', [BookingController::class, 'updateStatus']);
        Route::get('payments', [PaymentController::class, 'ownerIndex']);
        Route::get('payments/{payment}', [PaymentController::class, 'ownerShow']);
        Route::post('payments/{payment}/status', [PaymentController::class, 'ownerUpdateStatus']);
    });

    // 👤 USER ROUTES
    Route::middleware('role:user')->prefix('user')->name('user.')->group(function () {
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Welcome, User']);
        })->name('dashboard');
        Route::get('/profile', function (Request $request) {
            return $request->user();
        })->name('profile');
        Route::get('properties', [PublicPropertyController::class, 'index']);
        Route::get('properties/{property}', [PublicPropertyController::class, 'show']);
        Route::prefix('properties/{property}')->group(function () {
            Route::get('rooms', [PublicPropertyController::class, 'propertyRooms']);
            Route::get('rooms/{room}', [PublicPropertyController::class, 'roomDetail'])
                ->whereNumber(['property', 'room']);
          
            Route::prefix('rooms/{room}')->group(function () {
                Route::post('bookings', [BookingController::class, 'store']);
            });
        });
        Route::get('bookings', [BookingController::class, 'userBookings']);
        Route::get('bookings/{booking}', [BookingController::class, 'userBookingDetail']);
        Route::get('payments', [PaymentController::class, 'userIndex']);
        Route::prefix('bookings/{booking}')->group(function () {
            Route::post('payments', [PaymentController::class, 'userStore']);
            Route::get('payments/{payment}', [PaymentController::class, 'userShow']);
        });
    });
});
