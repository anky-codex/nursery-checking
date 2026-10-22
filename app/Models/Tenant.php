<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A nursery group (a customer of the platform). Not itself tenant-scoped.
 */
class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name'];
}
