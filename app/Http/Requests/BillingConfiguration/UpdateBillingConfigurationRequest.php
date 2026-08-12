<?php

namespace App\Http\Requests\BillingConfiguration;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'billing_configurations.update'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'water_tariff_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:water_tariffs,id',
            ],

            'water_percentage' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'service_fee_percentage' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'vat_percentage' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'gateway_fee_percentage' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'landlord_percentage' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'saas_percentage' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:100',
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
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            $fields = [
                'water_percentage',
                'service_fee_percentage',
                'vat_percentage',
                'gateway_fee_percentage',
                'landlord_percentage',
                'saas_percentage',
            ];

            $total = 0;

            foreach ($fields as $field) {
                $total += (float) (
                    $this->input(
                        $field,
                        $this->route(
                            'billingConfiguration'
                        )?->{$field} ?? 0
                    )
                );
            }

            if (abs($total - 100) > 0.001) {
                $validator->errors()->add(
                    'percentages',
                    "The billing percentages must total 100%. Current total: {$total}%."
                );
            }
        });
    }
}