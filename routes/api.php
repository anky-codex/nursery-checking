<?php

use App\Http\Controllers\CheckInController;
use App\Http\Controllers\RoomOccupantController;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Support\Facades\Route;

// Served without an /api prefix (see bootstrap/app.php). Every route here runs
// inside a tenant: IdentifyTenant reads X-Tenant-ID before anything else happens.
Route::middleware(IdentifyTenant::class)->group(function (): void {
    Route::post('rooms/{room}/check-ins', [CheckInController::class, 'store'])
        ->whereNumber('room');

    Route::post('check-ins/{id}/check-out', [CheckInController::class, 'checkOut'])
        ->whereNumber('id');

    Route::get('rooms/{room}/occupants', [RoomOccupantController::class, 'index'])
        ->whereNumber('room');
});
