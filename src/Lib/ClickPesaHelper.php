<?php

namespace Webkul\ClickPesa\Lib;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClickPesaHelper
{
    /**
     * Generate the ClickPesa hosted checkout URL.
     *
     * @param array       $orderItems
     * @param string      $orderReference
     * @param string      $merchantId
     * @param string|null $callbackURL
     * @return string|null
     */
    public function generateCheckoutUrl(
        array $orderItems,
        string $orderReference,
        string $merchantId,
        ?string $callbackURL = null
    ): ?string {
        $apiUrl = rtrim(Config::get('clickpesa.api_url', 'https://api.clickpesa.com'), '/');
        $endpoint = '/webshop/generate-checkout-url';

        $payload = [
            'orderItems'     => $orderItems,
            'orderReference' => $orderReference,
            'merchantId'     => $merchantId,
        ];

        if ($callbackURL) {
            $payload['callbackURL'] = $callbackURL;
        }

        try {
            $response = Http::timeout(10)->retry(2, 200)
                ->post("{$apiUrl}{$endpoint}", $payload);

            $data = $response->json();

            if ($response->successful() && isset($data['checkout_url'])) {
                return $data['checkout_url'];
            }

            Log::error('ClickPesa Checkout URL generation failed.', [
                'payload'  => $payload,
                'response' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ClickPesa API request exception.', [
                'error'   => $e->getMessage(),
                'payload' => $payload,
            ]);
        }

        return null;
    }
}
