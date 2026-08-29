<?php

namespace App\Http\Requests\MeterAssignment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeterAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'meter_assignments.update'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'unassigned_at' => [
                'nullable',
                'date',
            ],

            'status' => [
                'sometimes',
                'required',
                'in:active,ended',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
