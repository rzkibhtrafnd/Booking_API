<?php

namespace App\Http\Controllers\API\Owner;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\Booking;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::with(['property', 'availabilities'])
            ->whereHas('property', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->get();

        return response()->json($rooms);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'capacity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'facilities' => 'nullable|array',
            'stock' => 'required|integer|min:1',
            'availabilities' => 'nullable|array',
            'availabilities.*.date' => 'required|date|after_or_equal:today',
            'availabilities.*.custom_price' => 'nullable|numeric|min:0',
        ]);

        $property = Property::where('user_id', auth()->id())
            ->find($request->property_id);

        if (!$property) {
            return response()->json([
                'message' => 'Property not found or not owned by you'
            ], 403);
        }

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $room = Room::create([
                'property_id' => $request->property_id,
                'name' => $request->name,
                'description' => $request->description,
                'capacity' => $request->capacity,
                'price' => $request->price,
                'facilities' => $request->facilities,
                'stock' => $request->stock,
            ]);

            if ($request->has('availabilities')) {
                $this->updateRoomAvailabilities($room->id, $request->availabilities);
            }

            DB::commit();

            return response()->json([
                'message' => 'Room created successfully',
                'room' => $room->load('availabilities', 'property')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create room',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $room = Room::with(['property', 'availabilities'])
            ->whereHas('property', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->findOrFail($id);

        return response()->json($room);
    }

    public function update(Request $request, $id)
    {
        $room = Room::whereHas('property', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'capacity' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'facilities' => 'nullable|array',
            'stock' => 'required|integer|min:1',
            'availabilities' => 'nullable|array',
            'availabilities.*.date' => 'required|date|after_or_equal:today',
            'availabilities.*.custom_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $room->update([
                'name' => $request->name,
                'description' => $request->description,
                'capacity' => $request->capacity,
                'price' => $request->price,
                'facilities' => $request->facilities,
                'stock' => $request->stock,
            ]);

            if ($request->has('availabilities')) {
                $this->updateRoomAvailabilities($room->id, $request->availabilities);
            }

            DB::commit();

            return response()->json([
                'message' => 'Room updated successfully',
                'room' => $room->load('availabilities', 'property')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update room',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $room = Room::whereHas('property', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->findOrFail($id);

        $room->delete();

        return response()->json(['message' => 'Room deleted successfully']);
    }

    public function checkAvailability(Request $request, $id)
    {
        $room = Room::whereHas('property', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->findOrFail($id);

        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'quantity' => 'nullable|integer|min:1'
        ]);

        $start = Carbon::parse($request->start_date);
        $end = Carbon::parse($request->end_date);
        $period = CarbonPeriod::create($start, $end->subDay());

        $availabilityData = [];
        $isAvailable = true;
        $quantity = $request->quantity ?? 1;

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');

            $availability = RoomAvailability::firstOrNew([
                'room_id' => $room->id,
                'date' => $dateStr
            ]);

            $releasedQuantity = Booking::where('room_id', $room->id)
                ->where('check_out', $dateStr)
                ->where('status', 'confirmed')
                ->sum('quantity');

            $actualAvailable = $room->stock
                - ($availability->booked_quantity ?? 0)
                + $releasedQuantity;

            $availabilityData[$dateStr] = [
                'available' => $actualAvailable,
                'custom_price' => $availability->custom_price ?? null
            ];

            if ($actualAvailable < $quantity) {
                $isAvailable = false;
            }
        }

        return response()->json([
            'is_available' => $isAvailable,
            'availability' => $availabilityData,
            'required_quantity' => $quantity,
            'room_stock' => $room->stock
        ]);
    }

    protected function updateRoomAvailabilities($roomId, $availabilities)
    {
        foreach ($availabilities as $availability) {
            RoomAvailability::updateOrCreate(
                [
                    'room_id' => $roomId,
                    'date' => $availability['date']
                ],
                [
                    'custom_price' => $availability['custom_price'] ?? null,
                    'available' => true
                ]
            );
        }
    }
}
