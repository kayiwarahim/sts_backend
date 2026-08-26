<?php

namespace App\Services;

use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function __construct(
        protected ReportService $reportService,
        protected ReconciliationService $reconciliationService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Report
    |--------------------------------------------------------------------------
    */

    public function payments(
        User $user,
        array $filters = []
    ): StreamedResponse {

        $filters['per_page'] = 100;

        $report = $this->reportService->payments( $user, $filters);

        return $this->stream(
            'payments-report-' .
            now()->format('Ymd-His') .
            '.csv',

            [
                'Reference',
                'Organization',
                'Property',
                'Tenant',
                'Phone',
                'Amount',
                'Currency',
                'Status',
                'Provider Transaction ID',
                'Provider',
                'Initiated At',
                'Completed At',
            ],

            function ($handle) use ($report) {

                foreach (
                    $report->items()
                    as $payment
                ) {

                    fputcsv(
                        $handle,
                        [
                            $payment->reference,
                            $payment->organization?->name,
                            $payment->property?->name,
                            $payment->tenant? trim($payment->tenant->first_name . ' ' .$payment->tenant->last_name) : null,
                            $payment->payer_phone,
                            $payment->amount,
                            $payment->currency,
                            $payment->status,
                            $payment->provider_transaction_id,
                            $payment->mobile_money_provider,
                            optional($payment->initiated_at)->toDateTimeString(),
                            optional($payment->completed_at)->toDateTimeString(),
                        ]
                    );
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Water Vending Report
    |--------------------------------------------------------------------------
    */

    public function waterVendings(
        User $user,
        array $filters = []
    ): StreamedResponse {

        $filters['per_page'] = 100;

        $report =
            $this->reportService
                ->waterVendings(
                    $user,
                    $filters
                );

        return $this->stream(
            'water-vending-report-' .
            now()->format('Ymd-His') .
            '.csv',

            [
                'Reference',
                'Payment Reference',
                'Property',
                'Tenant',
                'Meter',
                'Water Amount',
                'Unit Price',
                'Units',
                'Status',
                'Vended At',
            ],

            function ($handle) use ($report) {

                foreach (
                    $report->items()
                    as $vending
                ) {

                    fputcsv(
                        $handle,
                        [
                            $vending->reference,
                            $vending->payment?->reference,
                            $vending->property?->name,
                            $vending->tenant? trim($vending->tenant->first_name .' ' .$vending->tenant->last_name): null,
                            $vending->meter?->meter_number,
                            $vending->amount,
                            $vending->price_per_m3,
                            $vending->volume_m3,
                            $vending->status,
                            optional($vending->vended_at)->toDateTimeString(),
                        ]
                    );
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Ledger Summary
    |--------------------------------------------------------------------------
    */

    public function ledger(
        User $user,
        array $filters = []
    ): StreamedResponse {

        $report =
            $this->reportService
                ->ledgerSummary(
                    $user,
                    $filters
                );

        return $this->stream(
            'ledger-report-' .
            now()->format('Ymd-His') .
            '.csv',

            [
                'Account Code',
                'Account',
                'Debit',
                'Credit',
            ],

            function ($handle) use ($report) {

                foreach (
                    $report['accounts']
                    as $account
                ) {

                    fputcsv(
                        $handle,
                        [
                            $account['code'],
                            $account['account'],
                            $account['debit'],
                            $account['credit'],
                        ]
                    );
                }

                fputcsv(
                    $handle,
                    []
                );

                fputcsv(
                    $handle,
                    [
                        'TOTAL',
                        '',
                        $report[
                            'total_debit'
                        ],
                        $report[
                            'total_credit'
                        ],
                    ]
                );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Reconciliation
    |--------------------------------------------------------------------------
    */

    public function paymentReconciliation(
        User $user,
        array $filters = []
    ): StreamedResponse {

        $filters['per_page'] = 100;

        $report =
            $this
                ->reconciliationService
                ->payments(
                    $user,
                    $filters
                );

        return $this->stream(
            'payment-reconciliation-' .
            now()->format('Ymd-His') .
            '.csv',

            [
                'Reference',
                'Payment Amount',
                'Allocation Total',
                'Ledger Debit',
                'Ledger Credit',
                'Balanced',
                'Issues',
            ],

            function ($handle) use ($report) {

                foreach (
                    $report->items()
                    as $payment
                ) {

                    $reconciliation =
                        $payment
                            ->reconciliation
                            ?? [];

                    fputcsv(
                        $handle,
                        [
                            $payment
                                ->reference,

                            $reconciliation[
                                'payment_amount'
                            ] ?? 0,

                            $reconciliation[
                                'allocation_total'
                            ] ?? 0,

                            $reconciliation[
                                'ledger_debit'
                            ] ?? 0,

                            $reconciliation[
                                'ledger_credit'
                            ] ?? 0,

                            (
                                $reconciliation[
                                    'balanced'
                                ] ?? false
                            )
                                ? 'YES'
                                : 'NO',

                            implode(
                                ', ',
                                $reconciliation[
                                    'issues'
                                ] ?? []
                            ),
                        ]
                    );
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STS Reconciliation
    |--------------------------------------------------------------------------
    */

    public function stsReconciliation(
        User $user,
        array $filters = []
    ): StreamedResponse {

        $filters['per_page'] = 100;

        $report =
            $this
                ->reconciliationService
                ->sts(
                    $user,
                    $filters
                );

        return $this->stream(
            'sts-reconciliation-' .
            now()->format('Ymd-His') .
            '.csv',

            [
                'Reference',
                'Meter',
                'Payment Reference',
                'Transaction Type',
                'Amount',
                'Volume M3',
                'Token',
                'Status',
                'Balanced',
                'Issues',
            ],

            function ($handle) use ($report) {

                foreach (
                    $report->items()
                    as $transaction
                ) {

                    $reconciliation =
                        $transaction
                            ->reconciliation
                            ?? [];

                    fputcsv(
                        $handle,
                        [
                            $transaction
                                ->reference,

                            $transaction
                                ->meter
                                ?->meter_number,

                            $transaction
                                ->payment
                                ?->reference,

                            $transaction
                                ->transaction_type,

                            $transaction
                                ->amount,

                            $transaction
                                ->volume_m3,

                            $transaction
                                ->token,

                            $transaction
                                ->status,

                            (
                                $reconciliation[
                                    'balanced'
                                ] ?? false
                            )
                                ? 'YES'
                                : 'NO',

                            implode(
                                ', ',
                                $reconciliation[
                                    'issues'
                                ] ?? []
                            ),
                        ]
                    );
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Stream CSV
    |--------------------------------------------------------------------------
    */

    protected function stream(
        string $filename,
        array $headers,
        callable $writer
    ): StreamedResponse {

        return response()->streamDownload(
            function () use (
                $headers,
                $writer
            ) {

                $handle =
                    fopen(
                        'php://output',
                        'w'
                    );

                /*
                |--------------------------------------------------------------------------
                | UTF-8 BOM
                |--------------------------------------------------------------------------
                |
                | Helps Excel open UTF-8 CSV correctly.
                |
                */

                fwrite(
                    $handle,
                    "\xEF\xBB\xBF"
                );

                fputcsv(
                    $handle,
                    $headers
                );

                $writer(
                    $handle
                );

                fclose(
                    $handle
                );
            },
            $filename,
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',

                'Cache-Control' =>
                    'no-store, no-cache',
            ]
        );
    }
}