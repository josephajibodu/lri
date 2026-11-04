<p align="center"><img src="public/favicon.svg" width="96" alt="Let's Run Ibadan logo"></p>

<h1 align="center">Let's Run Ibadan</h1>

<p align="center">Everyone. Every pace. One community.</p>

The website for Let's Run Ibadan (LRI), a running community of runners, joggers and walkers in local tribes across Ibadan, Oyo State. It is a single page that tells new runners when and where we meet, shows a live countdown to the next community run, and links to the member form and our Instagram.

## What's on the page

- A hero with draggable stickers and a top bar announcing the next run
- A race-bib countdown to the next community run
- A pace slider that shows your 5 km and 10 km times
- The weekly schedule: Tuesday, Thursday and Saturday tribe runs, plus the first Saturday of the month when every tribe runs together
- The four tribes on a compass: Akobo, Bodija, UI / Eleyele and Oluyole / Akala
- Routes from past community runs, drawn on a running track
- Events we have hosted or partnered on this year
- A first-Saturday guide with a kit checklist
- How to join, an FAQ, and a footer wordmark that stretches as you move across it

## Tech stack

- [Laravel 13](https://laravel.com/docs) on PHP 8.4
- Blade layouts, components and partials
- Vite builds `resources/css/app.css` and `resources/js/app.js`. Tailwind CSS 4 is imported, but the page styles are plain CSS in `app.css`
- Vanilla JavaScript for the countdown, stickers, pace slider, route map, kit check and footer animation
- [Filament](https://filamentphp.com) for the admin, [Bachs](https://docs.bachs.io) for ticket payments and [Mailchimp](https://mailchimp.com) for follow-up emails
- [Pest](https://pestphp.com) for tests and [Pint](https://laravel.com/docs/pint) for code style
- Anybody, Instrument Sans and Martian Mono, self-hosted in `resources/fonts` (Latin and Latin Extended subsets from Google Fonts) with their variable width axis, which the stretch animations need. The layout preloads Instrument Sans, the font of the headline and body text

## Getting started

You need PHP 8.4, Composer, Node.js, npm and MySQL. Create the database named by `DB_DATABASE` in `.env.example` first.

```bash
git clone https://github.com/josephajibodu/lri.git
cd lri
composer run setup
composer run dev
```

`composer run setup` installs the PHP and JavaScript dependencies, creates `.env` and the app key, runs the migrations and builds the assets. `composer run dev` starts the app and the Vite dev server. With [Laravel Herd](https://herd.laravel.com), the site is also available at `http://lri.test`.

The app uses the database for sessions and cache, so the page returns a 500 error until the database exists. If that happens, run `php artisan migrate`.

## Updating the site

### The next community run

Edit `next_run` in `config/lri.php` whenever a new run is announced. The top bar, the countdown card, the live countdown and the event listing search engines read all come from it.

| Key | What it controls |
| --- | --- |
| `title` | The run's name on the countdown card |
| `starts_at` | Start date and time in local time, for example `2026-10-31 06:30` |
| `timezone` | The timezone for `starts_at`. Ibadan is `Africa/Lagos` |
| `venue` | Venue name, shown in the top bar and on the card |
| `address` | Street address, shown after the venue on the card |
| `map_url` | The link behind the "Open in Maps" button |
| `note` | The line under the countdown |

Once the start time passes, the countdown shows "Last community run" until you set the next one. On a server where the config is cached, run `php artisan optimize` after changing this file.

### Links

`links` in `config/lri.php` holds the Instagram, TikTok and Strava accounts, the member registration form and the Run the Elements challenge. Every button and link on the page uses these values, and the three social accounts are also listed in the structured data so search engines connect them to the club.

### Instagram highlights

`instagram_posts` in `config/lri.php` lists the posts and reels shown in the scrolling "Fresh from the road" section, in order. To feature a post, open it on Instagram, choose "Copy link" and paste the link into the list. Posts must be public. Leave the list empty to hide the section.

The section uses Instagram's official embeds, so each card shows the real post or reel. Instagram's embed script only loads once a visitor scrolls near the section, which keeps the page fast. If a visitor's browser blocks Instagram, each card falls back to a "View this post on Instagram" link.

### Search and link previews

`site` in `config/lri.php` controls how the club shows up in search results and link previews:

| Key | What it controls |
| --- | --- |
| `name`, `alternate_name` | The club's name and short name in search results |
| `title` | The page title in search results and browser tabs. Keep it under about 60 characters |
| `description` | The snippet under the title in search results and link previews. Keep it under about 160 characters |
| `slogan`, `locality`, `region`, `country` | The club details in the structured data |

The site also serves:

- **Structured data** on the home page (schema.org JSON-LD), describing the club, the website and the next run as a free event. The run is listed only until it starts.
- **`/sitemap.xml`** listing the pages to index, and **`/robots.txt`** pointing crawlers to it.
- **A link preview image** at `public/images/og-image.png`, used by WhatsApp, Instagram, X and others.
- **Icons:** `favicon.ico`, `favicon.svg`, `apple-touch-icon.png` and the `site.webmanifest` icons in `public/`.
- **`noindex` on error pages**, so they never appear in search results.

All of these build their links from the address the site is served on, and use `https` when `APP_ENV=production`. Set `APP_URL` to the live address too.

### Page content

Each section of the page is its own Blade file:

| Section | File |
| --- | --- |
| Top bar | `resources/views/components/site/topbar.blade.php` |
| Header and navigation | `resources/views/components/site/header.blade.php` |
| Hero and stickers | `resources/views/home/hero.blade.php` |
| Next run countdown | `resources/views/home/next-run.blade.php` |
| Scrolling banner | `resources/views/home/marquee.blade.php` |
| Pace slider | `resources/views/home/pace.blade.php` |
| Weekly schedule | `resources/views/home/schedule.blade.php` |
| Tribes and compass | `resources/views/home/tribes.blade.php` |
| Route map | `resources/views/home/routes.blade.php` |
| Events | `resources/views/home/events.blade.php` |
| First Saturday and kit check | `resources/views/home/first-run.blade.php` |
| Beyond the run | `resources/views/home/community.blade.php` |
| Instagram highlights | `resources/views/home/instagram.blade.php` |
| Join | `resources/views/home/join.blade.php` |
| FAQ | `resources/views/home/faq.blade.php` |
| Footer | `resources/views/components/site/footer.blade.php` |

`resources/views/home.blade.php` puts the sections together, and `resources/views/components/layouts/app.blade.php` holds the page `<head>`. The stops for each route on the route map live in the `routes` object in `resources/js/app.js`.

### Brand and styles

The colours are CSS custom properties at the top of the page styles in `resources/css/app.css`. The main ones are `--yellow` (`#FEB203`, the LRI yellow), `--bg` (the off-white base) and `--ink` (text). Yellow is never used for text on the light background, because it is hard to read there. Text sits on a yellow block instead, as in the hero and the section headings.

The logo is an SVG redraw of the LRI logo in `resources/views/components/logo.blade.php` (full logo), `resources/views/components/logo-mark.blade.php` (the LRI mark) and `public/favicon.svg`. Replace them with the original artwork when it is available.

## Ticket payments

Silent Disco Run tickets are sold on the site, paid through [Bachs](https://docs.bachs.io). Organisers manage sales in the admin at `/admin`.

### How a purchase works

1. The buyer fills in the ticket form and picks a way to pay. Their tickets are held for 60 minutes (`PAYMENTS_CHECKOUT_EXPIRES_IN`).
2. Bachs's checkout opens as a pop-up over the page. If the pop-up can't load, the buyer goes to Bachs's own payment page instead.
3. Bachs confirms the payment with a webhook, a notification it sends to our server. As a backup, the order page asks Bachs directly while the buyer waits, and `payments:reconcile` asks every 5 minutes.
4. Once it's paid, the buyer moves on to their receipt at `/receipts/{code}`, an address they can always come back to, and their tickets are emailed from the queue.
5. An order that isn't paid is marked abandoned about 10 minutes after its hold runs out, and its tickets go back on sale. The buyer can still pay from their order page while tickets are left.

### Try it locally

There's no fake payment mode. Local purchases go through the Bachs sandbox, so no real money moves.

1. Get sandbox keys from the Bachs developer portal and add them to `.env`. A key starting `sk_sandbox_` talks to the sandbox, and one starting `sk_live_` takes real money, so never use a live key locally.

   ```bash
   BACHS_SECRET_KEY=sk_sandbox_...
   BACHS_WEBHOOK_SECRET=whsec_...
   ```

2. With Herd, set `APP_URL=https://lri.test`. Links made in the background are signed for the exact address, so `http` links stop working when Herd moves the browser to `https`.
3. Add the ticket and the ways to pay, match them to what's switched on at Bachs, and create an admin login:

   ```bash
   php artisan db:seed --class=TicketTypeSeeder
   php artisan db:seed --class=PaymentChannelSeeder
   php artisan payments:sync-channels
   php artisan make:filament-user
   php artisan permission:assign-role "Super admin" 1
   ```

   The last line makes that login a super admin. Use its id if it isn't 1. A login with no role can't get into the admin.

4. Run `composer run dev`, which includes the queue worker that sends ticket emails. It doesn't run the scheduler, so run `php artisan schedule:work` as well if you want the 5-minute checks. Emails go to `storage/logs/laravel.log` until a mail service is set up.
5. Buy a ticket at `/disco-run`, then look at the order in `/admin`.

Bachs can't reach `lri.test`, so webhooks don't arrive locally, but purchases are still confirmed by the order page asking Bachs. To try real webhooks, run `herd share` to get a temporary public address and add it as a webhook destination in the Bachs portal.

### Bachs webhooks

Add `https://<your-domain>/webhooks/payments/bachs` as a webhook destination in the Bachs developer portal, and put its signing secret in `BACHS_WEBHOOK_SECRET`. A webhook without a valid signature is refused. The site acts on these events and stores any others without acting on them:

| Event | What it does |
| --- | --- |
| `collection.succeeded` | Pays the order, issues its tickets and emails them |
| `checkout.completed` | Pays the order, if Bachs says it's paid. It can arrive before or after `collection.succeeded`, and an order is only fulfilled once |
| `collection.failed` | Records the failed attempt and why it failed |
| `collection.underpaid` | Holds the order back without issuing tickets |
| `checkout.expired` | Records that the checkout closed |

### The admin

Sign in at `/admin`. Each person sees only the sections their roles allow, and lands on the first one they can see. Roles are set up under **Settings > Roles** by ticking the sections a role can see, and given out under **Settings > People**. The site starts with three roles:

- **Super admin** sees everything, including sections added later. It can't be edited, and only a super admin can make someone else one.
- **Admin** starts with every section, but can be changed.
- **Headset desk** sees only the headset desk.

| Section | What it's for |
| --- | --- |
| Dashboard | Sales, tickets per day, the checkout funnel, how each way to pay performs and where buyers come from |
| Headset desk | On the day: find a ticket, fill in a missing name and email, and hand over one headset per paid ticket |
| Orders | Search and download orders, see each buyer's payment attempts and journey, check a payment with Bachs and email tickets again |
| Ticket types | Prices in naira and the ticket limit, which is the number of headsets |
| Ways to pay | Switch methods on or off, feature them at checkout and reorder them |
| System > Webhooks | What Bachs sent, with the option to handle a failed one again |
| System > Email tool | What Mailchimp was told, with the option to send a failed one again |
| Settings > People and Roles | Who can sign in, and which sections each role sees |
| `/log-viewer` | The application logs |

### Commands

| Command | What it does |
| --- | --- |
| `php artisan payments:sync-channels` | Matches the ways to pay to what's switched on at Bachs |
| `php artisan payments:reconcile` | Asks Bachs about open payments, in case a webhook went missing. Scheduled every 5 minutes |
| `php artisan orders:abandon-unpaid` | Marks orders abandoned once their hold has run out. Scheduled every 5 minutes |
| `php artisan payments:replay-webhook {id}` | Handles a stored webhook again. The id is its row in `webhook_calls` |
| `php artisan email-tools:prepare` | Adds the audience fields Mailchimp needs. Safe to run again |

### Follow-up emails with Mailchimp

The site tells Mailchimp when a buyer starts, abandons or pays for a checkout, and the emails themselves are built in Mailchimp. Nothing is sent until `EMAIL_TOOL` is set, and only buyers who ticked the consent box on the ticket form are ever sent.

1. In Mailchimp, create an API key (profile icon > Extras > API keys) and copy your audience ID (Audience > More options > Audience settings).
2. Add them to `.env`:

   ```bash
   EMAIL_TOOL=mailchimp
   MAILCHIMP_API_KEY=...
   MAILCHIMP_AUDIENCE_ID=...
   ```

3. Add the audience fields:

   ```bash
   php artisan email-tools:prepare
   ```

4. Build a Mailchimp automation flow that starts when the tag `checkout-abandoned` is added, and link its button to `*|RESUMEURL|*`.
5. Make the flow stop for anyone who gets the tag `ticket-paid`, so paid buyers aren't chased. Check how your Mailchimp plan handles this.
6. Try it with your own email address before turning it on for buyers, then check **System > Email tool** in the admin.

Each update sets the contact's name, email and order number, and one of these tags, taking off the other two:

| Tag | Added when | Resume link (`RESUMEURL`) |
| --- | --- | --- |
| `checkout-started` | The buyer submits the ticket form | Set |
| `checkout-abandoned` | The order is abandoned | Set |
| `ticket-paid` | The order is paid | Cleared |

The resume link opens the buyer's order page, where their details are saved and they can pick a way to pay. Phone numbers aren't sent. Someone already in the audience keeps their subscription status, so a buyer who unsubscribed isn't signed back up.

Sending runs on the queue, so Mailchimp being slow or down can't hold up checkout. Outages are retried for about 80 minutes. A refusal fails straight away and shows Mailchimp's reason in the admin, where you can send it again.

## Testing

```bash
php artisan test --compact
vendor/bin/pint --dirty
```

`tests/Feature/HomePageTest.php` checks that the page shows the next run and the links from `config/lri.php`. Tests run without built assets because `tests/TestCase.php` turns Vite off. They never reach Bachs or Mailchimp: `phpunit.xml` blanks the keys, and the tests fake both APIs.

## Deployment

The site runs on any host with PHP 8.4, including [Laravel Cloud](https://cloud.laravel.com). A typical deploy:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize
php artisan queue:restart
```

In production, set `APP_ENV=production`, `APP_DEBUG=false` and `APP_URL` to the site's address in `.env`.

### Going live with ticket sales

These only need doing once. `TICKET_PAYMENTS_PLAN.md` has the full checklist.

1. Set the live details in `.env`: the MySQL `DB_*` settings, `BACHS_SECRET_KEY` (`sk_live_...`) and `BACHS_WEBHOOK_SECRET`.
2. Bring across the birthdays, barracks registrations and organiser logins saved before the switch, which are still in the old SQLite file. Keep a copy of the file first. Then, with `.env` already pointing at MySQL and the migrations run, see what would be copied and copy it:

   ```bash
   cp database/database.sqlite ~/lri-before-mysql.sqlite
   php artisan signups:import-sqlite --dry-run
   php artisan signups:import-sqlite
   ```

   The photos and passports stay where they are on disk; only their records move. Anyone already in MySQL (matched by email, or phone for barracks) is skipped, so running it twice is safe. Organisers then find both lists under **Club** in the admin.
3. Choose a mail service and set `MAIL_MAILER` and its settings. Until then, ticket emails only go to the log file and buyers get nothing.
4. Keep a queue worker running. It sends the ticket emails and the Mailchimp updates:

   ```bash
   php artisan queue:work
   ```

5. Run the scheduler every minute, which runs the 5-minute payment checks:

   ```bash
   * * * * * cd /path/to/lri && php artisan schedule:run >> /dev/null 2>&1
   ```

6. Add the [webhook address](#bachs-webhooks) in the Bachs developer portal.
7. Add the ticket and the ways to pay, then create the first organiser and make them a super admin (use their id if it isn't 1):

   ```bash
   php artisan db:seed --class=TicketTypeSeeder --force
   php artisan db:seed --class=PaymentChannelSeeder --force
   php artisan payments:sync-channels
   php artisan make:filament-user
   php artisan permission:assign-role "Super admin" 1
   ```

8. Set the ticket limit (the number of headsets) under **Ticket types** in the admin.
9. If you're using follow-up emails, [connect Mailchimp](#follow-up-emails-with-mailchimp).

## Community

- Instagram: [@letsrunibadan](https://www.instagram.com/letsrunibadan/)
- TikTok: [@lri.letsrunibadan](https://www.tiktok.com/@lri.letsrunibadan)
- Strava: [LRI club](https://www.strava.com/clubs/1955817)
- Become a member: [member registration form](https://docs.google.com/forms/d/e/1FAIpQLScBqAfuSOLlA2YSUpJ9XBdNeo9MVbmwh1gQWNr8z_BMtIb1QA/viewform)
