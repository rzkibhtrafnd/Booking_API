<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomAvailability extends Model
{
    protected $fillable = [
        'room_id', 'date', 'available', 
        'booked_quantity', 'custom_price'
    ];

    protected $casts = [
        'date' => 'date',
        'available' => 'boolean',
        'custom_price' => 'decimal:2',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}