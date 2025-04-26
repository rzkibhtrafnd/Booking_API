<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\RoomAvailability;
use Illuminate\Support\Facades\DB;

class UpdateRoomAvailability extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-room-availability';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update room availability based on bookings that check out today';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now()->format('Y-m-d');

        // Ambil semua booking yang check-out hari ini dan statusnya confirmed
        $releasedRooms = Booking::where('check_out', $today)
            ->where('status', 'confirmed')
            ->get()
            ->groupBy('room_id');

        foreach ($releasedRooms as $roomId => $bookings) {
            $releasedQuantity = $bookings->sum('quantity');

            // Ambil atau buat data RoomAvailability untuk tanggal hari ini
            $availability = RoomAvailability::firstOrNew([
                'room_id' => $roomId,
                'date' => $today,
            ]);

            // Update booked_quantity dan status ketersediaan
            $availability->booked_quantity = max(0, ($availability->booked_quantity ?? 0) - $releasedQuantity);
            $availability->available = true;
            $availability->save();
        }

        $this->info('Room availability updated successfully');
    }
}