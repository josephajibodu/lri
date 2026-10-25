<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site and Search
    |--------------------------------------------------------------------------
    |
    | How the club appears in search results, link previews and the
    | structured data search engines read. Keep the title under about
    | 60 characters and the description under about 160.
    |
    */

    'site' => [
        'name' => "Let's Run Ibadan",
        'alternate_name' => 'LRI',
        'title' => "Let's Run Ibadan | Run Club and Running Community in Ibadan",
        'description' => 'A free run club for runners, joggers and walkers in Ibadan, Oyo State. Saturday runs at 6:30 AM from 5 km, with tribes in Akobo, Bodija, UI and Oluyole.',
        'slogan' => 'Everyone. Every pace. One community.',
        'locality' => 'Ibadan',
        'region' => 'Oyo State',
        'country' => 'NG',
    ],

    /*
    |--------------------------------------------------------------------------
    | Next Community Run
    |--------------------------------------------------------------------------
    |
    | The run shown in the top bar and the countdown card on the home page.
    | Update these values when the next community run is announced. The
    | start time is read in the given timezone (Ibadan is Africa/Lagos).
    |
    */

    'next_run' => [
        'title' => 'Breast Cancer Awareness Run + Hangout',
        'starts_at' => '2026-10-31 06:30',
        'timezone' => 'Africa/Lagos',
        'venue' => 'The Law Hub',
        'address' => 'No. 9 Ring Road',
        'map_url' => 'https://maps.google.com/?q=7.3731818199157715,3.858956813812256',
        'note' => 'Every tribe runs together, then we hang out. Bring a friend.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Community Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        'instagram' => 'https://www.instagram.com/letsrunibadan/',
        'member_form' => 'https://docs.google.com/forms/d/e/1FAIpQLScBqAfuSOLlA2YSUpJ9XBdNeo9MVbmwh1gQWNr8z_BMtIb1QA/viewform',
        'run_the_elements' => 'https://www.runtheelements.com/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Instagram Highlights
    |--------------------------------------------------------------------------
    |
    | Public posts and reels from @letsrunibadan shown in the scrolling
    | Instagram section on the home page, in this order. Paste the post's
    | link from Instagram's "Copy link". Leave the list empty to hide it.
    |
    */

    'instagram_posts' => [
        'https://www.instagram.com/p/DZGD_DFDkOe/',
        'https://www.instagram.com/p/De4FdImMUwc/',
        'https://www.instagram.com/p/Del0-_VuHec/',
        'https://www.instagram.com/p/DejRLtzuSeP/',
        'https://www.instagram.com/p/DeRgSJOucMh/',
        'https://www.instagram.com/p/Dd_PTPpuX-v/',
        'https://www.instagram.com/p/DeANMbFOo2u/',
        'https://www.instagram.com/p/Ddw9cdDgK-_/',
    ],

];
