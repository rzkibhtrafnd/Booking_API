<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\RoomResource;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicPropertyController extends Controller
{
    // Menampilkan daftar properti dengan filter opsional
    public function index(Request $request)
    {
        $query = Property::with('photos');

        // Filter berdasarkan kota
        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        // Filter berdasarkan jenis properti
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter berdasarkan nama properti
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Paginasi properti
        $properties = $query->paginate(10);

        return response()->json([
            'message' => 'Properti berhasil diambil',
            'data' => PropertyResource::collection($properties),
        ]);
    }

    // Menampilkan detail properti beserta daftar kamar
    public function show($id)
    {
        $property = Property::with(['photos', 'rooms'])->findOrFail($id);

        return response()->json([
            'message' => 'Detail properti berhasil diambil',
            'data' => new PropertyResource($property),
        ]);
    }

    // Menampilkan detail kamar beserta ketersediaan
    public function roomDetail(Property $property, Room $room)
    {
        // Validasi apakah kamar milik properti
        if ($room->property_id !== $property->id) {
            throw ValidationException::withMessages([
                'room' => ['Kamar tidak ditemukan di properti ini.'],
            ]);
        }

        $room->load([
            'property',
            'availabilities' => function ($query) {
                $query->where('date', '>=', now()->format('Y-m-d'))
                      ->orderBy('date');
            }
        ]);

        return response()->json([
            'message' => 'Detail kamar berhasil diambil',
            'data' => [
                'room' => new RoomResource($room),
                'booking_info' => [
                    'min_date' => now()->format('Y-m-d'),
                    'max_quantity' => $room->stock,
                    'price_range' => [
                        'default' => $room->price,
                        'custom' => $room->availabilities->pluck('custom_price', 'date')
                    ]
                ]
            ]
        ]);
    }

    // Menampilkan daftar kamar untuk sebuah properti
    public function propertyRooms(Property $property)
    {
        $rooms = $property->rooms()->with('availabilities')->get();

        return response()->json([
            'message' => 'Kamar berhasil diambil',
            'data' => RoomResource::collection($rooms),
        ]);
    }
}