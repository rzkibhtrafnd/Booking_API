<?php

namespace App\Http\Controllers\API\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Http\Requests\PropertyRequest;
use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Support\Facades\Storage;

class PropertyController extends Controller
{
    // Menampilkan daftar properti milik owner
    public function index()
    {
        $properties = Property::with('photos')
            ->where('user_id', auth()->id())
            ->get();

        return PropertyResource::collection($properties);
    }

    // Menyimpan properti baru
    public function store(PropertyRequest $request)
    {
        $property = Property::create(array_merge(
            $request->validated(),
            ['user_id' => auth()->id()]
        ));

        // Menyimpan foto utama
        if ($file = $request->file('main_image')) {
            $this->storePhoto($property, $file, true);
        }

        // Menyimpan foto tambahan
        foreach ($request->file('additional_images', []) as $image) {
            $this->storePhoto($property, $image);
        }

        $property->load('photos');
        return (new PropertyResource($property))
               ->response()->setStatusCode(201);
    }

    // Menampilkan detail properti
    public function show(Property $property)
    {
        $this->authorize('view', $property);
        return new PropertyResource($property->load('photos'));
    }

    // Mengupdate properti
    public function update(PropertyRequest $request, Property $property)
    {
        $this->authorize('update', $property);

        $property->update($request->validated());

        // Menghapus foto yang dipilih
        foreach ($request->input('deleted_image_ids', []) as $photoId) {
            $photo = PropertyPhoto::where('property_id', $property->id)
                                  ->findOrFail($photoId);
            if (Storage::disk('public')->exists($photo->img)) {
                Storage::disk('public')->delete($photo->img);
            }
            $photo->delete();
        }

        // Update atau tambah foto utama
        if ($file = $request->file('main_image')) {
            $old = $property->photos()->where('img_main', true)->first();
            if ($old) {
                Storage::disk('public')->delete($old->img);
                $old->delete();
            }
            $this->storePhoto($property, $file, true);
        }

        // Menambah foto tambahan baru
        foreach ($request->file('additional_images', []) as $image) {
            $this->storePhoto($property, $image);
        }

        $property->load('photos');
        return new PropertyResource($property);
    }

    // Menghapus properti
    public function destroy(Property $property)
    {
        $this->authorize('delete', $property);

        foreach ($property->photos as $photo) {
            if (Storage::disk('public')->exists($photo->img)) {
                Storage::disk('public')->delete($photo->img);
            }
            $photo->delete();
        }

        $property->delete();
        return response()->json(['message'=>'Properti berhasil dihapus.']);
    }

    // Menyimpan foto properti
    private function storePhoto(Property $property, $file, bool $isMain = false)
    {
        $path = $file->store('property_images','public');
        PropertyPhoto::create([
            'property_id'=> $property->id,
            'img'        => $path,
            'img_main'   => $isMain,
        ]);
    }
}
