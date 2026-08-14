<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:test-payment-flow')]
#[Description('Command description')]
class TestPaymentFlow extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
