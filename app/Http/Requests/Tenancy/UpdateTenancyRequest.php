<?php

namespace App\Http\Requests\Tenancy;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'tenancies.update'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'start_date' => [
                'sometimes',
                'required',
                'date',
            ],

            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],

            'status' => [
                'sometimes',
                'required',
                'in:active,ended,terminated',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}