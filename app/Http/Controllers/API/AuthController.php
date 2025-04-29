<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Pendaftaran hanya untuk role = user
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|confirmed|min:8',
        ], [
            'name.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password harus minimal 8 karakter.',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => 'user',
        ]);

        // Buat token untuk user
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Pendaftaran berhasil.',
            'data'    => new UserResource($user),
            'token'   => $token,
        ], 201);
    }

    // Login untuk semua role
    public function login(Request $request)
    {
        // Pembatasan percobaan login
        if (RateLimiter::tooManyAttempts('login:' . $request->ip(), 5)) {
            throw ValidationException::withMessages([
                'email' => ['Terlalu banyak percobaan login. Coba lagi dalam beberapa menit.'],
            ]);
        }

        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit('login:' . $request->ip(), 60);
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan salah.'],
            ]);
        }

        RateLimiter::clear('login:' . $request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => new UserResource($user),
            'token'   => $user->createToken('api_token')->plainTextToken,
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        // Hapus token saat ini
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ]);
    }
}