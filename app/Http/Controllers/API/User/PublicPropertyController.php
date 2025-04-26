<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Room;
use Illuminate\Http\Request;

class PublicPropertyController extends Controller
{
    // List properties with optional filters
    public function index(Request $request)
    {
        $query = Property::with('photos');

        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        $properties = $query->paginate(10);

        return response()->json([
            'message' => 'Properties retrieved successfully',
            'data' => $properties
        ]);
    }

    // Show detail property + list rooms
    public function show($id)
    {
        $property = Property::with(['photos', 'rooms'])->findOrFail($id);

        return response()->json([
            'message' => 'Property detail retrieved successfully',
            'data' => $property
        ]);
    }

    // Show detail room + availability
    public function roomDetail($roomId)
    {
        $room = Room::with([
            'property',
            'availabilities' => function ($query) {
                $query->where('date', '>=', now()->format('Y-m-d'))
                      ->orderBy('date');
            }
        ])->findOrFail($roomId);

        return response()->json([
            'message' => 'Room detail retrieved successfully',
            'data' => [
                'room' => $room,
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
}
