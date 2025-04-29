<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Booking;
use App\Models\RoomAvailability;
use App\Models\Property;
use App\Models\UserProfile;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function store(Request $request, $propertyId, $roomId)
    {
        $user = Auth::user();

        // Validasi input dari pengguna
        $request->validate([
            'check_in'    => 'required|date|after_or_equal:today',
            'check_out'   => 'required|date|after:check_in',
            'guest_count' => 'required|integer|min:1',
            'quantity'    => 'required|integer|min:1|max:10',
            'nik'          => 'required|string|max:20',
            'ktp_img'      => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'address'      => 'required|string|max:255',
            'gender'       => 'required|in:L,P',
        ]);

        // Mencari room dan property berdasarkan ID
        $room = Room::where('property_id', $propertyId)->findOrFail($roomId);
        $property = $room->property;

        // Memeriksa ketersediaan kamar
        $availabilityStatus = $this->checkRoomAvailability($room, $request);
        if ($availabilityStatus !== true) {
            return $availabilityStatus;
        }

        // Menghitung total harga untuk pemesanan
        $totalPrice = $this->calculateTotalPrice($room, $request);

        try {
            DB::beginTransaction();

            // Membuat pemesanan
            $booking = $this->createBooking($request, $room, $property, $totalPrice);

            // Menyimpan gambar KTP
            $ktpPath = $request->file('ktp_img')->store('ktp_images', 'public');
            $userProfile = UserProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'nik'     => $request->nik,
                    'ktp_img' => $ktpPath,
                    'address' => $request->address,
                    'gender'  => $request->gender,
                ]
            );

            // Memperbarui ketersediaan kamar
            $this->updateAvailabilitiesForBooking(
                $room->id,
                $request->check_in,
                $request->check_out,
                $request->quantity
            );

            DB::commit();

            // Mengembalikan respons sukses
            return response()->json([
                'message' => 'Pemesanan berhasil dibuat',
                'data'    => $booking->load('room', 'property')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            // Mengembalikan respons gagal
            return response()->json([
                'message' => 'Pemesanan gagal',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // Memeriksa ketersediaan kamar
    protected function checkRoomAvailability(Room $room, Request $request)
    {
        $start = Carbon::parse($request->check_in);
        $end   = Carbon::parse($request->check_out)->subDay();
        $period = CarbonPeriod::create($start, $end);

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');

            $availability = RoomAvailability::where('room_id', $room->id)
                ->where('date', $dateStr)
                ->first();

            if (!$availability) {
                return response()->json([
                    'message' => 'Kamar tidak tersedia pada tanggal berikut',
                    'date'    => $dateStr,
                ], 400);
            }

            $bookedQty = $availability->booked_quantity;
            $remaining = $room->stock - $bookedQty;

            if ($remaining < $request->quantity) {
                return response()->json([
                    'message'   => 'Kamar tidak tersedia pada tanggal berikut',
                    'date'      => $dateStr,
                    'available' => $remaining,
                    'requested' => $request->quantity
                ], 400);
            }
        }

        return true;
    }

    // Menghitung total harga pemesanan
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

            $price = $availability?->custom_price ?? $room->price;
            $total += $price * $request->quantity;
        }
        return $total;
    }

    // Membuat pemesanan
    protected function createBooking(Request $request, Room $room, Property $property, $totalPrice)
    {
        return Booking::create([
            'user_id'      => Auth::id(),
            'property_id'  => $property->id,
            'room_id'      => $room->id,
            'check_in'     => $request->check_in,
            'check_out'    => $request->check_out,
            'guest_count'  => $request->guest_count,
            'quantity'     => $request->quantity,
            'total_price'  => $totalPrice,
            'status'       => 'pending'
        ]);
    }

    // Memperbarui ketersediaan kamar setelah pemesanan
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

    public function userBookings(Request $request)
    {
        $user = Auth::user();
        $bookings = Booking::with(['room', 'property', 'payments'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'message' => 'Pemesanan pengguna berhasil diambil',
            'data' => $bookings
        ]);
    }

    public function userBookingDetail(Booking $booking)
    {
        // Memastikan pengguna hanya bisa mengakses pemesanannya sendiri
        if ($booking->user_id !== Auth::id()) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        return response()->json([
            'message' => 'Detail pemesanan berhasil diambil',
            'data' => $booking->load(['room', 'property', 'userProfile', 'payments'])
        ]);
    }

    public function ownerBookings(Request $request)
    {
        $user = Auth::user();
        $bookings = Booking::with(['user', 'room', 'property'])
            ->whereHas('property', function($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'message' => 'Pemesanan berhasil diambil',
            'data' => $bookings
        ]);
    }

    public function ownerBookingDetail(Booking $booking)
    {
        if ($booking->property->user_id !== Auth::id()) {
            return response()->json(['message' => 'Tidak diizinkan'], 403);
        }

        return response()->json([
            'message' => 'Detail pemesanan berhasil diambil',
            'data' => $booking->load(['user', 'room', 'property', 'userProfile'])
        ]);
    }
}
