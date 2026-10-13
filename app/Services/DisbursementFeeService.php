<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class DisbursementFeeService
{
    public function fee(int $amount): int
    {
        if ($amount < 500 || $amount > 5000000) {
            throw ValidationException::withMessages(['amount' => 'Disbursements must be between UGX 500 and UGX 5,000,000.']);
        }
        return match (true) {
            $amount <= 50000 => 800,
            $amount <= 200000 => 1600,
            $amount <= 400000 => 2100,
            $amount <= 1000000 => 3100,
            $amount <= 1500000 => 5200,
            default => 7200,
        };
    }
}
