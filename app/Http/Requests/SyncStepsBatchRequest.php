<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncStepsBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'min:1', 'max:31'],
            'days.*.date' => ['required', 'date_format:Y-m-d'],
            'days.*.steps' => ['required', 'integer', 'min:0'],
        ];
    }
}
