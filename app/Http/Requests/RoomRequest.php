<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoomRequest extends FormRequest
{
    public function authorize() { return auth()->check(); }
    public function rules()
    {
        return [
            'property_id' => 'required|exists:properties,id',
            'name'        => 'required|string|max:255',
            'description' => 'required|string',
            'capacity'    => 'required|integer|min:1',
            'price'       => 'required|numeric|min:0',
            'facilities'  => 'nullable|array',
            'stock'       => 'required|integer|min:1',
            'availabilities'          => 'nullable|array',
            'availabilities.*.date'   => 'required|date|after_or_equal:today',
            'availabilities.*.custom_price'=>'nullable|numeric|min:0',
        ];
    }
}
