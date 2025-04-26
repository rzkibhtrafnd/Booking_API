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
    // Fungsi untuk memesan kamar
    public function store(Request $request, Room $room)
    {
        $request->validate($this->validationRules());

        $property = $room->property;

        // Periksa ketersediaan kamar
        $availabilityStatus = $this->checkRoomAvailability($room, $request);
        if ($availabilityStatus !== true) {
            return $availabilityStatus;
        }

        // Hitung total harga
        $totalPrice = $this->calculateTotalPrice($room, $request);

        DB::beginTransaction();

        try {
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
                'booking' => $booking->load('room', 'property')
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

    // Memeriksa ketersediaan kamar (cek tanggal dari check_in s.d. sebelum check_out)
    protected function checkRoomAvailability(Room $room, Request $request)
    {
        $start = Carbon::parse($request->check_in);
        $end = Carbon::parse($request->check_out);
        
        $period = CarbonPeriod::create($start, $end->subDay()); // Hanya sampai sehari sebelum check-out
    
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            
            // Dapatkan availability atau buat baru jika belum ada
            $availability = RoomAvailability::firstOrNew([
                'room_id' => $room->id,
                'date' => $dateStr
            ]);
            
            // Hitung kamar yang akan tersedia dari booking yang check-out di tanggal ini
            $releasedQuantity = Booking::where('room_id', $room->id)
                ->where('check_out', $dateStr)
                ->where('status', 'confirmed')
                ->sum('quantity');
                
            // Ketersediaan aktual = stok - booked + yang akan dilepas
            $actualAvailable = $room->stock 
                - ($availability->booked_quantity ?? 0) 
                + $releasedQuantity;
    
            if ($actualAvailable < $request->quantity) {
                return response()->json([
                    'message' => 'Not enough rooms available on ' . $dateStr,
                    'date' => $dateStr,
                    'available' => $actualAvailable,
                    'requested' => $request->quantity
                ], 400);
            }
        }
    
        return true;
    }

    // Menghitung total harga berdasarkan jumlah kamar dan tanggal
    protected function calculateTotalPrice(Room $room, Request $request)
    {
        $start = Carbon::parse($request->check_in);
        $end   = Carbon::parse($request->check_out)->subDay();
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

    // Membuat booking baru
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

    // Memperbarui status availability kamar setelah booking
    protected function updateAvailabilitiesForBooking(int $roomId, string $checkIn, string $checkOut, int $quantity)
    {
        $start = Carbon::parse($checkIn);
        $end = Carbon::parse($checkOut)->subDay();
        $period = CarbonPeriod::create($start, $end);
    
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            
            // Update booked quantity
            RoomAvailability::updateOrCreate(
                ['room_id' => $roomId, 'date' => $dateStr],
                ['booked_quantity' => DB::raw("booked_quantity + $quantity")]
            );
            
            $this->updateAvailabilityStatus($roomId, $dateStr);
        }
    }

    // Memperbarui status ketersediaan kamar
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
