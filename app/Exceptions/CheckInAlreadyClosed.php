<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

final class CheckInAlreadyClosed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This check-in has already been checked out.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'check_in_already_closed',
        ], 409);
    }
}
