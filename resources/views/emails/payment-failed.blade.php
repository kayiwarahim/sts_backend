<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Payment Failed
    </title>
</head>

<body
    style="
        margin:0;
        padding:0;
        background:#f8fafc;
        font-family:Arial, Helvetica, sans-serif;
        color:#1e293b;
    "
>

<table
    width="100%"
    cellpadding="0"
    cellspacing="0"
    style="
        padding:30px 15px;
    "
>
    <tr>

        <td align="center">

            <table
                width="100%"
                cellpadding="0"
                cellspacing="0"
                style="
                    max-width:600px;
                    background:#ffffff;
                    border:1px solid #e2e8f0;
                    border-radius:12px;
                "
            >

                <tr>

                    <td
                        style="
                            padding:24px;
                            background:#991b1b;
                            color:white;
                        "
                    >
                        <h2 style="margin:0;">
                            Water Payment Failed
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
                            Your water payment could not be completed.
                        </p>

                        <table
                            width="100%"
                            cellpadding="8"
                            cellspacing="0"
                            style="
                                background:#f8fafc;
                                border-radius:8px;
                            "
                        >

                            <tr>
                                <td>
                                    Reference
                                </td>

                                <td align="right">
                                    {{ $payment->reference }}
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    Amount
                                </td>

                                <td align="right">
                                    {{ $payment->currency }}
                                    {{ number_format((float) $payment->amount, 2) }}
                                </td>
                            </tr>

                        </table>

                        <p style="margin-top:20px;">

                            {{ $friendlyReason ?? 'Please try the payment again.' }}

                        </p>

                    </td>

                </tr>

            </table>

        </td>

    </tr>
</table>

</body>

</html>