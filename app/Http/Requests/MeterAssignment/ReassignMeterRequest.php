<?php

namespace App\Http\Requests\MeterAssignment;

use Illuminate\Foundation\Http\FormRequest;

class ReassignMeterRequest extends FormRequest
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
            'unit_id' => [
                'required',
                'integer',
                'exists:units,id',
            ],

            'assigned_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}