<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CheckInChild;
use App\Actions\CheckOutChild;
use App\Http\Requests\StoreCheckInRequest;
use App\Http\Resources\CheckInResource;
use App\Models\CheckIn;
use App\Models\Child;
use App\Models\Room;
use Illuminate\Http\JsonResponse;

/**
 * Route parameters are looked up through the tenant-scoped models, so an id
 * belonging to another tenant produces the same 404 as one that does not exist.
 */
final class CheckInController
{
    public function store(StoreCheckInRequest $request, CheckInChild $checkInChild, string $room): JsonResponse
    {
        $room = Room::query()->findOrFail($room);
        $child = Child::query()->findOrFail($request->validated('child_id'));

        $checkIn = $checkInChild->handle($room, $child);

        return CheckInResource::make($checkIn)->response()->setStatusCode(201);
    }

    public function checkOut(CheckOutChild $checkOutChild, string $id): CheckInResource
    {
        $checkIn = CheckIn::query()->findOrFail($id);

        return CheckInResource::make($checkOutChild->handle($checkIn));
    }
}
