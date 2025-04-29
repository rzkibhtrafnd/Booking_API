<?php

namespace App\Http\Controllers\API\Owner;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Http\Requests\RoomRequest;
use App\Models\Room;
use App\Models\Property;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    // Menampilkan daftar kamar milik owner
    public function index()
    {
        $rooms = Room::with('availabilities','property')
            ->whereHas('property', fn($q)=> $q->where('user_id', auth()->id()))
            ->get();

        return RoomResource::collection($rooms);
    }

    // Menyimpan kamar baru
    public function store(RoomRequest $request)
    {
        $property = Property::where('user_id', auth()->id())
                            ->findOrFail($request->property_id);

        return DB::transaction(function() use($request,$property) {
            $room = Room::create($request->validated());
            foreach ($request->input('availabilities',[]) as $av) {
                $room->availabilities()->create($av + ['available'=>true]);
            }
            $room->load('availabilities','property');
            return (new RoomResource($room))
                   ->response()->setStatusCode(201);
        });
    }

    // Menampilkan detail kamar
    public function show(Property $property, Room $room)
    {
        if ($room->property_id !== $property->id
         || $property->user_id    !== auth()->id()) {
            abort(403);
        }
        return new RoomResource($room->load('availabilities'));
    }

    // Mengupdate kamar
    public function update(RoomRequest $request, $id)
    {
        $room = Room::whereHas('property', fn($q)=> $q->where('user_id',auth()->id()))
                    ->findOrFail($id);

        return DB::transaction(function() use($request,$room){
            $room->update($request->validated());

            // Mengsinkronisasi ketersediaan kamar
            $room->availabilities()->delete();
            foreach ($request->input('availabilities',[]) as $av) {
                $room->availabilities()->create($av + ['available'=>true]);
            }

            return new RoomResource($room->load('availabilities','property'));
        });
    }

    // Menghapus kamar
    public function destroy($id)
    {
        $room = Room::whereHas('property', fn($q)=> $q->where('user_id',auth()->id()))
                    ->findOrFail($id);
        $room->delete();
        return response()->json(['message'=>'Kamar berhasil dihapus.']);
    }
}