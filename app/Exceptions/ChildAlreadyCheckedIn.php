<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\CheckIn;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The child already has an open check-in. We reject rather than move them:
 * staff must check the child out of the first room explicitly (see README).
 */
final class ChildAlreadyCheckedIn extends RuntimeException
{
    public function __construct(public readonly CheckIn $openCheckIn)
    {
        parent::__construct('This child is already checked into a room. Check them out first.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'child_already_checked_in',
            // Tells staff exactly which check-in to close.
            'current_check_in' => [
                'id' => $this->openCheckIn->getKey(),
                'room_id' => $this->openCheckIn->room_id,
            ],
        ], 409);
    }
}
