<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Providers
    |--------------------------------------------------------------------------
    |
    | Each payment channel (a way to pay in a currency) names the provider
    | that handles it, so different methods can go through different
    | providers. The default is used where no channel decides, such as
    | when syncing channels without naming a provider.
    |
    */

    'default' => env('PAYMENTS_PROVIDER', 'bachs'),

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | How long a buyer has to pay once a checkout opens, in minutes, and so
    | how long their tickets are held. Bachs allows 1 to 1440.
    |
    */

    'checkout_expires_in_minutes' => (int) env('PAYMENTS_CHECKOUT_EXPIRES_IN', 60),

    'providers' => [

        /*
        | Bachs (docs.bachs.io). Sandbox keys start with sk_sandbox_ and live
        | keys with sk_live_; the matching API address is picked from the key
        | unless base_url is set. The webhook secret is the signing secret of
        | the webhook endpoint in the Bachs developer portal.
        */

        'bachs' => [
            'secret_key' => env('BACHS_SECRET_KEY'),
            'webhook_secret' => env('BACHS_WEBHOOK_SECRET'),
            'base_url' => env('BACHS_BASE_URL'),
            'webhook_tolerance' => 300,
            'connect_timeout' => 5,
            'timeout' => 15,
        ],

    ],

];
