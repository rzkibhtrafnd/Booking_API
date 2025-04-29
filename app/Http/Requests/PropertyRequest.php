<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class PropertyRequest extends FormRequest
{
    public function authorize() { return auth()->check(); }
    public function rules()
    {
        return [
            'name'               => 'required|string|max:255',
            'type'               => 'required|string|max:255',
            'description'        => 'required|string',
            'address'            => 'required|string|max:255',
            'city'               => 'required|string|max:255',
            'latitude'           => 'required|numeric',
            'longitude'          => 'required|numeric',
            'main_image'         => ['nullable', File::image()->max(2048)],
            'additional_images.*'=> ['nullable', File::image()->max(2048)],
            'deleted_image_ids'  => 'array',
            'deleted_image_ids.*'=> 'integer|exists:property_photos,id',
        ];
    }
}
