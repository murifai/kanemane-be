<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\ExchangeRate;
use Carbon\Carbon;

class CurrencyService
{
    /**
     * Fetch exchange rates from Frankfurter API
     *
     * @param string $base
     * @param array $targets
     * @return array
     */
    public function fetchRates(string $base = 'JPY', array $targets = ['IDR']): array
    {
        $response = Http::get('https://api.frankfurter.app/latest', [
            'from' => $base,
            'to' => implode(',', $targets),
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to fetch exchange rates: ' . $response->body());
    }

    /**
     * Update exchange rates in database
     *
     * @param string $base
     * @param array $targets
     * @return void
     */
    public function updateRates(string $base = 'JPY', array $targets = ['IDR']): void
    {
        $data = $this->fetchRates($base, $targets);
        $date = $data['date'];
        $rates = $data['rates'];

        foreach ($rates as $currency => $rate) {
            ExchangeRate::updateOrCreate(
                [
                    'base_currency' => $base,
                    'target_currency' => $currency,
                    'date' => $date,
                ],
                [
                    'rate' => $rate,
                ]
            );
        }
    }
}
