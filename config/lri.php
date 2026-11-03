<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site and Search
    |--------------------------------------------------------------------------
    |
    | How the club appears in search results, link previews and the
    | structured data search engines read. Keep the title under about
    | 60 characters and the description under about 125.
    |
    */

    'site' => [
        'name' => "Let's Run Ibadan",
        'alternate_name' => 'LRI',
        'title' => 'Let’s Run Ibadan | Free Run Club in Ibadan, Oyo State',
        'description' => 'A free Saturday run club in Ibadan for every pace. 6:30 AM runs from 5 km, with tribes in Akobo, Bodija, UI and Oluyole.',
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
    | For a run where each tribe starts from its own meeting point, leave
    | address and map_url null and the card links to the tribes instead.
    |
    */

    'next_run' => [
        'title' => 'Saturday Tribe Run',
        'starts_at' => '2026-11-07 06:30',
        'timezone' => 'Africa/Lagos',
        'venue' => 'Your tribe’s meeting point',
        'address' => null,
        'map_url' => null,
        'note' => 'Every tribe runs 5 km and up from home. Your tribe posts the route in its group.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tribes
    |--------------------------------------------------------------------------
    |
    | The local crews members run with. Used wherever a member picks
    | their home tribe, such as the birthday form.
    |
    */

    'tribes' => [
        'Akobo',
        'Bodija',
        'UI / Eleyele',
        'Oluyole / Akala',
    ],

    /*
    |--------------------------------------------------------------------------
    | Akobo Barracks Registration
    |--------------------------------------------------------------------------
    |
    | Adekunle Fajuyi Cantonment (Akobo Barracks) asks every runner to fill
    | its paper permit form. Members fill it online and organisers print the
    | forms and drop them off. The work lines are the same for every runner,
    | so they are printed from here rather than asked on the form. The fee
    | and deadline are shown on the form; set the deadline to null to hide it.
    |
    */

    'barracks_registration' => [
        'cantonment' => 'Adekunle Fajuyi Cantonment',
        'fee' => '₦2,000',
        'deadline' => 'Friday',
        'work_activity' => "Recreational running with Let's Run Ibadan (Akobo tribe)",
        'place_of_work' => 'Sports Centre',
        'start_of_work' => 'Saturdays, 6:30 AM',
        'end_of_work' => '9:00 AM',
    ],

    /*
    |--------------------------------------------------------------------------
    | Silent Disco Run
    |--------------------------------------------------------------------------
    |
    | The night run with wireless headsets: everyone runs on one of three
    | DJ channels, then the run ends in a rave. Until the date is fixed,
    | leave starts_at null and the page shows the date window instead.
    |
    | Payment: set checkout_url to any payment link (a Selar, Paystack or
    | Flutterwave product page) for the "Get your ticket" button. To show
    | a checkout widget on the page instead, set both embed_script and
    | embed_product. With neither set, the page says tickets open soon.
    |
    */

    'disco_run' => [
        'title' => 'Ibadan Silent Disco Run',
        'starts_at' => null,
        'timezone' => 'Africa/Lagos',
        'date_window' => 'First two weeks of December',
        'distance' => '5 km',
        'venue' => null,
        'price' => null,
        'includes' => [
            'A wireless LED headset for the night',
            'Three live DJ channels to switch between',
            'Pacers and sweepers so nobody runs alone',
            'Water on the route',
            'The rave after the run',
        ],
        'channels' => [
            ['name' => 'Afro Heat', 'genre' => 'Afrobeats · Street pop', 'tone' => 'coral'],
            ['name' => 'Piano Hours', 'genre' => 'Amapiano · Log drums', 'tone' => 'green'],
            ['name' => 'Rewind', 'genre' => 'Throwbacks · 2000s jams', 'tone' => 'sky'],
        ],
        'checkout_url' => env('DISCO_RUN_CHECKOUT_URL'),
        'embed_script' => env('DISCO_RUN_EMBED_SCRIPT'),
        'embed_product' => env('DISCO_RUN_EMBED_PRODUCT'),
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
        'https://www.instagram.com/p/DfPguYwjCyf/',
        'https://www.instagram.com/reel/DfPFIpiOZ2t/',
        'https://www.instagram.com/reel/DfNOY9kMrQF/',
        'https://www.instagram.com/p/DfMirsbCFD8/',
        'https://www.instagram.com/p/DfK-6moDpzV/',
        'https://www.instagram.com/reel/DfK614su9p5/',
        'https://www.instagram.com/p/DfKdOldjCid/',
        'https://www.instagram.com/p/DfKEg9-jGRq/',
        'https://www.instagram.com/reel/De_gkvvuSsW/',
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

    /*
    |--------------------------------------------------------------------------
    | Pace Setters
    |--------------------------------------------------------------------------
    |
    | The club's leaders, shown on the pace setters page. Each person's group
    | is "Club-wide" for leaders of the whole club, or one of the tribes
    | above; the page shows Club-wide first, then the tribes in order, and
    | hides any group with nobody in it. A photo is a path under public/,
    | such as images/pace-setters/name.jpg (portrait, about 4:5); leave it
    | null to show initials. Instagram is the handle without the @, or null.
    | Group lines are optional one-liners shown under each group's name.
    |
    | PLACEHOLDER: every person below is a stand-in until the real names,
    | roles, photos and handles arrive.
    |
    */

    'pace_setters' => [
        'groups' => [
            'Club-wide' => 'Lead the community runs, plan the hangouts and keep all four tribes moving together.',
            'Akobo' => 'Saturday mornings from the Barracks gate.',
            'Bodija' => 'Loops past Bodija Market, Sango and the Secretariat.',
            'UI / Eleyele' => 'Home of the Thursday track session.',
            'Oluyole / Akala' => 'The south side, carpools included.',
        ],

        'people' => [
            [
                'name' => 'Leader A',
                'role' => 'Club captain',
                'group' => 'Club-wide',
                'bio' => 'Placeholder line. What they look after across the club.',
                'photo' => null,
                'instagram' => null,
            ],
            [
                'name' => 'Leader B',
                'role' => 'Run director',
                'group' => 'Club-wide',
                'bio' => 'Placeholder line. What they look after across the club.',
                'photo' => null,
                'instagram' => null,
            ],
            [
                'name' => 'Leader C',
                'role' => 'Community lead',
                'group' => 'Club-wide',
                'bio' => 'Placeholder line. What they look after across the club.',
                'photo' => null,
                'instagram' => null,
            ],
            [
                'name' => 'Leader D',
                'role' => 'Tribe lead',
                'group' => 'Akobo',
                'bio' => 'Placeholder line. One sentence on how they lead the tribe.',
                'photo' => null,
                'instagram' => null,
            ],
            [
                'name' => 'Leader E',
                'role' => 'Pacer',
                'group' => 'Akobo',
                'bio' => 'Placeholder line. One sentence on how they lead the tribe.',
                'photo' => null,
                'instagram' => null,
            ],
            [
                'name' => 'Leader F',
                'role' => 'Sweeper',
                'group' => 'Akobo',
                'bio' => 'Placeholder line. One sentence on how they lead the tribe.',
                'photo' => null,
                'instagram' => null,
            ],
            [
                'name' => 'Leader G',
                'role' => 'Tribe lead',
                'group' => 'Bodija',
                'bio' => 'Placeholder line. One sentence on how they lead the tribe.',
                'photo' => null,
                'instagram' => null,
            ],
            [
                'name' => 'Leader H',
                'role' => 'Pacer',
                'group' => 'Bodija',
                'bio' => 'Placeholder line. One sentence on how they lead the tribe.',
                'photo' => null,
                'instagram' => null,
            ],
            [
                'name' => 'Leader I',
                'role' => 'Tribe lead',
                'group' => 'UI / Eleyele',
                'bio' => 'Placeholder line. One sentence on how they lead the tribe.',
                'photo' => null,
                'instagram' => null,
            ],
            [
                'name' => 'Leader J',
                'role' => 'Track coach',
                'group' => 'UI / Eleyele',
                'bio' => 'Placeholder line. One sentence on how they lead the tribe.',
                'photo' => null,
                'instagram' => null,
            ],
            [
                'name' => 'Leader K',
                'role' => 'Pacer',
                'group' => 'UI / Eleyele',
                'bio' => 'Placeholder line. One sentence on how they lead the tribe.',
                'photo' => null,
                'instagram' => null,
            ],
            [
                'name' => 'Leader L',
                'role' => 'Tribe lead',
                'group' => 'Oluyole / Akala',
                'bio' => 'Placeholder line. One sentence on how they lead the tribe.',
                'photo' => null,
                'instagram' => null,
            ],
        ],
    ],

];
