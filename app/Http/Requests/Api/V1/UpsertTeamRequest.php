<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate the inbound payload for `POST /api/v1/teams`.
 *
 * @see docs/mil-std-498/SRS.md COMP-F-018
 * @see docs/mil-std-498/IDD.md §3.8.1
 */
class UpsertTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string'],
            'external_reference_id' => ['required', 'string', 'max:255'],
            'source_system' => ['required', 'string', 'max:64'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'disbanded'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A team name is required.',
            'external_reference_id.required' => 'An external_reference_id is required.',
            'source_system.required' => 'A source_system is required.',
        ];
    }
}
