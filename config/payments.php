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

    /*
    |--------------------------------------------------------------------------
    | Smallest Charge
    |--------------------------------------------------------------------------
    |
    | The least a checkout can ask for, in kobo. Bachs refuses anything under
    | NGN 100, so a coupon always leaves at least this much to pay.
    |
    */

    'minimum_charge' => 10_000,

    /*
    |--------------------------------------------------------------------------
    | Limits On Unpaid Orders
    |--------------------------------------------------------------------------
    |
    | Holding tickets costs nothing, so these stop one person holding them all
    | without paying. A buyer (by email) and a connection (by IP address, which
    | people on one mobile network can share) can only hold so many unpaid
    | tickets, and each order only gets so many tries at paying, since every
    | try keeps its tickets held for longer.
    |
    */

    'unpaid_limits' => [
        'tickets_per_buyer' => 20,
        'tickets_per_connection' => 40,
        'attempts_per_order' => 5,
    ],

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
