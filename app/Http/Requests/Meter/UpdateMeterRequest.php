<?php

namespace App\Http\Requests\Meter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('meters.update')
            ?? false;
    }

    public function rules(): array
    {
        $meterId =
            $this->route('meter')?->id;

        return [
            'meter_number' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique(
                    'meters',
                    'meter_number'
                )->ignore($meterId),
            ],

            'serial_number' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique(
                    'meters',
                    'serial_number'
                )->ignore($meterId),
            ],

            'manufacturer' => [
                'nullable',
                'string',
                'max:100',
            ],

            'model' => [
                'nullable',
                'string',
                'max:100',
            ],

            'meter_type' => [
                'nullable',
                'string',
                'max:50',
            ],

            'key_revision' => [
                'nullable',
                'string',
                'max:50',
            ],

            'supply_group_code' => [
                'nullable',
                'string',
                'max:100',
            ],

            'status' => [
                'sometimes',
                'required',
                'in:active,inactive,tampered,disconnected',
            ],

            'installed_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}