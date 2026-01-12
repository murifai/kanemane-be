<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currency:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update exchange rates from external API';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\CurrencyService $currencyService)
    {
        $this->info('Fetching exchange rates...');
        
        try {
            $currencyService->updateRates();
            $this->info('Exchange rates updated successfully.');
        } catch (\Exception $e) {
            $this->error('Failed to update exchange rates: ' . $e->getMessage());
            return 1;
        }
    }
}
