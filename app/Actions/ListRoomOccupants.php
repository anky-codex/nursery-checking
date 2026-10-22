<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CheckIn;
use App\Models\Room;
use Illuminate\Database\Eloquent\Collection;

/**
 * Answers "who is in this room right now?" with the room's open check-ins,
 * earliest arrival first.
 */
final class ListRoomOccupants
{
    /**
     * @return Collection<int, CheckIn>
     */
    public function handle(Room $room): Collection
    {
        return CheckIn::query()
            ->open()
            ->where('room_id', $room->getKey())
            ->with('child')
            ->orderBy('checked_in_at')
            ->orderBy('id')
            ->get();
    }
}
