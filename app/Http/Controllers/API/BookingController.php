<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonPeriod;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'property_id' => 'required|exists:properties,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'guest_count' => 'required|integer|min:1',
            'quantity' => 'required|integer|min:1'
        ]);

        $room = Room::findOrFail($request->room_id);

        // Check availability
        $availability = $room->getAvailableRooms(
            $request->check_in,
            $request->check_out
        );

        foreach ($availability as $date => $data) {
            if ($data['available'] < $request->quantity) {
                return response()->json([
                    'message' => 'Room not available for selected dates',
                    'conflict_date' => $date,
                    'available_quantity' => $data['available'],
                    'requested_quantity' => $request->quantity
                ], 400);
            }
        }

        try {
            DB::beginTransaction();

            // Create booking
            $booking = Booking::create([
                'user_id' => auth()->id(),
                'property_id' => $request->property_id,
                'room_id' => $request->room_id,
                'check_in' => $request->check_in,
                'check_out' => $request->check_out,
                'guest_count' => $request->guest_count,
                'quantity' => $request->quantity,
                'total_price' => $this->calculateTotalPrice($room, $request),
                'status' => 'confirmed'
            ]);

            // Update availabilities
            $this->updateAvailabilitiesForBooking(
                $room->id,
                $request->check_in,
                $request->check_out,
                $request->quantity
            );

            DB::commit();

            return response()->json([
                'message' => 'Booking created successfully',
                'booking' => $booking
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create booking',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function calculateTotalPrice($room, $request)
    {
        $period = CarbonPeriod::create(
            $request->check_in,
            $request->check_out
        )->toArray();

        $total = 0;

        foreach ($period as $date) {
            $availability = RoomAvailability::where('room_id', $room->id)
                ->where('date', $date->format('Y-m-d'))
                ->first();

            $price = $availability->custom_price ?? $room->price;
            $total += $price * $request->quantity;
        }

        return $total;
    }

    protected function updateAvailabilitiesForBooking($roomId, $checkIn, $checkOut, $quantity)
    {
        $period = CarbonPeriod::create($checkIn, $checkOut);

        foreach ($period as $date) {
            RoomAvailability::updateOrCreate(
                ['room_id' => $roomId, 'date' => $date],
                ['booked_quantity' => DB::raw("booked_quantity + $quantity")]
            );

            // Update availability status if needed
            $this->updateAvailabilityStatus($roomId, $date);
        }
    }

    protected function updateAvailabilityStatus($roomId, $date)
    {
        $room = Room::find($roomId);
        $availability = RoomAvailability::where('room_id', $roomId)
            ->where('date', $date)
            ->first();

        if ($availability && $availability->booked_quantity >= $room->stock) {
            $availability->update(['available' => false]);
        }
    }
}