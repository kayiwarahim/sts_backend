<?php

namespace App\Http\Requests\BillingConfiguration;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillingConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'billing_configurations.create'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'property_id' => [
                'required',
                'integer',
                'exists:properties,id',
            ],

            'water_tariff_id' => [
                'required',
                'integer',
                'exists:water_tariffs,id',
            ],

            'water_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'service_fee_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'vat_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'gateway_fee_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'landlord_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'saas_percentage' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'effective_from' => [
                'required',
                'date',
            ],

            'effective_to' => [
                'nullable',
                'date',
                'after_or_equal:effective_from',
            ],

            'status' => [
                'nullable',
                'in:active,inactive',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            $total =
                (float) $this->water_percentage +
                (float) $this->service_fee_percentage +
                (float) $this->vat_percentage +
                (float) $this->gateway_fee_percentage +
                (float) $this->landlord_percentage +
                (float) $this->saas_percentage;

            if (abs($total - 100) > 0.001) {
                $validator->errors()->add(
                    'percentages',
                    "The billing percentages must total 100%. Current total: {$total}%."
                );
            }
        });
    }
}