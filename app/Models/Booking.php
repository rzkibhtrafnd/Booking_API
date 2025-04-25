<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'property_id',
        'room_id',
        'check_in',
        'check_out',
        'guest_count',
        'quantity',
        'total_price',
        'status',
        'special_requests'
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'total_price' => 'decimal:2',
    ];

    // Relasi dengan User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relasi dengan Property
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    // Relasi dengan Room
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    // Cek apakah booking bisa dibatalkan
    public function canBeCancelled(): bool
    {
        return $this->status === 'confirmed' 
            && $this->check_in > now()->addDays(1);
    }

    // Hitung jumlah malam
    public function getNightsAttribute(): int
    {
        return Carbon::parse($this->check_in)
            ->diffInDays(Carbon::parse($this->check_out));
    }

    // Scope untuk booking aktif
    public function scopeActive($query)
    {
        return $query->where('status', 'confirmed')
            ->where('check_out', '>=', now());
    }

    // Scope untuk booking berdasarkan user
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}