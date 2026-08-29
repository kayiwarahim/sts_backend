<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    /*
    |--------------------------------------------------------------------------
    | Created
    |--------------------------------------------------------------------------
    */

    public function created(
        Model $model
    ): void {

        $this->record(
            $model,
            'created',
            null,
            $this->sanitize(
                $model->getAttributes()
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Updated
    |--------------------------------------------------------------------------
    */

    public function updated(
        Model $model
    ): void {

        $changes =
            $this->sanitize(
                $model->getChanges()
            );

        unset(
            $changes[
                'updated_at'
            ]
        );

        if (empty($changes)) {
            return;
        }

        $oldValues = [];

        foreach (
            array_keys(
                $changes
            ) as $key
        ) {

            $oldValues[$key] =
                $model
                    ->getOriginal(
                        $key
                    );
        }

        $this->record(
            $model,
            'updated',
            $this->sanitize(
                $oldValues
            ),
            $changes
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Deleted
    |--------------------------------------------------------------------------
    */

    public function deleted(
        Model $model
    ): void {

        $this->record(
            $model,
            'deleted',
            $this->sanitize(
                $model
                    ->getAttributes()
            ),
            null
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Record audit
    |--------------------------------------------------------------------------
    */

    protected function record(
        Model $model,
        string $action,
        ?array $oldValues,
        ?array $newValues
    ): void {

        AuditLog::create([
            'user_id' => auth()->id(),

            'organization_id' => $this
                ->organizationId(
                    $model
                ),

            'action' => $action,

            'auditable_type' => $model::class,

            'auditable_id' => $model->getKey(),

            'old_values' => $oldValues,

            'new_values' => $newValues,

            'ip_address' => request()
                ->ip(),

            'user_agent' => request()
                ->userAgent(),

            'description' => sprintf(
                '%s %s #%s',
                ucfirst(
                    $action
                ),
                class_basename(
                    $model
                ),
                $model
                    ->getKey()
            ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve organization
    |--------------------------------------------------------------------------
    */

    protected function organizationId(
        Model $model
    ): ?int {

        if (
            isset(
                $model
                    ->organization_id
            )
        ) {
            return
                $model
                    ->organization_id;
        }

        /*
        |--------------------------------------------------------------------------
        | Direct property models
        |--------------------------------------------------------------------------
        */

        if (
            method_exists(
                $model,
                'property'
            )
        ) {

            $property =
                $model
                    ->property()
                    ->first();

            if (
                $property
                    ?->organization_id
            ) {
                return
                    $property
                        ->organization_id;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Models linked through Unit
        |--------------------------------------------------------------------------
        */

        if (
            method_exists(
                $model,
                'unit'
            )
        ) {

            $unit =
                $model
                    ->unit()
                    ->with(
                        'property'
                    )
                    ->first();

            if (
                $unit
                    ?->property
                    ?->organization_id
            ) {
                return
                    $unit
                        ->property
                        ->organization_id;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Never record secrets
    |--------------------------------------------------------------------------
    */

    protected function sanitize(
        ?array $values
    ): ?array {

        if ($values === null) {
            return null;
        }

        $sensitive = [
            'password',
            'remember_token',
            'token',
            'credentials',
            'bearer_token',
            'api_key',
            'secret',
            'webhook_key',
        ];

        foreach (
            $sensitive as $key
        ) {

            if (
                array_key_exists(
                    $key,
                    $values
                )
            ) {
                $values[$key] =
                    '[REDACTED]';
            }
        }

        return $values;
    }
}
