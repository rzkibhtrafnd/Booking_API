<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PropertyPhotoResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'      => $this->id,
            'url'     => asset('storage/' . $this->img),
            'is_main' => $this->img_main,
        ];
    }
}
