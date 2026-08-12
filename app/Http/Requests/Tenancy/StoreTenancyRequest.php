<?php

namespace App\Http\Requests\Tenancy;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'tenancies.create'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'unit_id' => [
                'required',
                'integer',
                'exists:units,id',
            ],

            'tenant_id' => [
                'required',
                'integer',
                'exists:tenants,id',
            ],

            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],

            'status' => [
                'nullable',
                'in:active,ended,terminated',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}