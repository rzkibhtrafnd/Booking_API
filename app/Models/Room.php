<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'name',
        'description',
        'capacity',
        'price',
        'facilities',
        'stock'
    ];

    protected $casts = [
        'facilities' => 'array',
        'price' => 'decimal:2',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function availabilities()
    {
        return $this->hasMany(RoomAvailability::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function getAvailableRooms($startDate, $endDate)
    {
        $period = new \DatePeriod(
            new \DateTime($startDate),
            new \DateInterval('P1D'),
            new \DateTime($endDate)
        );

        $results = [];
        foreach ($period as $date) {
            $availability = $this->availabilities()
                ->where('date', $date->format('Y-m-d'))
                ->first();

            $available = $availability ? 
                ($this->stock - $availability->booked_quantity) : 
                $this->stock;

            $results[$date->format('Y-m-d')] = [
                'available' => $available,
                'custom_price' => $availability->custom_price ?? null
            ];
        }

        return $results;
    }
}