<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PropertyPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'id', 'property_id', 'img', 'img_main'
    ];

    protected $casts = [
        'img_main' => 'boolean',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
