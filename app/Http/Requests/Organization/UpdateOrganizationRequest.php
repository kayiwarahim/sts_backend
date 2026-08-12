<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('organizations.update') ?? false;
    }

    public function rules(): array
    {
        $organizationId =
            $this->route('organization')?->id;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'registration_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique(
                    'organizations',
                    'registration_number'
                )->ignore($organizationId),
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            'status' => [
                'sometimes',
                'required',
                'in:active,suspended,inactive',
            ],
        ];
    }
}