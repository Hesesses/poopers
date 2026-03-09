<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UseItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'effect_id' => ['nullable', 'integer', 'exists:item_effects,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_user_id.exists' => 'The selected target does not exist.',
            'effect_id.exists' => 'The selected effect does not exist.',
        ];
    }
}
