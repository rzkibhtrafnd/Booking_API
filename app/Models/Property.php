<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id','name','type','description',
        'address','city','latitude','longitude','rating','status'
    ];

    protected $hidden = [
        'user_id','created_at','updated_at'
    ];

    protected $casts = [
        'latitude'  => 'decimal:6',
        'longitude' => 'decimal:6',
        'rating'    => 'decimal:1',
    ];

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
