<?php

namespace Webkul\ClickPesa\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Transformers\OrderResource;

class ClickPesaController extends Controller
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected InvoiceRepository $invoiceRepository
    ) {}

    /**
     * Redirect customer to ClickPesa Hosted Checkout.
     */
    public function redirect()
    {
        if (!Cart::hasError()) {
            try {
                Cart::collectTotals();
                $this->validateOrder();

                $cart = Cart::getCart();
                $data = (new OrderResource($cart))->jsonSerialize();
                $order = $this->orderRepository->create($data);
                Cart::deActivateCart();

                session()->flash('order', $order);
                session()->flash('info', trans('clickpesa::app.clickpesa.redirecting'));

                Log::debug('Generating ClickPesa link for order: ' . $order->id);
                $checkoutLink = $this->generateCheckoutLink($order);
                Log::debug('Generated ClickPesa link: ' . $checkoutLink);

                return redirect()->away($checkoutLink);
            } catch (\Exception $e) {
                Log::error('ClickPesa Redirect Error: ' . $e->getMessage());
                session()->flash('error', trans('clickpesa::app.clickpesa.error'));

                return redirect()->route('shop.checkout.cart.index');
            }
        }

        session()->flash('error', trans('clickpesa::app.clickpesa.error'));
        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Generate ClickPesa checkout link via API
     */
    protected function generateCheckoutLink($order)
    {
        $token = $this->getAuthToken();
        $apiUrl = config('clickpesa.api_url', 'https://api.clickpesa.com');
        $endpoint = $apiUrl . '/third-parties/checkout-link/generate-checkout-url';

        // Generate a unique order reference combining order id and timestamp
        // This guarantees uniqueness per payment attempt.
        $orderReference = $order->id . time();

        $payload = [
            'totalPrice' => number_format($order->grand_total, 2, '.', ''),
            'orderReference' => $orderReference,
            'orderCurrency' => $order->order_currency_code,
            'customerName' => $order->customer_full_name,
            'customerEmail' => $order->customer_email,
            'customerPhone' => $this->formatPhone($order->customer_phone),
        ];

        // Always add checksum in production
        $checksumKey = config('clickpesa.checksum_key', 'CHKkvnhWFTNYqPGRiXuBAOPAgUDz92xLCUK');
        $payload['checksum'] = $this->createPayloadChecksum($checksumKey, $payload);

        // Debug log
        Log::debug('ClickPesa Payload', $payload);

        $response = Http::withHeaders([
            'Authorization' => $token,
            'Content-Type' => 'application/json',
        ])->post($endpoint, $payload);

        if ($response->failed()) {
            throw new \Exception('Checkout link API failed: ' . $response->body());
        }

        return $response->json()['checkoutLink'];
    }


    /**
     * Create payload checksum for security
     */
    protected function createPayloadChecksum($checksumKey, $payload)
    {
        // Remove existing checksum if present
        unset($payload['checksum']);

        // Sort payload keys alphabetically
        ksort($payload);

        // Concatenate sorted values
        $payloadString = implode("", array_values($payload));

        // Generate HMAC-SHA256 hash
        return hash_hmac("sha256", $payloadString, $checksumKey);
    }

    /**
     * Get ClickPesa authentication token
     */
    protected function getAuthToken()
    {
        $apiUrl = config('clickpesa.api_url', 'https://api.clickpesa.com');
        $tokenEndpoint = $apiUrl . '/third-parties/generate-token';

        $clientId = core()->getConfigData('sales.payment_methods.clickpesa.client_id');
        $apiSecret = core()->getConfigData('sales.payment_methods.clickpesa.api_secret');

        $response = Http::withHeaders([
            'client-id' => $clientId,
            'api-key' => $apiSecret,
        ])->post($tokenEndpoint);

        if ($response->failed()) {
            throw new \Exception('Auth failed: ' . $response->body());
        }

        $data = $response->json();

        if (!isset($data['token'])) {
            throw new \Exception('Invalid token response: ' . $response->body());
        }

        return $data['token'];
    }

    /**
     * Format phone number for ClickPesa
     */
    protected function formatPhone($phone)
    {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);

        // Add country code if missing (Tanzania)
        if (strlen($phone) === 9 && !str_starts_with($phone, '255')) {
            return '255' . $phone;
        }

        return $phone;
    }

    /**
     * Handle return from ClickPesa.
     */
    public function success(Request $request)
    {
        $paymentStatus = $request->input('status');
        $orderId = $request->input('order_id', $request->input('orderReference'));

        if ($paymentStatus === 'success' && $orderId) {
            try {
                $order = $this->orderRepository->findOrFail($orderId);

                if ($order->status !== 'processing') {
                    $this->orderRepository->update(['status' => 'processing'], $order->id);

                    if ($order->canInvoice()) {
                        $this->invoiceRepository->create($this->prepareInvoiceData($order));
                    }
                }

                session()->flash('order', $order);
                session()->flash('success', trans('clickpesa::app.clickpesa.success'));

                return redirect()->route('shop.checkout.onepage.success');
            } catch (\Exception $e) {
                Log::error('ClickPesa Success Error: ' . $e->getMessage());
            }
        }

        session()->flash('error', trans('clickpesa::app.clickpesa.error'));
        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Handle payment cancel.
     */
    public function cancel()
    {
        session()->flash('error', trans('clickpesa::app.clickpesa.cancelled'));
        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Callback for server-to-server notification from ClickPesa.
     */
    public function callback(Request $request)
    {
        $payload = $request->getContent();
        $receivedSignature = $request->header('X-Signature');
        $secret = core()->getConfigData('sales.payment_methods.clickpesa.api_secret');

        $calculatedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($receivedSignature, $calculatedSignature)) {
            Log::warning('ClickPesa Callback Signature Mismatch', [
                'received'   => $receivedSignature,
                'calculated' => $calculatedSignature,
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($payload, true);
        $orderId = $data['order_id'] ?? $data['orderReference'] ?? null;

        if ($orderId && ($data['status'] ?? null) === 'success') {
            try {
                $order = $this->orderRepository->findOrFail($orderId);

                if ($order->status !== 'processing') {
                    $this->orderRepository->update(['status' => 'processing'], $order->id);

                    if ($order->canInvoice()) {
                        $this->invoiceRepository->create($this->prepareInvoiceData($order));
                    }
                }
            } catch (\Exception $e) {
                Log::error('ClickPesa Callback Order Error: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Callback processed'], 200);
    }

    /**
     * Prepare invoice data.
     */
    protected function prepareInvoiceData($order)
    {
        $invoiceData = ['order_id' => $order->id];

        foreach ($order->items as $item) {
            $invoiceData['invoice']['items'][$item->id] = $item->qty_to_invoice;
        }

        return $invoiceData;
    }

    /**
     * Validates the order like Bagisto does internally.
     */
    protected function validateOrder()
    {
        $cart = Cart::getCart();

        $minimumOrderAmount = (float) core()->getConfigData('sales.order_settings.minimum_order.minimum_order_amount') ?: 0;

        if (!Cart::haveMinimumOrderAmount()) {
            throw new \Exception(trans('shop::app.checkout.cart.minimum-order-message', ['amount' => core()->currency($minimumOrderAmount)]));
        }

        if (
            $cart->haveStockableItems()
            && !$cart->shipping_address
        ) {
            throw new \Exception(trans('clickpesa::app.checkout.cart.check-shipping-address'));
        }

        if (!$cart->billing_address) {
            throw new \Exception(trans('clickpesa::app.checkout.cart.check-billing-address'));
        }

        if (
            $cart->haveStockableItems()
            && !$cart->selected_shipping_rate
        ) {
            throw new \Exception(trans('clickpesa::app.checkout.cart.specify-shipping-method'));
        }

        if (!$cart->payment) {
            throw new \Exception(trans('clickpesa::app.checkout.cart.specify-payment-method'));
        }
    }
}
