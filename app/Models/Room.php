<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id','name','description',
        'capacity','price','facilities','stock'
    ];

    protected $hidden = [
        'property_id','created_at','updated_at'
    ];

    protected $casts = [
        'facilities' => 'array',
        'price'      => 'decimal:2',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function availabilities()
    {
        return $this->hasMany(RoomAvailability::class);
    }
}