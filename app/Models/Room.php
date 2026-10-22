<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = ['name'];

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }
}
