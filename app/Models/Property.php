<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Property extends Model
{
    use HasFactory;


    protected $fillable = [
        'id', 'user_id', 'name', 'type', 'description',
        'address', 'city', 'latitude', 'longitude', 'rating', 'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function photos()
    {
        return $this->hasMany(PropertyPhoto::class);
    }

    public function mainPhoto()
    {
        return $this->hasOne(PropertyPhoto::class)->where('img_main', true);
    }

    public function getMainImageUrlAttribute()
    {
        return $this->mainPhoto ? asset('storage/' . $this->mainPhoto->img) : asset('images/default.jpg');
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}
