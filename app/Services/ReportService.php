<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\StsTransaction;
use App\Models\WaterVending;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReportService
{
    public function financialSummary(
        User $user,
        array $filters = []
    ): array {

        $payments =
            $this->paymentQuery(
                $user,
                $filters
            );

        $successful =
            (clone $payments)
                ->where(
                    'status',
                    'successful'
                );

        $totalCollections =
            (float)
            (clone $successful)
                ->sum('amount');

        $successfulCount =
            (clone $successful)
                ->count();

        $failedCount =
            (clone $payments)
                ->where(
                    'status',
                    'failed'
                )
                ->count();

        $processingCount =
            (clone $payments)
                ->whereIn(
                    'status',
                    [
                        'pending',
                        'processing',
                    ]
                )
                ->count();

        $allocationQuery =
            PaymentAllocation::query()
                ->whereHas(
                    'payment',
                    function ($query)
                    use (
                        $user,
                        $filters
                    ) {

                        $this->scopePaymentQuery(
                            $query,
                            $user,
                            $filters
                        );

                        $query->where(
                            'status',
                            'successful'
                        );
                    }
                );

        $allocations =
            (clone $allocationQuery)
                ->selectRaw(
                    '
                    allocation_type,
                    SUM(amount) as total
                    '
                )
                ->groupBy(
                    'allocation_type'
                )
                ->pluck(
                    'total',
                    'allocation_type'
                );

        $waterVendingQuery =
            WaterVending::query()
                ->where(
                    'status',
                    'successful'
                );

        if (
            !$user->isSuperAdmin()
        ) {

            $waterVendingQuery
                ->whereHas(
                    'property',
                    fn ($query) =>
                        $query->where(
                            'organization_id',
                            $user->organization_id
                        )
                );

        }

        $this->applyDateFilters(
            $waterVendingQuery,
            $filters,
            'vended_at'
        );

        return [
            'total_collections' =>
                $totalCollections,

            'successful_payments' =>
                $successfulCount,

            'failed_payments' =>
                $failedCount,

            'processing_payments' =>
                $processingCount,

            'water_funds' =>
                (float)
                ($allocations['water'] ?? 0),

            'service_revenue' =>
                (float)
                ($allocations['service_fee'] ?? 0),

            'vat_payable' =>
                (float)
                ($allocations['vat'] ?? 0),

            'gateway_fees' =>
                (float)
                ($allocations['gateway_fee'] ?? 0),

            'landlord_payable' =>
                (float)
                ($allocations['landlord'] ?? 0),

            'saas_revenue' =>
                (float)
                ($allocations['saas'] ?? 0),

            'total_water_vended_m3' =>
                (float)
                $waterVendingQuery
                    ->sum(
                        'volume_m3'
                    ),
        ];
    }

    public function payments(
        User $user,
        array $filters = []
    ) {

        $query =
            $this->paymentQuery(
                $user,
                $filters
            )
                ->with([
                    'organization:id,name',
                    'property:id,name,property_code',
                    'tenant:id,first_name,last_name,phone',
                    'paymentProvider:id,name,code',
                    'allocations',
                    'waterVending.tokens',
                ])
                ->latest(
                    'initiated_at'
                );

        return $query->paginate(
            min(
                (int)
                ($filters['per_page'] ?? 25),
                100
            )
        );
    }

    public function waterVendings(
        User $user,
        array $filters = []
    ) {

        $query =
            WaterVending::query()
                ->with([
                    'payment:id,reference,amount,status',
                    'tenant:id,first_name,last_name',
                    'property:id,name,property_code',
                    'meter:id,meter_number',
                    'waterTariff:id,name,price_per_m3',
                    'tokens',
                ]);

        if (
            !$user->isSuperAdmin()
        ) {

            $query->whereHas(
                'property',
                fn ($q) =>
                    $q->where(
                        'organization_id',
                        $user->organization_id
                    )
            );

        }

        if (
            !empty(
                $filters['status']
            )
        ) {

            $query->where(
                'status',
                $filters['status']
            );

        }

        $this->applyDateFilters(
            $query,
            $filters,
            'vended_at'
        );

        return $query
            ->latest(
                'vended_at'
            )
            ->paginate(
                min(
                    (int)
                    ($filters['per_page'] ?? 25),
                    100
                )
            );
    }

    public function ledgerSummary(
        User $user,
        array $filters = []
    ): array {

        $entries =
            LedgerEntry::query()
                ->with([
                    'account',
                    'transaction',
                ]);

        if (
            !$user->isSuperAdmin()
        ) {

            $entries->whereHas(
                'transaction',
                fn ($query) =>
                    $query->where(
                        'organization_id',
                        $user->organization_id
                    )
            );

        }

        if (
            !empty(
                $filters['date_from']
            )
        ) {

            $entries->whereHas(
                'transaction',
                fn ($query) =>
                    $query->whereDate(
                        'transaction_date',
                        '>=',
                        $filters['date_from']
                    )
            );

        }

        if (
            !empty(
                $filters['date_to']
            )
        ) {

            $entries->whereHas(
                'transaction',
                fn ($query) =>
                    $query->whereDate(
                        'transaction_date',
                        '<=',
                        $filters['date_to']
                    )
            );

        }

        $rows =
            $entries
                ->get()
                ->groupBy(
                    fn ($entry) =>
                        $entry->account
                            ?->code ??
                        'UNKNOWN'
                )
                ->map(
                    function ($group) {

                        return [
                            'account' =>
                                $group
                                    ->first()
                                    ?->account
                                    ?->name,

                            'code' =>
                                $group
                                    ->first()
                                    ?->account
                                    ?->code,

                            'debit' =>
                                (float)
                                $group->sum(
                                    'debit'
                                ),

                            'credit' =>
                                (float)
                                $group->sum(
                                    'credit'
                                ),
                        ];

                    }
                )
                ->values();

        return [
            'accounts' =>
                $rows,

            'total_debit' =>
                (float)
                $rows->sum(
                    'debit'
                ),

            'total_credit' =>
                (float)
                $rows->sum(
                    'credit'
                ),
        ];
    }

    protected function paymentQuery(
        User $user,
        array $filters = []
    ): Builder {

        $query =
            Payment::query();

        return $this->scopePaymentQuery(
            $query,
            $user,
            $filters
        );
    }

    protected function scopePaymentQuery(
        Builder $query,
        User $user,
        array $filters = []
    ): Builder {

        if (
            !$user->isSuperAdmin()
        ) {

            $query->where(
                'organization_id',
                $user->organization_id
            );

        }

        if (
            !empty(
                $filters['organization_id']
            ) &&
            $user->isSuperAdmin()
        ) {

            $query->where(
                'organization_id',
                $filters['organization_id']
            );

        }

        if (
            !empty(
                $filters['property_id']
            )
        ) {

            $query->where(
                'property_id',
                $filters['property_id']
            );

        }

        if (
            !empty(
                $filters['status']
            )
        ) {

            $query->where(
                'status',
                $filters['status']
            );

        }

        $this->applyDateFilters(
            $query,
            $filters,
            'initiated_at'
        );

        return $query;
    }

    protected function applyDateFilters(
        Builder $query,
        array $filters,
        string $column
    ): void {

        if (
            !empty(
                $filters['date_from']
            )
        ) {

            $query->whereDate(
                $column,
                '>=',
                Carbon::parse(
                    $filters['date_from']
                )
            );

        }

        if (
            !empty(
                $filters['date_to']
            )
        ) {

            $query->whereDate(
                $column,
                '<=',
                Carbon::parse(
                    $filters['date_to']
                )
            );

        }
    }
}