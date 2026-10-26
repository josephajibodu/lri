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
        'map_url' => 'https://maps.app.goo.gl/jcXfeecZv1zgkQot6',
        'note' => 'Every tribe runs together, then we hang out. Bring a friend.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Community Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        'instagram' => 'https://www.instagram.com/letsrunibadan/',
        'tiktok' => 'https://www.tiktok.com/@lri.letsrunibadan',
        'strava' => 'https://www.strava.com/clubs/1955817',
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

    /*
    |--------------------------------------------------------------------------
    | Founders
    |--------------------------------------------------------------------------
    |
    | The people who started the club and the story so far, shown on the
    | founders page in this order. A photo is a path under public/, such as
    | images/founders/name.jpg (portrait, about 4:5); leave it null to show
    | the founder's initials instead. Facts and links are label => value
    | pairs and can be left empty. Milestones run oldest first.
    |
    | PLACEHOLDER: both founders and the first two milestones below are
    | stand-ins until the real names, photos, bios and dates arrive.
    |
    */

    'founders' => [
        'founded' => '20XX',

        'people' => [
            [
                'name' => 'Founder One',
                'role' => 'Co-founder',
                'tribe' => 'Bodija',
                'photo' => null,
                'quote' => 'Placeholder quote. One line on why they started LRI.',
                'bio' => 'Placeholder bio. Two or three sentences on who they are, how they started running and what they look after in the club today.',
                'facts' => [
                    'Saturday pace' => '6:30 /km',
                    'Favourite route' => 'Ring Road loop',
                ],
                'links' => [],
            ],
            [
                'name' => 'Founder Two',
                'role' => 'Co-founder',
                'tribe' => 'Akobo',
                'photo' => null,
                'quote' => 'Placeholder quote. One line on why they started LRI.',
                'bio' => 'Placeholder bio. Two or three sentences on who they are, how they started running and what they look after in the club today.',
                'facts' => [
                    'Saturday pace' => '7:00 /km',
                    'Favourite route' => 'Bodija 10K',
                ],
                'links' => [],
            ],
        ],

        'milestones' => [
            [
                'date' => 'Placeholder date',
                'title' => 'The first run',
                'text' => 'Placeholder. Where the first run started, how far it went and how many people turned up.',
            ],
            [
                'date' => 'Placeholder date',
                'title' => 'Four tribes',
                'text' => 'Placeholder. When Akobo, Bodija, UI / Eleyele and Oluyole / Akala became tribes so members could run close to home.',
            ],
            [
                'date' => 'Aug 2026',
                'title' => 'Ring Road loop',
                'text' => 'Every tribe ran the Ring Road loop together from The Law Hub.',
            ],
            [
                'date' => 'Sep 2026',
                'title' => 'Bodija 10K',
                'text' => 'A 10K and a 5K from Pasta Express, Osuntokun, then a community hangout.',
            ],
            [
                'date' => 'Oct 2026',
                'title' => 'Breast Cancer Awareness Run',
                'text' => 'All four tribes together at The Law Hub, running for a cause.',
            ],
        ],
    ],

];
