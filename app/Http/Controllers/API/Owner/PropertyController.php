<?php

namespace App\Http\Controllers\API\Owner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class PropertyController extends Controller
{
    public function index()
    {
        $properties = Property::with('photos')->where('user_id', auth()->id())->get();
        return response()->json($properties);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'description' => 'required|string',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'main_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'additional_images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Memastikan user mengirim reques dengan benar
        if (!User::find(auth()->id())) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $property = Property::create([
            'user_id' => auth()->id(),
            ...$request->only(['name', 'type', 'description', 'address', 'city', 'latitude', 'longitude'])
        ]);

        // Simpan gambar utama
        if ($request->hasFile('main_image')) {
            $this->storePhoto($property->id, $request->file('main_image'), true);
        }

        // Simpan gambar tambahan
        if ($request->hasFile('additional_images')) {
            foreach ($request->file('additional_images') as $image) {
                $this->storePhoto($property->id, $image);
            }
        }

        return response()->json([
            'message' => 'Property created successfully.',
            'property' => $property->load('photos')
        ], 201);
    }

    public function show(Property $property)
    {
        if ($property->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($property->load('photos'));
    }

    public function update(Request $request, Property $property)
    {
        if ($property->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'description' => 'required|string',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'additional_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'deleted_image_ids' => 'array',
            'deleted_image_ids.*' => 'integer|exists:property_photos,id',
        ]);

        $property->update($request->except(['main_image', 'additional_images', 'deleted_image_ids']));

        // Hapus gambar yang dipilih
        if ($request->filled('deleted_image_ids')) {
            $photosToDelete = PropertyPhoto::whereIn('id', $request->deleted_image_ids)
                ->where('property_id', $property->id)
                ->get();

            foreach ($photosToDelete as $photo) {
                Storage::disk('public')->delete($photo->img);
                $photo->delete();
            }
        }

        // Update gambar utama
        if ($request->hasFile('main_image')) {
            $oldMain = $property->photos()->where('img_main', true)->first();
            if ($oldMain) {
                Storage::disk('public')->delete($oldMain->img);
                $oldMain->delete();
            }
            $this->storePhoto($property->id, $request->file('main_image'), true);
        }

        // Tambah gambar tambahan baru
        if ($request->hasFile('additional_images')) {
            foreach ($request->file('additional_images') as $image) {
                $this->storePhoto($property->id, $image);
            }
        }

        return response()->json([
            'message' => 'Property updated successfully.',
            'property' => $property->load('photos')
        ]);
    }

    public function destroy(Property $property)
    {
        if ($property->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        foreach ($property->photos as $photo) {
            Storage::disk('public')->delete($photo->img);
            $photo->delete();
        }

        $property->delete();

        return response()->json(['message' => 'Property deleted successfully.']);
    }

    private function storePhoto($propertyId, $image, $isMain = false)
    {
        $path = $image->store('property_images', 'public');

        PropertyPhoto::create([
            'property_id' => $propertyId,
            'img' => $path,
            'img_main' => $isMain,
        ]);
    }
}
