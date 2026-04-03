<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ParticipantType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAddParticipantsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.participant_type' => ['required', 'string', Rule::in(array_column(ParticipantType::cases(), 'value'))],
            'participants.*.participant_id' => ['required', 'integer'],
            'participants.*.seed' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'participants.required' => 'At least one participant is required.',
            'participants.*.participant_type.required' => 'Each participant must have a type.',
            'participants.*.participant_type.in' => 'Invalid participant type. Must be "team" or "user".',
            'participants.*.participant_id.required' => 'Each participant must have an ID.',
        ];
    }
}
