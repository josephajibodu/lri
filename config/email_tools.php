<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email Tool
    |--------------------------------------------------------------------------
    |
    | The outside tool that sends follow-up emails, such as to buyers who
    | abandoned their checkout. The site only sends it the data, and only
    | for buyers who ticked the consent box. Leave it empty to send nothing.
    |
    */

    'default' => env('EMAIL_TOOL'),

    'tools' => [

        /*
        | Mailchimp (mailchimp.com/developer). The audience id is under
        | Audience > More options > Audience settings. After setting these,
        | run php artisan email-tools:prepare once.
        */

        'mailchimp' => [
            'api_key' => env('MAILCHIMP_API_KEY'),
            'audience_id' => env('MAILCHIMP_AUDIENCE_ID'),
            'connect_timeout' => 5,
            'timeout' => 15,
        ],

    ],

];
