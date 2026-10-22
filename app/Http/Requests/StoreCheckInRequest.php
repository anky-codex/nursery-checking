<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Child;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authentication is handled upstream; tenancy is enforced by the middleware and scope.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'child_id' => [
                'bail',
                'required',
                'integer',
                // Uses the tenant-scoped model, so a child of another tenant is
                // indistinguishable from one that does not exist.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Child::query()->whereKey($value)->exists()) {
                        $fail('The selected child does not exist.');
                    }
                },
            ],
        ];
    }
}
