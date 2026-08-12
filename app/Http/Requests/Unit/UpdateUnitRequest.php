<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('units.update')
            ?? false;
    }

    public function rules(): array
    {
        return [
            'unit_number' => [
                'sometimes',
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
                'sometimes',
                'required',
                'in:occupied,vacant,maintenance,inactive',
            ],
        ];
    }
}