<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Booking;
use App\Models\RoomAvailability;
use App\Models\Property;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function store(Request $request, Room $room)
    {
        $validator = validator($request->all(), $this->validationRules());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $property = $room->property;

        $availabilityStatus = $this->checkRoomAvailability($room, $request);
        if ($availabilityStatus !== true) {
            return $availabilityStatus;
        }

        $totalPrice = $this->calculateTotalPrice($room, $request);

        try {
            DB::beginTransaction();

            $booking = $this->createBooking($request, $room, $property, $totalPrice);
            $this->updateAvailabilitiesForBooking(
                $room->id,
                $request->check_in,
                $request->check_out,
                $request->quantity
            );

            DB::commit();

            return response()->json([
                'message' => 'Booking created successfully',
                'data' => $booking->load('room', 'property')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Booking failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function validationRules()
    {
        return [
            'check_in'    => 'required|date|after_or_equal:today',
            'check_out'   => 'required|date|after:check_in',
            'guest_count' => 'required|integer|min:1',
            'quantity'    => 'required|integer|min:1|max:10'
        ];
    }

    protected function checkRoomAvailability(Room $room, Request $request)
    {
        $start = Carbon::parse($request->check_in);
        $end = Carbon::parse($request->check_out)->subDay();
        $period = CarbonPeriod::create($start, $end);

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

            if ($actualAvailable < $request->quantity) {
                return response()->json([
                    'message' => 'Not enough rooms available',
                    'details' => [
                        'date' => $dateStr,
                        'available' => $actualAvailable,
                        'requested' => $request->quantity
                    ]
                ], 400);
            }
        }

        return true;
    }

    protected function calculateTotalPrice(Room $room, Request $request)
    {
        $start = Carbon::parse($request->check_in);
        $end = Carbon::parse($request->check_out)->subDay();
        $period = CarbonPeriod::create($start, $end);

        $total = 0;

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $availability = RoomAvailability::where('room_id', $room->id)
                ->where('date', $dateStr)
                ->first();

            $price = $availability->custom_price ?? $room->price;
            $total += $price * $request->quantity;
        }

        return $total;
    }

    protected function createBooking(Request $request, Room $room, Property $property, $totalPrice)
    {
        return Booking::create([
            'user_id'      => auth()->id(),
            'property_id'  => $property->id,
            'room_id'      => $room->id,
            'check_in'     => $request->check_in,
            'check_out'    => $request->check_out,
            'guest_count'  => $request->guest_count,
            'quantity'     => $request->quantity,
            'total_price'  => $totalPrice,
            'status'       => 'confirmed'
        ]);
    }

    protected function updateAvailabilitiesForBooking(int $roomId, string $checkIn, string $checkOut, int $quantity)
    {
        $start = Carbon::parse($checkIn);
        $end = Carbon::parse($checkOut)->subDay();
        $period = CarbonPeriod::create($start, $end);

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');

            RoomAvailability::updateOrCreate(
                ['room_id' => $roomId, 'date' => $dateStr],
                ['booked_quantity' => DB::raw("booked_quantity + $quantity")]
            );

            $this->updateAvailabilityStatus($roomId, $dateStr);
        }
    }

    protected function updateAvailabilityStatus(int $roomId, string $date)
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
