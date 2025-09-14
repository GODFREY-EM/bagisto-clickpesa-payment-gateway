<?php

return [
    'payment' => [
        'description' => 'Pay securely using M-Pesa mobile money',
    ],

    'checkout' => [
        'cart' => [
            'have-error'               => 'Something went wrong while processing your request.',
            'check-shipping-address'   => 'Please provide a valid shipping address.',
            'check-billing-address'    => 'Please provide a valid billing address.',
            'specify-shipping-method'  => 'Please select a shipping method.',
            'specify-payment-method'   => 'Please select a payment method.',
            'payment-failed'           => 'Payment has failed. Please try again.',
            'payment-cancelled'        => 'You have cancelled the payment.',
            'minimum-order-message'    => 'The minimum order amount is :amount.',
        ],
    ],

    'common' => [
        'error' => 'An unexpected error occurred. Please try again.',
    ],

    'clickpesa' => [
        'redirecting'         => 'Redirecting to ClickPesa...',
        'success'             => 'Payment successful! Thank you for your order.',
        'cancelled'           => 'Payment cancelled. You can try again.',
        'error'               => 'An error occurred while processing your payment. Please try again.',
        'pending_cancelled'   => 'Your unpaid order has been automatically cancelled due to timeout.',
    ],
];
