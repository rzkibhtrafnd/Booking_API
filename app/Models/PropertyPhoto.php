<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id','img','img_main'
    ];

    protected $hidden = [
        'property_id','created_at','updated_at'
    ];

    protected $casts = [
        'img_main' => 'boolean',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
