<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class OwnerController extends Controller
{
    // Menampilkan daftar pemilik
    public function index()
    {
        $owners = User::where('role', 'owner')->select('id', 'name', 'email', 'created_at')->get();

        return response()->json([
            'data' => $owners
        ]);
    }

    // Menyimpan pemilik baru
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
            'message' => 'Pemilik berhasil dibuat.',
            'data'    => [
                'id'         => $owner->id,
                'name'       => $owner->name,
                'email'      => $owner->email,
                'created_at' => $owner->created_at,
            ]
        ], 201);
    }

    // Menampilkan detail pemilik
    public function show($id)
    {
        $owner = User::where('role', 'owner')
                     ->select('id', 'name', 'email', 'created_at')
                     ->findOrFail($id);

        return response()->json([
            'data' => $owner
        ]);
    }

    // Mengupdate data pemilik
    public function update(Request $request, $id)
    {
        $owner = User::where('role', 'owner')->findOrFail($id);
    
        $request->validate([
            'name'     => 'sometimes|required|string|max:255',
            'email'    => 'sometimes|required|email|unique:users,email,' . $owner->id,
            'password' => 'sometimes|nullable|string|min:6',
        ]);
    
        $owner->name  = $request->input('name', $owner->name);
        $owner->email = $request->input('email', $owner->email);
    
        if ($request->filled('password')) {
            $owner->password = Hash::make($request->password);
        }
    
        $owner->save();
    
        return response()->json([
            'message' => 'Pemilik berhasil diperbarui.',
            'data'    => [
                'id'    => $owner->id,
                'name'  => $owner->name,
                'email' => $owner->email,
            ]
        ]);
    }
    

    // Menghapus pemilik
    public function destroy($id)
    {
        $owner = User::where('role', 'owner')->findOrFail($id);
        $owner->delete();

        return response()->json([
            'message' => 'Pemilik berhasil dihapus.'
        ]);
    }
}