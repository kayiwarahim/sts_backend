<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('units.create')
            ?? false;
    }

    public function rules(): array
    {
        return [
            'unit_number' => [
                'required',
                'string',
                'max:100',
            ],

            'floor' => [
                'nullable',
                'string',
                'max:50',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'status' => [
                'nullable',
                'in:occupied,vacant,maintenance,inactive',
            ],
        ];
    }
}
