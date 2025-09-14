<?php

/**
 * Payment Methods Configuration
 *
 * Registers available payment methods in the application.
 * Each method includes a unique code, display information, and backend handler class.
 */

return [
    'clickpesa' => [
        'code'        => 'clickpesa',
        'title'       => 'ClickPesa',
        'description' => 'Pay securely using ClickPesa mobile money or card.',
        'class'       => 'Webkul\ClickPesa\Payment\ClickPesa',
        'active'      => true,
        'sort'        => 1,
    ],
];
