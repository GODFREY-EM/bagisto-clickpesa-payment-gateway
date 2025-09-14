<?php

namespace Webkul\ClickPesa\Payment;

use Webkul\Payment\Payment\Payment;
use Illuminate\Support\Facades\Storage;
use Webkul\Checkout\Facades\Cart;

class ClickPesa extends Payment
{
    /**
     * Payment method code
     *
     * @var string
     */
    protected $code = 'clickpesa';

    /**
     * Get redirect URL to hosted ClickPesa checkout
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return route('clickpesa.redirect');
    }

    /**
     * Get payment method logo/image
     *
     * @return string
     */
    public function getImage()
    {
        $url = $this->getConfigData('image');

        return $url
            ? Storage::url($url)
            : asset('vendor/clickpesa/images/clickpesa.png');
    }

    /**
     * Additional payment info shown on checkout summary before redirecting
     *
     * @return array
     */
    public function getAdditionalDetails()
    {
        $cart = Cart::getCart();

        if (! $cart) {
            return [];
        }

        return [
            'title'          => $this->getTitle(),
            'description'    => $this->getDescription(),
            'cart'           => $cart,
            'billingAddress' => $cart->billing_address,
            'html'           => view()->exists('clickpesa::hosted-info')
                ? view('clickpesa::hosted-info')->render()
                : '<p>You will be redirected to ClickPesa to complete your payment.</p>',
        ];
    }

    /**
     * The view used for selecting this payment method
     *
     * @return string
     */
    public function getPaymentView()
    {
        return 'shop::checkout.onepage.payment';
    }

    /**
     * Title of the payment method
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getConfigData('title') ?: 'ClickPesa';
    }

    /**
     * Description shown in checkout
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->getConfigData('description') ?: 'Pay securely using ClickPesa mobile money';
    }

    /**
     * Whether this payment method is enabled
     *
     * @return bool
     */
    public function isActive()
    {
        return (bool) $this->getConfigData('active');
    }

    /**
     * Sort order for this payment method
     *
     * @return int
     */
    public function getSortOrder()
    {
        return (int) ($this->getConfigData('sort') ?? 0);
    }
}
