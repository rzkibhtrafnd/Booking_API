<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'amount',
        'method',
        'status',
        'paid_at',
        'transfer_proof'
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount' => 'decimal:2'
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
