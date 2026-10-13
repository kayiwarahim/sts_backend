# Property water-wallet mobile money disbursements

These endpoints send the property's collected water allocation to a Ugandan
mobile money number using Relworx. The recipient receives `amount`. The property
water wallet is debited for **amount + fixed gateway fee**. Collection percentage
splits are not applied again.

## Setup

Apply the new persistence migration before using this feature:

```powershell
php artisan migrate --path=database/migrations/2026_10_13_090000_create_mobile_money_disbursements_table.php
php artisan queue:restart
```

Use the existing Relworx environment settings: `RELWORX_BASE_URL`,
`RELWORX_ACCOUNT_NO`, `RELWORX_BEARER_TOKEN`, and the webhook key/URL. Configure
Relworx disbursement callbacks to use the existing `/api/webhooks/relworx` URL.
The signature is verified by the existing signature service. Disbursement callbacks
trigger an authoritative provider status query before accounting is finalized.

An asynchronous queue connection requires a running worker. A `sync` connection
submits during the request. The property's existing active UGX ledger accounts
`WATER_PAYABLE` and `PAYMENT_CLEARING` are required. An organization-scoped
`DISBURSEMENT_PENDING` liability account is provisioned automatically on first use.

## Authentication and permissions

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
Content-Type: application/json
```

Callers need permission to view the property; non-Super Admins are restricted to
their organization. Additional permissions are listed below.

| Method | Endpoint                                                            | Permission                                             |
| ------ | ------------------------------------------------------------------- | ------------------------------------------------------ |
| GET    | `/api/properties/{property}/disbursements/quote?amount=50000`     | `nwsc.payments.create`                               |
| POST   | `/api/properties/{property}/disbursements`                        | `nwsc.payments.create` and `nwsc.payments.process` |
| GET    | `/api/properties/{property}/disbursements?per_page=20`            | `nwsc.payments.view`                                 |
| GET    | `/api/properties/{property}/disbursements/{disbursement}`         | `nwsc.payments.view`                                 |
| POST   | `/api/properties/{property}/disbursements/{disbursement}/refresh` | `nwsc.payments.process`                              |

The existing property policy also requires `properties.view` for organization
users. No new roles or permissions are added.

## Fee schedule

| Amount sent (whole UGX) | Gateway fee (UGX) |
| ----------------------- | ----------------: |
| 500–50,000             |               800 |
| 50,001–200,000         |             1,600 |
| 200,001–400,000        |             2,100 |
| 400,001–1,000,000      |             3,100 |
| 1,000,001–1,500,000    |             5,200 |
| 1,500,001–5,000,000    |             7,200 |

Amounts outside these bands or fractional amounts are rejected. This schedule
is the one supplied for this application; it is independent of collection fees.

## Quote

```http
GET /api/properties/10/disbursements/quote?amount=50000
```

HTTP 200:

```json
{
  "success": true,
  "data": {
    "currency": "UGX",
    "recipient_amount": 50000,
    "gateway_fee": 800,
    "total_debit": 50800
  }
}
```

A quote does not check or reserve wallet funds.

## Initiate a disbursement

```http
POST /api/properties/10/disbursements
```

```json
{
  "reference": "PAYOUT-20261013-001",
  "nwsc_account_id": 5,
  "msisdn": "+256701345672",
  "amount": 50000,
  "description": "Main water account remittance"
}
```

| Field               | Required | Rules                                                                                  |
| ------------------- | -------- | -------------------------------------------------------------------------------------- |
| `reference`       | Yes      | Unique 8–36 character idempotency reference; letters, digits, hyphens and underscores |
| `nwsc_account_id` | Yes      | Active NWSC account belonging to this property                                         |
| `msisdn`          | Yes      | International Ugandan mobile number:`+2567` followed by 8 digits                     |
| `amount`          | Yes      | Whole UGX from 500 through 5,000,000; recipient amount                                 |
| `description`     | No       | Up to 255 characters; defaults to`NWSC water remittance`                             |

The selected NWSC account is the bookkeeping/remittance destination. The API
sends money to the explicitly supplied mobile number; it does not verify that
the mobile money subscriber belongs to NWSC. Confirm the beneficiary before submitting.
Currency is fixed to UGX, and the server calculates the fee.

HTTP 202 returns a persisted disbursement, abbreviated:

```json
{
  "success": true,
  "message": "Disbursement recorded. Inspect its status for provider confirmation.",
  "data": {
    "id": 1,
    "property_id": 10,
    "water_wallet_id": 3,
    "nwsc_account_id": 5,
    "reference": "PAYOUT-20261013-001",
    "msisdn": "+256701345672",
    "currency": "UGX",
    "amount": "50000.00",
    "gateway_fee": "800.00",
    "total_debit": "50800.00",
    "status": "pending",
    "provider_reference": null,
    "provider_transaction_id": null,
    "reservation_ledger_id": 100,
    "completion_ledger_id": null,
    "nwsc_payment_id": null
  }
}
```

202 acknowledges the local request, not delivery of money. Reuse the **same
reference and payload** when retrying an HTTP request. A matching request returns
the existing record without another deduction. Reusing the reference with a
different property, amount, number or NWSC account returns 422. Description is
not changed on replay. Do not create a new reference to retry an uncertain payout.

## Status and reconciliation

GET the detail endpoint to read local status. POST the refresh endpoint (no body)
to query Relworx and apply confirmation. Both return `{ "success": true, "data": { ...disbursement } }` on HTTP 200. List returns a paginator under `data`.

| Status           | Meaning                                                                                      |
| ---------------- | -------------------------------------------------------------------------------------------- |
| `pending`      | Funds reserved, job awaiting submission                                                      |
| `submitting`   | Submission claimed; no second send is allowed                                                |
| `processing`   | Provider accepted the send or reports it pending                                             |
| `unknown`      | Transport/API outcome unclear; reservation remains                                           |
| `successful`   | Provider confirmed delivery; payout accounting posted                                        |
| `failed`       | Provider confirmed zero-charge failure; reservation reversed                                 |
| `needs_review` | Provider fee differs or a failed payout has a charge; reservation remains for reconciliation |

Timeouts and crashes after submission are reconciled using the original customer
reference when an internal provider reference is unavailable. Jobs do not
automatically resend. Repeating initiation for a still-pending record can enqueue
it again; the persisted submission claim prevents duplicate sends.

`php artisan water:reconcile` also checks nonterminal disbursements during its
provider stage. `--internal-only` skips this provider query. `needs_review` and
provider errors are reported as reconciliation errors. A fee discrepancy needs
operator investigation; this API does not offer a force-success or force-release
operation. Do not mark funds released until the provider outcome is established.

Provider references, amount, currency and recipient must match before accounting
is finalized. `provider_transaction_id` stores the provider TID on confirmation.
If the provider omits `charge`, the configured fee is used; a supplied differing
charge requires review. Duplicate terminal callbacks make no additional postings.

## Wallet and double-entry records

For UGX 50,000 sent with an UGX 800 fee:

| Stage                       | Debit                                                      | Credit                      | Wallet effect          |
| --------------------------- | ---------------------------------------------------------- | --------------------------- | ---------------------- |
| Reserve                     | WATER_PAYABLE 50,800                                       | DISBURSEMENT_PENDING 50,800 | Debit 50,800           |
| Confirm success             | DISBURSEMENT_PENDING 50,000 recipient + 800 gateway charge | PAYMENT_CLEARING 50,800     | No second wallet debit |
| Confirm zero-charge failure | DISBURSEMENT_PENDING 50,800                                | WATER_PAYABLE 50,800        | Credit 50,800          |

Reservations and final postings use the existing `LedgerService`, wallet row
locks, and database transactions. Every journal has equal total debit and credit.
The fee is borne by the water wallet; collection allocations and collection
gateway liabilities are not charged again. Principal and fee have separate
descriptions in the successful ledger journal and separate stored amounts.

On success, an existing `NwscPayment` record is created for the recipient amount
only (50,000). The reservation wallet debit is linked to it. NWSC remittance
statistics therefore include successful principal remittances and the current
wallet balance already reflects principal plus fees and pending reservations.
Each initiation and terminal outcome also creates an audit log. No NWSC payment
record is created for pending/unknown/failed attempts.

## Errors

- 401: missing/invalid authentication.
- 403: permission or property access denied.
- 404: property/disbursement missing or disbursement belongs to another property.
- 422: invalid fields, insufficient funds including fee, inactive property/wallet/account, wrong wallet currency, missing ledger setup, or reused reference conflict.
- 502 on refresh: provider API/data validation failure; reservation is retained.

Insufficient funds are checked atomically before enqueueing or contacting the
provider. Both wallet balance and fee must be displayed before the user submits.

Provider contract references:
[Send payment](https://payments.relworx.com/docs/mobile_money/send_payment/),
[Check request status](https://payments.relworx.com/docs/mobile_money/check_request_status/),
[Webhooks](https://payments.relworx.com/docs/webhooks/).

## Verification

Run the payout tests and existing collection webhook tests against the testing database:

```powershell
php artisan test --compact tests/Feature/MobileMoneyDisbursementTest.php tests/Feature/RelworxWebhookTest.php
```

Provider HTTP requests are faked in the payout tests. They cover fee boundaries,
wallet reservations, success/failure postings, replay protection, unknown outcomes,
fee discrepancies, mismatched provider results, permissions and webhook routing.
