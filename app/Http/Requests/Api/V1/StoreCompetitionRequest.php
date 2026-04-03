<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\CompetitionType;
use App\Enums\StageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompetitionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(array_column(CompetitionType::cases(), 'value'))],
            'stage_type' => ['required', 'string', Rule::in(array_column(StageType::cases(), 'value'))],
            'description' => ['nullable', 'string'],
            'settings' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A competition name is required.',
            'type.required' => 'A competition type is required.',
            'type.in' => 'Invalid competition type.',
            'stage_type.required' => 'A stage type is required.',
            'stage_type.in' => 'Invalid stage type.',
        ];
    }
}
