<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'description'   => $this->description,
            'capacity'      => $this->capacity,
            'price'         => (float)$this->price,
            'facilities'    => $this->facilities,
            'stock'         => $this->stock,
            'availabilities'=> RoomAvailabilityResource::collection($this->whenLoaded('availabilities')),
        ];
    }
}
