<?php

namespace App\Http\Requests\MeterAssignment;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeterAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'meter_assignments.create'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'meter_id' => [
                'required',
                'integer',
                'exists:meters,id',
            ],

            'unit_id' => [
                'required',
                'integer',
                'exists:units,id',
            ],

            'assigned_at' => [
                'required',
                'date',
            ],

            'status' => [
                'nullable',
                'in:active,ended',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}