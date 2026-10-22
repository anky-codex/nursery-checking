<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CheckIn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A child currently in a room, shown via their open check-in.
 *
 * @mixin CheckIn
 */
final class OccupantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'check_in_id' => $this->id,
            'checked_in_at' => $this->checked_in_at->toIso8601String(),
            'child' => [
                'id' => $this->child->id,
                'name' => $this->child->name,
            ],
        ];
    }
}
