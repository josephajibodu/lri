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
- [Filament](https://filamentphp.com) for the admin, with [Spatie's laravel-permission](https://spatie.be/docs/laravel-permission) for its roles, [Bachs](https://docs.bachs.io) for ticket payments and [Mailchimp](https://mailchimp.com) for follow-up emails
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

The colours are CSS custom properties at the top of the page styles in `resources/css/app.css`. The main ones are `--yellow` (`#FFB000`, the LRI orange), `--bg` (the off-white base) and `--ink` (text). Yellow is never used for text on the light background, because it is hard to read there. Text sits on a yellow block instead, as in the hero and the section headings.

The admin uses the same colours, and follows the same rule: its yellow buttons have dark text. The shades of yellow are in `app/Providers/Filament/AdminPanelProvider.php`. The background, the yellow stripe and the other small touches are in `resources/views/filament/brand-styles.blade.php`.

The logo is an SVG redraw of the LRI logo in `resources/views/components/logo.blade.php` (full logo), `resources/views/components/logo-mark.blade.php` (the LRI mark) and `public/favicon.svg`. Replace them with the original artwork when it is available.

## Ticket payments

Silent Disco Run tickets are sold on the site, paid through [Bachs](https://docs.bachs.io). Organisers manage sales in the admin at `/admin`.

### How a purchase works

1. The buyer fills in the ticket form and picks a way to pay. Their tickets are held for 60 minutes (`PAYMENTS_CHECKOUT_EXPIRES_IN`).
2. Bachs's checkout opens as a pop-up over the page. If the pop-up can't load, the buyer goes to Bachs's own payment page instead.
3. Bachs confirms the payment with a webhook, a notification it sends to our server. As a backup, the order page asks Bachs directly while the buyer waits, and `payments:reconcile` asks every 5 minutes.
4. Once it's paid, the buyer moves on to their receipt at `/receipts/{code}`, an address they can always come back to, and their tickets are emailed from the queue. The first ticket already carries the buyer's name and email.
5. An order that isn't paid is marked abandoned about 10 minutes after its hold runs out, and its tickets go back on sale. The buyer can still pay from their order page while tickets are left, at the ticket's price that day.

### Coupons

Organisers make coupons in the admin under Tickets > Coupons. Buyers tap "Have a coupon?" on the ticket form, type the code and press Apply, and the total drops before they choose how to pay.

- **What it takes off:** a percentage (up to 99%) or a naira amount, off the order's total.
- **Limits you can set:** when it starts and ends, how many orders can use it, how many tickets it covers in one order (any more are full price; percentage coupons only), and which tickets it works on.
- **A coupon can't be edited.** Once it's made, it can only be disabled, or turned back on. Anyone with a checkout already open can still pay that one at the discount, but if they start another it goes back to full price, and they're told before they're asked to pay.
- **The buyer always pays at least ₦100** (`payments.minimum_charge`), because Bachs refuses anything less. So a coupon can't make a ticket free.
- **What counts as a use:** paid orders and orders still being paid for. An abandoned or refunded order gives its use back. On a coupon with an order limit, one email or connection can only hold one unpaid use at a time, so unpaid orders can't use it all up.
- **Coming back to an abandoned order:** the coupon is checked again. If it no longer works, the order goes back to full price and the buyer is told before they're asked to pay.

The coupon's code and what it took off show on the order page, the receipt, the tickets email, the order in the admin and the CSV download. To see every order that used a coupon, press Orders on its row.

**To try a live sale cheaply:** make a coupon for ₦10,000 off, switch on "Limit how many orders can use it" and set it to 1, then buy one ticket with it. You pay a real ₦100. Disable the coupon afterwards.

### A name on every ticket

There is one ticket type at one price. Each ticket is for one person, and each person picks up their own headset, so every ticket needs that person's name and email. The buyer only enters their own details at checkout; the other tickets are named after paying:

- **Type details.** The buyer types the friend's name and email on the receipt.
- **Send link.** The buyer sends the ticket's own link (the share sheet on phones, otherwise it's copied). The friend opens `/tickets/{secret}`, fills in their name and email, and from then on the same link shows their ticket and QR code. The page shows only that one ticket: no other tickets, no price and nothing about the buyer. A ticket that already has a name can't be taken over through its link.
- **At the desk on the day.** Anything still blank can be filled in at the [headset desk](#on-the-day-the-headset-desk).

Every time a ticket is named or its details are fixed, that person is emailed their own ticket with its QR code and link. The buyer isn't emailed again for their own ticket. Friends are never sent to Mailchimp, because they didn't tick the consent box; their email is only used for their ticket.

The receipt lists every ticket with where it stands and what the buyer can do:

| Status | Means | Buttons |
| --- | --- | --- |
| Yours | The buyer's own ticket, with its QR code | |
| Emailed | Named, and that person has their ticket | **Edit** fixes a typo in the name and emails it again with the same code. Changing the email gives it a new code and link, so whoever had it before can't use it or see who has it now. **Reissue** gives it a new code and link, so the old QR code, link and any screenshots stop working; it then needs naming again |
| No name yet | Blank | **Type details**, **Send link** |
| Link sent | The link went out, but nobody has filled it in | **Copy link**, **Cancel link** (the old link stops working) |
| Headset collected | Used | None: a collected ticket can't be changed |

Only the buyer has these buttons. Whoever receives a ticket can't pass it on or reissue it.

### QR codes

Every ticket has its own QR code, never one per order, with the printed code (like `7K3Q-9PXM`) underneath to type if a scan fails. It's on the ticket page, on the receipt for the buyer's own ticket, and in the ticket emails, where it's attached as an image inside the email because Gmail and others block other kinds of image. The QR code holds a link to the headset desk with that code searched (`/admin/headset-desk?search=7K3Q-9PXM`), so desk staff scan it with their phone's normal camera. Anyone else who scans it only reaches the admin sign-in. The codes are made with `chillerlan/php-qrcode`; emails need PHP's GD extension to draw them.

### On the day: the headset desk

The headset desk is a page in the admin (**Tickets > Headset desk**, at `/admin/headset-desk`) that works on a phone. Give each person working the desk a login with the **Headset desk** role, and they'll see nothing else in the admin.

The desk is hidden for now: only super admins can see it, and anyone else who scans a QR code is refused. To give it back to every role with **Headset desk** ticked, set `HEADSET_DESK_OPEN=true` in the environment and redeploy.

1. Scan their QR code with your phone's camera, which opens the desk on that ticket. If it won't scan, type the code under it, their name or email, or the name of whoever bought it. Codes work with or without the dash. Tickets are grouped by order, so searching for the buyer shows everyone they paid for.
2. Each ticket shows one of:
   - green **Paid, ready**: press **Give headset**;
   - red **Headset already given**, with when and by whom: don't give another;
   - amber **Needs a name**, with how many tickets on that order are unnamed: press **Save and give headset**, type in the ticket's code and the person's name and email, and they're emailed their ticket. A ticket with no name has nothing to check someone against, so its code is the proof: it's on their QR code, or the buyer can read it out from their email or receipt. Staff can't see it until it's searched for in full. No code, no headset;
   - **Payment disputed**: the buyer's bank is disputing the payment, so don't hand over a headset;
   - **Test payment**: the order was marked as a test, so don't hand over a headset;
   - **Not paid**: the order isn't paid, or was refunded.
3. **Change name** fixes a typo on a named ticket before the headset goes out.

Each paid ticket gets one headset, once. If two people at the desk press the button for the same ticket at the same moment, only one is recorded.

The count at the top of the page shows how many headsets have been handed out against the number of paid tickets.

### Try it locally

There's no fake payment mode. Local purchases go through the Bachs sandbox, so no real money moves.

1. Get sandbox keys from the Bachs developer portal and add them to `.env`. A key starting `sk_sandbox_` talks to the sandbox, and one starting `sk_live_` takes real money, so never use a live key locally.

   ```bash
   BACHS_SECRET_KEY=sk_sandbox_...
   BACHS_WEBHOOK_SECRET=whsec_...
   ```

2. With Herd, set `APP_URL=https://lri.test`. Links made in the background are signed for the exact address, so `http` links stop working when Herd moves the browser to `https`.
3. Add the default admin, the ticket and the ways to pay, then match them to what's switched on at Bachs:

   ```bash
   php artisan db:seed
   php artisan payments:sync-channels
   ```

   This creates `joseph@gmail.com` as a super admin and shows its password once, so save it. See [The first login](#the-first-login) for choosing the password or using a different login.

4. Run `composer run dev`, which includes the queue worker that sends ticket emails. It doesn't run the scheduler, so run `php artisan schedule:work` as well if you want the 5-minute checks. Emails go to `storage/logs/laravel.log` until a mail service is set up.
5. Buy a ticket at `/silent-disco-run`, then look at the order in `/admin`.

Bachs can't reach `lri.test`, so webhooks don't arrive locally, but purchases are still confirmed by the order page asking Bachs. To try real webhooks, run `herd share` to get a temporary public address and add it as a webhook destination in the Bachs portal.

### Bachs webhooks

Add `https://<your-domain>/webhooks/payments/bachs` as a webhook destination in the Bachs developer portal, and put its signing secret in `BACHS_WEBHOOK_SECRET`. A webhook without a valid signature is refused. **Subscribe the webhook to the refund and dispute events too**, or refunded tickets keep working. The site acts on these events and stores any others without acting on them:

| Event | What it does |
| --- | --- |
| `collection.succeeded` | Pays the order, issues its tickets and emails them |
| `checkout.completed` | Pays the order, if Bachs says it's paid. It can arrive before or after `collection.succeeded`, and an order is only fulfilled once |
| `collection.failed` | Records why the try failed. The checkout stays open, so the buyer can try again on it |
| `collection.underpaid` | Holds the order back without issuing tickets |
| `checkout.expired` | Records that the checkout closed |
| `refund.paid` | Cancels the order's tickets if all the money went back. A partial refund keeps them working and flags the order |
| `dispute.created`, `dispute.updated` | Puts the order's tickets on hold straight away. Winning brings them back, losing cancels them |

### When money goes back

Tickets only work while the order is paid for.

- **Full refund:** the tickets stop working, and their places and coupon use go back on offer. Getting back everything except Bachs's fee counts as full. An order paid for twice keeps its tickets when only the extra payment is refunded.
- **Partial refund:** the tickets keep working, and the order is flagged for an organiser to decide.
- **Chargeback:** when the buyer's bank disputes a payment, the tickets go on hold at once and keep their places. Winning makes them work again, and losing counts as a refund. If a dispute closes without a ruling, an organiser presses **Release tickets** on the order once Bachs confirms the money is staying.
- **A payment that lands late** is honoured, since the buyer has paid, even if the event has since sold out or the coupon has run out. The order is flagged so an organiser can decide whether to refund it.

### Test payments

A payment made to try out checkout is still a real payment, so it isn't deleted. Open the order in **Tickets > Orders** and press **Mark as test payment**. Its tickets stop working (the ticket link stops opening and the headset desk shows **Test payment**), their places and coupon use go back on offer, and it leaves the sales figures. The order keeps its status, who marked it and when, and its payment stays on record. No money is refunded, and if you refund it in Bachs later it stays a test payment. An order with a collected headset can't be marked.

Orders that need a person show **Needs a look** in **Tickets > Orders**, with a count in the menu, a filter, and the reason on the order. **Mark as looked at** clears the note without changing anything else.

### Limits on unpaid orders

Holding tickets costs nothing, so `payments.unpaid_limits` in `config/payments.php` stops one person holding them all without paying:

- One email can hold 20 unpaid tickets and one connection (IP address) can hold 40. Someone holding none can always place one order, however big it's allowed to be.
- An order gets 5 tries at paying, since each one keeps its tickets held for longer. After that the buyer starts a new order.
- Switching sales off also stops unpaid orders opening new checkouts.

### The bot check

Those limits go by email and internet address, and both are easy to change, so a script could still reserve a lot of tickets without paying. To stop that, the ticket form uses [Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/), a free check that a real browser is placing the order. Buyers usually see nothing. Now and then, Cloudflare asks them to click a box.

It's off until `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET_KEY` are set, which is how it stays out of the way locally. To turn it on, add a widget in the Cloudflare dashboard under Turnstile, in Managed mode, with your domain as its hostname, and put its two keys in `.env`.

- **Failing the check:** the order is refused, and the buyer is asked to try again.
- **If Cloudflare's server can't answer, or the secret key is wrong:** the order goes through and the problem is reported in the logs, so that kind of outage doesn't stop sales.
- **If the check can't load in buyers' browsers, or the site key is wrong:** nobody can order, because the site can't tell that apart from a script skipping the check on purpose. So after setting the keys, buy a ticket on the live site to check it. If this happens during a sale, empty both keys in `.env` and run `php artisan optimize`, which switches the check off.
- **Buyers need JavaScript on,** and a strict ad blocker can stop the check loading. They're told either way.

To try it locally, use [Cloudflare's test keys](https://developers.cloudflare.com/turnstile/troubleshooting/testing/). Remove them afterwards.

### The admin

Everyone signs in at `/admin`. Each person sees only the sections their roles allow: the rest are missing from their menu, and their addresses are refused. `/admin` takes each person to the first section they can see, so desk staff land straight on the desk and everyone else on the dashboard (`/admin/dashboard`). Someone whose roles don't show any section is refused.

Roles are set up under **Settings > Roles** by ticking the sections a role can see, and given out under **Settings > People**. Someone can have more than one role, and someone with no role can't sign in. The site starts with four roles:

- **Super admin** sees everything, including sections added later. It can't be edited, and only a super admin can make someone else one, or change or remove one.
- **Admin** starts with every section except System, but can be changed. A section added later isn't ticked for it automatically.
- **Developer** sees only what's under System: webhooks, the email tool and the logs. Anyone else needs those sections ticked on one of their roles.
- **Headset desk** sees only the headset desk, while [it's open](#on-the-day-the-headset-desk).

Nobody can change their own roles or remove their own login, so nobody locks themselves out. A role still given to someone can't be removed. **People and roles** lets someone give anyone any role except Super admin, so only tick it for people you trust with everything.

### The first login

The database starts with no logins, so one has to be made from the terminal. There are two ways, and you only need one.

**Use the seeder.** This creates `joseph@gmail.com` as a super admin. It doesn't use factories, so it works on a production install, and it's safe to run again: it won't change the password of a login that already exists.

```bash
php artisan db:seed --class=AdminUserSeeder --force
```

With no password set, it makes a random one and shows it once in the terminal, so save it. To choose the login yourself, set these in `.env` before running it. Take the password out of `.env` once you've signed in.

```bash
DEFAULT_ADMIN_EMAIL=joseph@gmail.com
DEFAULT_ADMIN_PASSWORD=choose-a-long-password
```

**Or use Filament's command**, which asks for a name, email and password. It makes a login with no role, and a login with no role can't sign in, so give it the Super admin role afterwards. Use the id the login got; `php artisan permission:show` lists who has which role.

```bash
php artisan make:filament-user
```

```bash
php artisan permission:assign-role "Super admin" 1
```

If every super admin is ever locked out, either way gets you back in with a new login.

### Inviting people

Everyone after the first login is best invited, so nobody has to send a password around. Anyone whose roles include **People and roles** can do it.

1. Go to **Settings > Invitations** and press **Invite someone**. The same button is on **Settings > People**.
2. Enter their email address and tick their roles. Only a super admin can invite another super admin.
3. They get an email with **Accept invitation** and **Decline this invitation** links.
4. If they accept, they add their name and choose a password of at least 12 characters, and they're signed straight in. If they decline, they're asked to confirm, and no login is made.

**Settings > Invitations** shows where each invitation stands, and the menu shows how many are waiting for a reply.

- **A link works for 24 hours and only once.** To change the time, edit `admin_invitations.lifetime_hours` in `config/lri.php`.
- **Send again** emails a new link with the full time again, and the old link stops working. Use it for an invitation that's waiting or has expired. For one that was declined or cancelled, invite them again.
- **Cancel** stops a waiting invitation's link straight away.
- **Opening a link changes nothing.** Mail apps and scanners open links on their own, so only pressing a button on the page accepts or declines.
- **Someone who already has a login can't be invited.** Change their roles under **Settings > People**. A role promised in a waiting invitation can't be removed until that invitation closes.
- **Invitation emails go through the queue**, so a queue worker has to be running and a mail service set up. If an email can't reach someone, **Add without an invite** on **Settings > People** still lets you set a password for them yourself.

To see the email while working locally, open `/mail-previews/admin-invitation`. Its links open sample versions of the accept and decline pages, with the buttons switched off. The real pages are normal site pages at `/invitations/{link}`, in `resources/views/admin-invitations/`.

It uses the club's official email look: a white card with the logo and a stripe of yellow. Use that for anything that isn't about an event, and leave the dark poster look to event emails. To use it on a new email, set `public $theme = 'official'` on the mailable and wrap its view in `<x-mail::official.message>`. The components are in `resources/views/vendor/mail/html/official/`, the colours in `themes/official.css`, and the plain-text versions in `resources/views/vendor/mail/text/official/`.

### What's in the admin

| Section | What it's for |
| --- | --- |
| Dashboard | Sales, tickets per day, the checkout funnel, how each way to pay performs and where buyers come from |
| Headset desk | On the day: find a ticket, fill in a missing name and email, and hand over one headset per paid ticket |
| Orders | Search and download orders, see each buyer's payment attempts and journey, check a payment with Bachs and email tickets again |
| Ticket types | Prices in naira and the ticket limit, which is the number of headsets |
| Coupons | Make coupons, see how much each has been used, and disable them. They can't be edited |
| Ways to pay | Switch methods on or off, feature them at checkout and reorder them |
| System > Webhooks | What Bachs sent, with the option to handle a failed one again |
| System > Email tool | What Mailchimp was told, with the option to send a failed one again |
| Club > Birthdays, Barracks permits | Birthdays members shared and barracks permit sign-ups (permits only while that registration is switched on) |
| Settings > People, Invitations and Roles | Who can sign in, who's been invited, and which sections each role sees |
| `/log-viewer` | The application logs |

### Commands

| Command | What it does |
| --- | --- |
| `php artisan payments:sync-channels` | Matches the ways to pay to what's switched on at Bachs |
| `php artisan payments:reconcile` | Asks Bachs about open payments, in case a webhook went missing. Scheduled every 5 minutes |
| `php artisan orders:abandon-unpaid` | Marks orders abandoned once their hold has run out. Scheduled every 5 minutes |
| `php artisan payments:replay-webhook {id}` | Handles a stored webhook again. The id is its row in `webhook_calls` |
| `php artisan email-tools:prepare` | Adds the audience fields Mailchimp needs. Safe to run again |
| `php artisan db:seed --class=AdminUserSeeder --force` | Creates the default admin, `joseph@gmail.com`, as a super admin. Safe to run again |
| `php artisan make:filament-user` | Creates a login from the terminal. It has no role until you give it one with the next command |
| `php artisan permission:assign-role "Super admin" {id}` | Gives a login a role. Use it for the first login, or if every super admin is locked out |
| `php artisan permission:show` | Lists the roles and what each one can see |
| `php artisan permission:cache-reset` | Clears the saved copy of the roles and permissions, if a change in the admin doesn't seem to take effect |

### Adding a section to the admin

Every section is guarded by a permission, so a new resource or page needs one too, or only super admins will see it:

1. Add a case to `app/Enums/AdminPermission.php`, with its label and description for the Roles screen.
2. Create it in the database with a migration, the way `database/migrations/2026_11_04_000457_create_admin_roles_and_permissions.php` does, then tick it for the roles that should see it.
3. Guard the section: for a resource, add a policy extending `app/Policies/AdminSectionPolicy.php` (Laravel finds `App\Policies\{Model}Policy` on its own); for a page, return `auth()->user()?->can(AdminPermission::YourCase->value)` from its `canAccess()`.

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
6. Try it before turning it on for buyers, using an email address that isn't in the audience yet, then check **System > Email tool** in the admin. You should get Mailchimp's confirmation email when you place the order. Also check whether confirming after the order has been abandoned still starts the flow, because this hasn't been tried on a real Mailchimp account.

Each update sets the contact's name, email and order number, and one of these tags, taking off the other two:

| Tag | Added when | Resume link (`RESUMEURL`) |
| --- | --- | --- |
| `checkout-started` | The buyer submits the ticket form | Set |
| `checkout-abandoned` | The order is abandoned | Set |
| `ticket-paid` | The order is paid | Cleared |

The resume link opens the buyer's order page, where their details are saved and they can pick a way to pay. Phone numbers aren't sent.

Anyone can type any email on the ticket form, so ticking the box on an unpaid order doesn't prove the address belongs to them. What happens to each contact depends on where they stand:

| Contact | What happens |
| --- | --- |
| New, on an unpaid order | Added as pending, not subscribed. Mailchimp sends one email asking them to confirm, and marketing emails wait until they do |
| New, on a paid order | Subscribed |
| Still pending, and pays | Subscribed, without having to confirm |
| Unsubscribed or bounced | Stays that way, even if they pay |

This means abandoned-checkout reminders only reach people who confirmed. Expect new buyers who tick the box to get the confirmation email when they place the order, even if they go on to pay straight away.

Sending runs on the queue, so Mailchimp being slow or down can't hold up checkout. Outages are retried for about 80 minutes. A refusal fails straight away and shows Mailchimp's reason in the admin, where you can send it again.

## Testing

```bash
php artisan test --compact
vendor/bin/pint --dirty
```

`tests/Feature/HomePageTest.php` checks that the page shows the next run and the links from `config/lri.php`. Tests run without built assets because `tests/TestCase.php` turns Vite off. They never reach Bachs or Mailchimp: `phpunit.xml` blanks the keys, and the tests fake both APIs.

## Deployment

The site runs on any host with PHP 8.4, including [Laravel Cloud](https://cloud.laravel.com). It needs:

- PHP 8.4 with the GD extension (`php -m | grep gd`), which draws the QR codes in ticket emails
- MySQL
- Composer, Node.js and npm, to install and build
- A way to keep a queue worker running and to run the scheduler every minute

### The first deploy

Do these once, in order, when the site goes live.

1. **Get the code and install it.**

   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci
   npm run build
   ```

2. **Create `.env`** from `.env.example` and fill it in:

   | Setting | Value |
   | --- | --- |
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false`, so visitors never see error details |
   | `APP_URL` | The exact live address, `https` included. Ticket links, receipt links and the links in emails are built from it |
   | `DB_*` | The live MySQL database. Create the empty database first |
   | `BACHS_SECRET_KEY` | The live key, starting `sk_live_`. With any other key, or a `BACHS_BASE_URL` other than the live one, the live site refuses to open checkouts, because anyone could get real tickets with test payments |
   | `BACHS_WEBHOOK_SECRET` | The signing secret of the webhook you add in step 9 |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | The keys of your Cloudflare Turnstile widget. Without them there's no [bot check](#the-bot-check) |
   | `MAIL_MAILER` and its settings | A real mail service. Until this is set, ticket emails only go to the log file and nobody gets their tickets |
   | `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Who ticket emails come from. Change it from `hello@example.com` |
   | `LOG_LEVEL` | `error` or `warning`, so the log doesn't fill up with detail |
   | `EMAIL_TOOL`, `MAILCHIMP_*` | Leave blank unless you're using [follow-up emails](#follow-up-emails-with-mailchimp) |
   | `BARRACKS_REGISTRATION_OPEN` | `false` unless the barracks permit sign-up is running |
   | `HEADSET_DESK_OPEN` | `false` keeps the headset desk to super admins. `true` gives it back to every role with it ticked |

3. **Make the app key.** It encrypts sessions and signs links. Run this once only: changing it later logs everyone out and breaks links already sent.

   ```bash
   php artisan key:generate --force
   ```

4. **Create the tables.** This builds every table, plus the admin's permissions and its four starting roles.

   ```bash
   php artisan migrate --force
   ```

   If migrations were ever run on this database before the migrations were tidied up, start it again from empty instead. **This deletes everything in the database**, so only do it while nothing real is in there:

   ```bash
   php artisan migrate:fresh --force
   ```

5. **Add the default admin, the ticket and the ways to pay**, and match them to what's switched on at Bachs:

   ```bash
   php artisan db:seed --force
   php artisan payments:sync-channels
   ```

   Run `db:seed` once, on the first deploy. Running all of it again puts the ticket's price and limit back to what's in `TicketTypeSeeder`, undoing any change made in the admin.

6. **Save the first login's password.** The seeder creates `joseph@gmail.com` as a super admin and shows its password once in the terminal. If your deploy tool keeps its output in logs, set `DEFAULT_ADMIN_PASSWORD` in `.env` first so the password isn't printed. [The first login](#the-first-login) covers this, and how to create the login with `php artisan make:filament-user` instead.

7. **Cache the config, routes and views** so the site runs faster:

   ```bash
   php artisan optimize
   ```

8. **Start the background work.**
   - Keep a queue worker running, with Supervisor or your host's worker setting. It sends the ticket emails and the Mailchimp updates, so without it nobody gets their tickets:

     ```bash
     php artisan queue:work
     ```

   - Run the scheduler every minute. It checks payments with Bachs and frees up unpaid tickets every 5 minutes, and clears out old barracks passport uploads daily:

     ```bash
     * * * * * cd /path/to/lri && php artisan schedule:run >> /dev/null 2>&1
     ```

9. **Add the webhook** at `https://<your-domain>/webhooks/payments/bachs` in the Bachs developer portal, subscribed to the checkout, collection, refund and dispute events, and put its signing secret in `BACHS_WEBHOOK_SECRET` (run `php artisan optimize` again after changing `.env`). See [Bachs webhooks](#bachs-webhooks).
10. **Finish in the admin** at `/admin`, signed in as the super admin:
    - Set the price and the ticket limit (the number of headsets) under **Ticket types**.
    - Check **Ways to pay** shows the methods you expect.
    - [Invite everyone else](#inviting-people) under **Settings > Invitations**. Before the event, invite each person working the headset desk with the **Headset desk** role.
11. **Try it for real.** Buy one ticket with a real payment, check it arrives by email with its QR code, and find it on the headset desk. `TICKET_PAYMENTS_PLAN.md` has the full go-live checklist.
12. If you're using follow-up emails, [connect Mailchimp](#follow-up-emails-with-mailchimp).

### Every deploy after that

```bash
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

`php artisan queue:restart` makes the queue worker pick up the new code once it finishes the job it's on. Never run `migrate:fresh` once real orders exist: it deletes them all. Changes to the database from now on go in new migrations.

## Community

- Instagram: [@letsrunibadan](https://www.instagram.com/letsrunibadan/)
- TikTok: [@lri.letsrunibadan](https://www.tiktok.com/@lri.letsrunibadan)
- Strava: [LRI club](https://www.strava.com/clubs/1955817)
- Become a member: [member registration form](https://docs.google.com/forms/d/e/1FAIpQLScBqAfuSOLlA2YSUpJ9XBdNeo9MVbmwh1gQWNr8z_BMtIb1QA/viewform)
