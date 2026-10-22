<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\CheckInAlreadyClosed;
use App\Models\CheckIn;

/**
 * Closes an open check-in.
 */
final class CheckOutChild
{
    public function handle(CheckIn $checkIn): CheckIn
    {
        // A single conditional UPDATE, so two simultaneous check-outs cannot
        // both succeed and the first timestamp is never overwritten.
        $closed = CheckIn::query()
            ->whereKey($checkIn->getKey())
            ->open()
            ->update(['checked_out_at' => now()]);

        if ($closed === 0) {
            throw new CheckInAlreadyClosed();
        }

        return $checkIn->refresh();
    }
}
