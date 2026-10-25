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
- [Pest](https://pestphp.com) for tests and [Pint](https://laravel.com/docs/pint) for code style
- Anybody, Instrument Sans and Martian Mono from Google Fonts, loaded in the layout because the stretch animations need the fonts' variable width axis

## Getting started

You need PHP 8.4, Composer, Node.js and npm.

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

`links` in `config/lri.php` holds the Instagram profile, the member registration form and the Run the Elements challenge. Every button and link on the page uses these values.

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

## Testing

```bash
php artisan test --compact
vendor/bin/pint --dirty
```

`tests/Feature/HomePageTest.php` checks that the page shows the next run and the links from `config/lri.php`. Tests run without built assets because `tests/TestCase.php` turns Vite off.

## Deployment

The site runs on any host with PHP 8.4, including [Laravel Cloud](https://cloud.laravel.com). A typical deploy:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
php artisan optimize
```

In production, set `APP_ENV=production`, `APP_DEBUG=false` and `APP_URL` to the site's address in `.env`.

## Community

- Instagram: [@letsrunibadan](https://www.instagram.com/letsrunibadan/)
- Become a member: [member registration form](https://docs.google.com/forms/d/e/1FAIpQLScBqAfuSOLlA2YSUpJ9XBdNeo9MVbmwh1gQWNr8z_BMtIb1QA/viewform)
