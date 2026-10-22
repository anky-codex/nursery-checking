<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\ChildAlreadyCheckedIn;
use App\Models\CheckIn;
use App\Models\Child;
use App\Models\Room;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Checks a child into a room, unless they are already checked into one.
 */
final class CheckInChild
{
    public function handle(Room $room, Child $child): CheckIn
    {
        try {
            return DB::transaction(function () use ($room, $child): CheckIn {
                // Serialise concurrent check-ins of the same child where the
                // database supports row locks (a no-op on SQLite).
                Child::query()->whereKey($child->getKey())->lockForUpdate()->first();

                $this->ensureNotAlreadyCheckedIn($child);

                return CheckIn::query()->create([
                    'room_id' => $room->getKey(),
                    'child_id' => $child->getKey(),
                    'checked_in_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            // Lost a race: the partial unique index stopped the second open
            // check-in. Report it the same way as the ordinary case.
            $this->ensureNotAlreadyCheckedIn($child);

            throw $e;
        }
    }

    private function ensureNotAlreadyCheckedIn(Child $child): void
    {
        $open = CheckIn::query()->open()->where('child_id', $child->getKey())->first();

        if ($open !== null) {
            throw new ChildAlreadyCheckedIn($open);
        }
    }
}
