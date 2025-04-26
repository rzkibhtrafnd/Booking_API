<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class OwnerController extends Controller
{
    public function index()
    {
        $owners = User::where('role', 'owner')->get();

        return response()->json($owners);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $owner = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => 'owner',
        ]);

        return response()->json([
            'message' => 'Owner created successfully.',
            'data'    => $owner,
        ], 201);
    }

    public function show($id)
    {
        $owner = User::where('role', 'owner')->findOrFail($id);

        return response()->json($owner);
    }

    public function update(Request $request, $id)
    {
        $owner = User::where('role', 'owner')->findOrFail($id);

        $owner->update($request->only(['name', 'email']));

        return response()->json([
            'message' => 'Owner updated successfully.',
            'data'    => $owner,
        ]);
    }

    public function destroy($id)
    {
        $owner = User::where('role', 'owner')->findOrFail($id);
        $owner->delete();

        return response()->json([
            'message' => 'Owner deleted successfully.',
        ]);
    }
}
