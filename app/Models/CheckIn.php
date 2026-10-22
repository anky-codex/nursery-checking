<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stay of one child in one room. It is "open" until checked_out_at is set.
 */
class CheckIn extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = ['room_id', 'child_id', 'checked_in_at', 'checked_out_at'];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
    ];

    /** Check-ins that have not been checked out yet. */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('checked_out_at');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }
}
