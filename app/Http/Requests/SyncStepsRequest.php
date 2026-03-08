<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncStepsRequest extends FormRequest
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
            'steps' => ['required', 'integer', 'min:0'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'hourly_steps' => ['sometimes', 'array', 'size:24'],
            'hourly_steps.*' => ['integer', 'min:0'],
        ];
    }
}
