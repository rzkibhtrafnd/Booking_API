<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'type'         => $this->type,
            'description'  => $this->description,
            'address'      => $this->address,
            'city'         => $this->city,
            'latitude'     => (float)$this->latitude,
            'longitude'    => (float)$this->longitude,
            'rating'       => (float)$this->rating,
            'status'       => $this->status,
            'photos'       => PropertyPhotoResource::collection($this->whenLoaded('photos')),
        ];
    }
}
