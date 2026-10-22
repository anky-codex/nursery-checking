<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ListRoomOccupants;
use App\Http\Resources\OccupantResource;
use App\Models\Room;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class RoomOccupantController
{
    public function index(ListRoomOccupants $listRoomOccupants, string $room): AnonymousResourceCollection
    {
        $room = Room::query()->findOrFail($room);

        return OccupantResource::collection($listRoomOccupants->handle($room));
    }
}
