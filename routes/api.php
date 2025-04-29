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

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    // ROUTE UNTUK ADMIN
    Route::prefix('admin')->middleware('role:admin')->name('admin.')->group(function () {
        Route::get('/dashboard', fn() => response()->json(['message' => 'Welcome, Admin']))->name('dashboard');
        Route::apiResource('/owners', OwnerController::class)->names('owners');
    });

    // ROUTE UNTUK OWNER
    Route::prefix('owner')->middleware('role:owner')->name('owner.')->group(function () {
        Route::get('/dashboard', fn() => response()->json(['message' => 'Welcome, Owner']))->name('dashboard');

        Route::apiResource('/properties', PropertyController::class)->names('properties');

        Route::prefix('/properties/{property}')->group(function () {
            Route::apiResource('/rooms', RoomController::class)->names('rooms');

            Route::get('/rooms/{room}/availability', [RoomController::class, 'checkAvailability'])
                ->whereNumber(['property', 'room']);
        });

        Route::get('/bookings', [BookingController::class, 'ownerBookings']);
        Route::get('/bookings/{booking}', [BookingController::class, 'ownerBookingDetail']);
        Route::post('/bookings/{booking}/status', [BookingController::class, 'updateStatus']);

        Route::get('/payments', [PaymentController::class, 'ownerIndex']);
        Route::get('/payments/{payment}', [PaymentController::class, 'ownerShow']);
        Route::post('/payments/{payment}/status', [PaymentController::class, 'ownerUpdateStatus']);
    });

    // ROUTE UNTUK USER
    Route::prefix('user')->middleware('role:user')->name('user.')->group(function () {
        Route::get('/dashboard', fn() => response()->json(['message' => 'Welcome, User']))->name('dashboard');
        Route::get('/profile', fn(Request $request) => $request->user())->name('profile');

        Route::get('/properties', [PublicPropertyController::class, 'index']);
        Route::get('/properties/{property}', [PublicPropertyController::class, 'show']);

        Route::prefix('/properties/{property}/rooms')->group(function () {
            Route::get('/', [PublicPropertyController::class, 'propertyRooms']);
            Route::get('/{room}', [PublicPropertyController::class, 'roomDetail'])->whereNumber(['property', 'room']);
            Route::post('/{room}/bookings', [BookingController::class, 'store']);
        });

        Route::get('/bookings', [BookingController::class, 'userBookings']);
        Route::get('/bookings/{booking}', [BookingController::class, 'userBookingDetail']);

        Route::get('/payments', [PaymentController::class, 'userIndex']);
        
        Route::prefix('/bookings/{booking}')->group(function () {
            Route::post('/payments', [PaymentController::class, 'userStore']);
            Route::get('/payments/{payment}', [PaymentController::class, 'userShow']);
        });
    });
});
