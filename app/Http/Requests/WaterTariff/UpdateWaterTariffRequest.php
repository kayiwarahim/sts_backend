<?php

namespace App\Http\Requests\WaterTariff;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWaterTariffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'water_tariffs.update'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'price_per_m3' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],

            'effective_to' => [
                'nullable',
                'date',
            ],

            'status' => [
                'sometimes',
                'required',
                'in:active,inactive',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}
