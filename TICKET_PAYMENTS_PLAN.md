# Ticket payments plan

Selling Silent Disco Run tickets on the site, with Bachs as the first payment provider.
Branch: `ticket-payments`. Last updated: 3 November 2026.

## Goals

- **Accept ticket payments through Bachs**, built so another provider (for example Paystack for OPay) can be added later without touching checkout.
- **Care about payment methods, not just payments.** Each method, in each currency, is set up separately, and any of them can be featured at checkout. For example, "Pay with bank transfer" shows as a big button that goes straight to a bank-transfer-only checkout.
- **Track every step of the buyer's journey**, so we can see why someone didn't pay and which payment methods and sources work best.
- **Win back abandoned payments through an outside email tool** (Mailchimp, Mailtrap and the like). The site only sends those tools the data; the follow-up emails themselves are set up in the tool.
- **Give admins an admin area.** The `users` table is for organisers only.

## How it's built

- **Provider layer** ([app/Payments](app/Payments)): one contract every provider follows ([PaymentProvider.php](app/Payments/Contracts/PaymentProvider.php)). Each provider translates its own statuses, methods and failure reasons into ours. There is no fake provider: locally the site uses Bachs's sandbox (`sk_sandbox_` keys), and tests fake Bachs's API so they never reach it.
- **Payment methods are database rows** (`payment_channels`). Each row says the method, the currency, which provider handles it, whether it's on, whether it's featured, and its position in the list.
- **Every payment attempt is kept**, failed ones included. That history is what makes the per-method numbers honest.
- **Bachs's webhooks are what confirm payment.** A webhook is the notification Bachs sends our server when something happens to a payment. The page a buyer lands on after paying proves nothing on its own. Our own checks with Bachs back the webhooks up.
- **Money is stored in kobo** (whole numbers, so no rounding errors). It's only converted to Bachs's decimal format when we talk to Bachs.

## Progress

### ✅ Phase 1: Database and money (`3f83eb2`)
- [x] Switched to MySQL (tests still use a throwaway in-memory database)
- [x] Tables: ticket types, payment methods, orders, payment attempts, tickets, webhooks, journey steps
- [x] Readable order references (`LRI-7K3Q9P`), and a separate reference for each payment attempt
- [x] Seeded the ₦10,000 Disco Run ticket, plus naira bank transfer (featured) and card through Bachs

### ✅ Phase 2: Provider layer and Bachs (`cc33217`)
- [x] Provider contract and manager
- [x] Bachs: open a checkout limited to one method, ask where a checkout stands, list the methods switched on for our account
- [x] Each Bachs request carries an "idempotency key", a unique tag that stops a retry from opening a second checkout, so brief outages can be retried safely
- [x] Webhooks are only trusted if their HMAC signature checks out and they're under 5 minutes old; both secrets work while Bachs rotates to a new one
- [x] `php artisan payments:sync-channels` matches the payment methods to what's switched on at Bachs

### ✅ Phase 3: Checkout and order page (`24f124b`)
- [x] The ticket card is now the order form, with featured methods up front and the rest under "More ways to pay"
- [x] Tickets are held while the buyer pays, and two buyers can't take the last tickets at the same time
- [x] Bachs's checkout opens as a pop-up over our page, falling back to Bachs's own page; the form still works without JavaScript
- [x] A signed order page: it confirms the payment, shows ticket codes, explains failures in plain words and lets the buyer pay again
- [x] Each order is fulfilled only once; late updates can't undo a payment; short or wrong-currency payments are held back

### ✅ Phase 4: Webhooks, background checks, emails (`eed81db`)
- [x] Webhook endpoint at `/webhooks/payments/bachs`; webhooks are stored, handled once, and can be replayed (`payments:replay-webhook`)
- [x] `payments:reconcile` (every 5 minutes) asks Bachs about open payments, in case a webhook went missing
- [x] `orders:abandon-unpaid` (every 5 minutes) marks orders abandoned 10 minutes after their hold runs out
- [x] Tickets email, sent from the queue with retries

