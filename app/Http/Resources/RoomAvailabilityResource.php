<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RoomAvailabilityResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'date'            => $this->date->format('Y-m-d'),
            'available'       => $this->available,
            'booked_quantity' => $this->booked_quantity,
            'custom_price'    => $this->custom_price !== null
                                  ? (float)$this->custom_price
                                  : null,
        ];
    }
}
