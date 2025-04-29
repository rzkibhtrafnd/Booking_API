<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomAvailability extends Model
{
    protected $fillable = [
        'room_id','date','available',
        'booked_quantity','custom_price'
    ];

    protected $hidden = [
        'room_id','created_at','updated_at'
    ];

    protected $casts = [
        'date'            => 'date:Y-m-d',
        'available'       => 'boolean',
        'custom_price'    => 'decimal:2',
        'booked_quantity' => 'integer',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}