### ✅ Phase 5: Journey tracking (`53acb20`)
- [x] Visitor id cookie, plus campaign tags or the referring site credited on the order
- [x] Every step recorded, from the page view to paid, failed, abandoned or coming back; link previews and crawlers aren't counted
- [x] The browser reports starting the form and closing the pop-up, and only on its own order

### ✅ Phase 6: Admin (`d2403bd`)
- [x] Filament admin at `/admin`, with organisers managed in the admin itself
- [x] Dashboard: headline numbers, tickets per day, checkout funnel, how each way to pay performs, where buyers come from
- [x] Orders: search, filters, CSV download, and on each order its payment attempts and journey, plus check with provider, email tickets again, and open the buyer's page
- [x] Ticket types (prices in naira), ways to pay (on/off, featured, drag to reorder), webhooks (replay)
- [x] The opcodes log viewer at `/log-viewer`, behind the admin sign-in

### ⏳ Phase 7: Email tool connections (not started)
- [ ] One shared contract for email tools, with a driver per tool. Which one comes first is still to be decided:
  - Mailchimp
  - Mailtrap
  - a generic webhook, which lets Zapier, Make or anything else plug in
- [ ] Send the tool three things:
  - **Checkout started:** the buyer's contact details
  - **Checkout abandoned:** includes a resume link, a signed link that reopens checkout with their details already filled in
  - **Paid:** so the tool stops the follow-up emails
- [ ] Only send people who ticked the consent box, as Nigeria's Data Protection Act requires
- [ ] Send in the background with retries, so a slow tool never affects checkout
- [ ] Show in the admin what was sent and anything that failed

## Before going live

- [ ] Move production's existing birthdays and barracks registrations from SQLite to MySQL
- [ ] Production `.env`: `DB_*` for MySQL, `BACHS_SECRET_KEY` (`sk_live_…`), `BACHS_WEBHOOK_SECRET`
- [ ] In the Bachs developer portal, add `https://<domain>/webhooks/payments/bachs` as a webhook destination
- [ ] Run a queue worker (`php artisan queue:work`) for ticket emails
- [ ] Run the scheduler (`php artisan schedule:run` every minute) for the 5-minute checks
- [ ] Choose and set up a mail service; mail currently only goes to the log file
- [ ] Run `php artisan db:seed --class=TicketTypeSeeder` and `--class=PaymentChannelSeeder`, then `php artisan payments:sync-channels`
- [ ] Create the first organiser with `php artisan make:filament-user`
- [ ] Set the Disco Run ticket limit (number of headsets) in the admin
- [ ] Do a real test purchase on the Bachs sandbox (the sandbox keys in the local `.env` work)

## Open questions

- **Email tool:** which one first for phase 7, and should it also send the ticket emails?
- **QR codes:** do you want a QR code on tickets for check-in?
- **Ticket types:** more than one, such as early bird or group? Do you need each attendee's name?
- **OPay:** Bachs offers only card and bank transfer for naira. Ask Bachs whether OPay is coming, or add a second provider?
- **Cookie notice:** do we need one for the visitor and source cookies?

## Known gaps

- **Untested against real Bachs:** the webhook payloads and failure-reason wording come from Bachs's docs and haven't been checked against a real sandbox.
- **No webhooks locally:** Bachs can't reach `lri.test`, so a local test purchase is confirmed by the order page asking Bachs directly. To try webhooks locally, share the site at a public address (`herd share`) and add that address as a webhook destination in the Bachs portal.
- **No follow-up before submit:** someone who leaves before submitting the form can't be followed up by email, because we don't have their email yet. They only appear in the funnel.
- **PHPUnit advisory:** `composer audit` reports a medium-severity issue in PHPUnit 13.4.3, which is only used for tests. It was already there before this work.
