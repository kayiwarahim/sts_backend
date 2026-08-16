<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Token</title>
</head>

<body style=" margin:0; padding:0; background:#f8fafc;  font-family:Arial, Helvetica, sans-serif; color:#1e293b; ">

@php

    $meterToken =
        $stsTransaction
            ->tokens
            ->first();

    $token =
        $meterToken?->token
        ?? $stsTransaction->token;

    $volume =
        $meterToken?->volume_m3
        ?? $stsTransaction->volume_m3;

    $meterNumber =
        $stsTransaction
            ->meter
            ?->meter_number;

@endphp

<table width="100%" cellpadding="0" cellspacing="0" style=" padding:30px 15px;">

    <tr>
        <td align="center">
            <table
                width="100%"
                cellpadding="0"
                cellspacing="0"
                style="
                    max-width:600px;
                    background:#ffffff;
                    border-radius:12px;
                    overflow:hidden;
                    border:1px solid #e2e8f0;
                "
            >

                <tr>
                    <td
                        style="
                            padding:24px;
                            background:#0369a1;
                            color:#ffffff;
                        " >
                        <h2 style="margin:0;">
                            Your Water Token
                        </h2>
                    </td>
                </tr>
                <tr>
                    <td style="padding:30px;">
                        <p>
                            Hello
                            <strong>
                                {{ $payment->tenant?->first_name ?? 'Customer' }}
                            </strong>,
                        </p>
                        <p>
                            Your prepaid water purchase for meter number {{ $meterNumber ?? '-' }} has been completed successfully.
                        </p>
                        <div
                            style="
                                margin:25px 0;
                                padding:25px;
                                border-radius:12px;
                                background:#eff6ff;
                                text-align:center;
                                border:1px solid #bfdbfe;
                            "
                        >
                            <div
                                style="
                                    font-size:13px;
                                    color:#64748b;
                                    margin-bottom:8px;
                                "
                            >
                                STS WATER TOKEN
                            </div>

                            <div
                                style="
                                    font-size:26px;
                                    font-weight:bold;
                                    letter-spacing:3px;
                                    color:#0f172a;
                                "
                            >
                                {{ $token }}
                            </div>

                        </div>

                        <table
                            width="100%"
                            cellpadding="9"
                            cellspacing="0"
                            style="
                                background:#f8fafc;
                                border-radius:8px;
                            "
                        >

                            <tr>
                                <td>
                                    Payment Reference
                                </td>

                                <td align="right">
                                    {{ $payment->reference }}
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    Meter Number
                                </td>

                                <td align="right">
                                    <strong>
                                        {{ $meterNumber ?? '-' }}
                                    </strong>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    Water Quantity
                                </td>

                                <td align="right">
                                    <strong>
                                        {{ number_format((float) $volume, 3) }}
                                        m³
                                    </strong>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    Payment Amount
                                </td>

                                <td align="right">
                                    {{ $payment->currency }}
                                    {{ number_format((float) $payment->amount, 2) }}
                                </td>
                            </tr>

                            {{-- @if($payment->waterVending)

                                <tr>

                                    <td>
                                        Water Allocation
                                    </td>

                                    <td align="right">

                                        {{ $payment->currency }}

                                        {{ number_format(
                                            (float) $payment->waterVending->amount,
                                            2
                                        ) }}

                                    </td>

                                </tr>

                                <tr>

                                    <td>
                                        Local Water Tariff
                                    </td>

                                    <td align="right">

                                        {{ $payment->currency }}

                                        {{ number_format(
                                            (float) $payment->waterVending->price_per_m3,
                                            2
                                        ) }}

                                        / m³

                                    </td>

                                </tr>

                            @endif --}}

                        </table>

                        <div
                            style="
                                margin-top:25px;
                                padding:15px;
                                background:#fefce8;
                                border:1px solid #fde68a;
                                border-radius:8px;
                            "
                        >

                            <strong>
                                Important:
                            </strong>
                            Enter this token into the prepaid water meter associated with meter {{ $stsTransaction->meter->number }}
                        </div>

                        <p style=" margin-top:25px; color:#64748b;font-size:13px; text-align:center; ">
                            Keep this email for your records.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>