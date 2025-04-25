<?php

// app/Http/Controllers/API/AuthController.php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Register hanya untuk role = user
    public function register(Request $req)
    {
        $req->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|string|email|unique:users',
            'password'              => 'required|string|confirmed|min:8',
        ]);

        $user = User::create([
            'name'     => $req->name,
            'email'    => $req->email,
            'password' => Hash::make($req->password),
            'role'     => 'user',
        ]);

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ], 201);
    }

    // Login untuk semua role
    public function login(Request $req)
    {
        $req->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $req->email)->first();
        if (! $user || ! Hash::check($req->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $req)
    {
        $req->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}

