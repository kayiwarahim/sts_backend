<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta
            name="viewport" 
            content="width=device-width, initial-scale=1.0">
        <title>
            Payment Successful
        </title>
    </head>

    <body style=" margin:0; padding:0; background:#f8fafc; font-family:Arial, Helvetica, sans-serif; color:#1e293b;">

            @php

                $meter =
                    $payment
                        ->tenant
                        ?->activeTenancy
                        ?->unit
                        ?->activeMeterAssignment
                        ?->meter;

            @endphp

            <table width="100%" cellpadding="0" cellspacing="0" style=" padding:30px 15px; background:#f8fafc; ">
                <tr>
                    <td align="center">
                        <table width="100%" cellpadding="0" cellspacing="0" style=" max-width:600px; background:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; ">
                            <tr>
                                <td style=" padding:24px; background:#0f172a; color:white; ">
                                    <h2 style="margin:0;">
                                        Water Payment Successful
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
                                        Your water payment has been received successfully.
                                    </p>

                                    <table width="100%" cellpadding="8" cellspacing="0" style=" margin-top:20px; background:#f8fafc; border-radius:8px; ">
                                        <tr>
                                            <td>
                                                Payment Reference
                                            </td>
                                            <td align="right">
                                                <strong>
                                                    {{ $payment->reference }}
                                                </strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                Meter Number
                                            </td>
                                            <td align="right">
                                                <strong>
                                                    {{ $meter?->meter_number ?? '-' }}
                                                </strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                Amount
                                            </td>
                                            <td align="right">
                                                <strong>

                                                    {{ $payment->currency }}
                                                    {{ number_format(
                                                        (float) $payment->amount,
                                                        2
                                                    ) }}
                                                </strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                Property
                                            </td>
                                            <td align="right">
                                                {{ $payment->property?->name ?? '-' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                Status
                                            </td>
                                            <td align="right">
                                                <strong>
                                                    Successful
                                                </strong>
                                            </td>
                                        </tr>
                                    </table>
                                    <p style=" margin-top:25px; color:#64748b; " >
                                        Your prepaid water token will be delivered separately once the STS vending process has completed.
                                    </p
                                    <p style=" margin-top:25px; color:#64748b;font-size:13px; text-align:center; ">
                                        Contact us on +256 759 259 740 for any assistance.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
    </body>
</html>