<?php

namespace App\Http\Requests\Meter;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('meters.create')
            ?? false;
    }

    public function rules(): array
    {
        $user =
            $this->user();

        return [
            /*
            |--------------------------------------------------------------------------
            | Owning Organization
            |--------------------------------------------------------------------------
            |
            | Super Admin:
            |   Must explicitly choose the organization that owns this meter.
            |
            | Landlord:
            |   Does not need to send organization_id.
            |   MeterService will force their own organization_id.
            |--------------------------------------------------------------------------
            */

            'organization_id' => [
                $user?->isSuperAdmin()
                    ? 'required'
                    : 'nullable',

                'integer',
                'exists:organizations,id',
            ],

            'meter_number' => [
                'required',
                'string',
                'max:100',
                'unique:meters,meter_number',
            ],

            'serial_number' => [
                'required',
                'string',
                'max:100',
                'unique:meters,serial_number',
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
                'nullable',
                'in:active,inactive,tampered,disconnected',
            ],

            'installed_at' => [
                'nullable',
                'date',
            ],
        ];
    }
}