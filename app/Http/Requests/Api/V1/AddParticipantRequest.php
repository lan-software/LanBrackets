<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ParticipantType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddParticipantRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'participant_type' => ['required', 'string', Rule::in(array_column(ParticipantType::cases(), 'value'))],
            'participant_id' => ['required', 'integer'],
            'seed' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'participant_type.required' => 'A participant type is required.',
            'participant_type.in' => 'Invalid participant type. Must be "team" or "user".',
            'participant_id.required' => 'A participant ID is required.',
        ];
    }
}
