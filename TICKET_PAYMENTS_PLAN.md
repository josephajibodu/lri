# Ticket payments plan

Selling Silent Disco Run tickets on the site, with Bachs as the first payment provider.
Branch: `ticket-payments`. Last updated: 4 November 2026.

## Goals

- **Accept ticket payments through Bachs**, built so another provider (for example Paystack for OPay) can be added later without touching checkout.
- **Care about payment methods, not just payments.** Each method, in each currency, is set up separately, and any of them can be featured at checkout. For example, "Pay with bank transfer" shows as a big button that goes straight to a bank-transfer-only checkout.
- **Track every step of the buyer's journey**, so we can see why someone didn't pay and which payment methods and sources work best.
- **Win back abandoned payments through an outside email tool** (Mailchimp, Mailtrap and the like). The site only sends those tools the data; the follow-up emails themselves are set up in the tool.
- **Give admins an admin area.** The `users` table is for organisers and helpers; their roles decide what each sees.

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
- [x] Bachs's checkout opens as a pop-up over our page, for live and sandbox checkouts alike. Bachs's own page is only a fallback, for when the pop-up can't load or JavaScript is off
- [x] A signed order page: buyers come back to it from the checkout. It asks Bachs straight away, explains failures in plain words and lets the buyer pay again
- [x] Once paid, the buyer moves on to a receipt at its own lasting address (`/receipts/{secret code}`, with nothing about the payment in it): their tickets, what they paid, when and how. The tickets email links to it
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

### ⏸️ Phase 7: Email tool connections, with Mailchimp first (built, switched off)

On hold until the email tool is chosen. The code stays, and nothing is sent while `EMAIL_TOOL` isn't set. If another tool is picked, it gets its own driver alongside Mailchimp's.

- [x] One shared contract for email tools ([EmailTool.php](app/EmailTools/Contracts/EmailTool.php)), with a driver per tool. Mailchimp is the first ([MailchimpProvider.php](app/EmailTools/Providers/MailchimpProvider.php))
- [x] The tool gets three things. Each sets the contact's name, order number and one tag, and takes off the other two tags:
  - **Checkout started** (tag `checkout-started`): adds or updates the contact, with a resume link
  - **Checkout abandoned** (tag `checkout-abandoned`): the same, for the follow-up emails to use
  - **Paid** (tag `ticket-paid`): so the tool stops the follow-up emails. It also clears the resume link, which by then leads to the receipt
- [x] The resume link (audience field `RESUMEURL`) is the buyer's signed order page, which keeps their details and offers every way to pay
- [x] Only people who ticked the consent box are sent, as Nigeria's Data Protection Act requires. Someone already in the audience keeps their subscription status, so anyone who unsubscribed isn't signed back up
- [x] The consent has to come from the person themself, and anyone can type any email on an unpaid order. So a new contact from an unpaid order is added as pending, and Mailchimp asks them to confirm. Paying subscribes them, including anyone still pending. Abandoned-checkout reminders only reach people who confirmed
- [x] Phone numbers aren't sent: follow-up emails don't need them, and Mailchimp's phone field can refuse Nigerian numbers
- [x] Sent in the background: outages are retried, refusals fail straight away, and news that's no longer true (such as "abandoned" for someone who has since paid) is skipped
- [x] The admin shows what was sent and anything that failed, under System > Email tool, with "Send again"

### ✅ Phase 8: A name on every ticket, and the headset desk

Each person picks up their own headset, so each ticket carries its own name and email. The buyer pays for everyone, and the tickets are named after payment.

**Database**
- [x] `tickets` gained `holder_email`, `claim_token` (a long random secret for the ticket's own link) and `checked_in_by` (who handed over the headset). `checked_in_at` already recorded when
- [x] When an order is paid, the first ticket gets the buyer's name and email; the rest stay blank

**Buyer names the tickets (on the receipt page)**
- [x] For orders of more than one ticket, the receipt has a name-and-email form under each ticket and a "Copy their link" button for blank ones (the share sheet on phones)
- [x] The buyer can change a ticket's name or email until its headset has been collected
- [x] The tickets email shows who each ticket belongs to, and if some are blank, tells the buyer to name them or bring everyone to the desk

**Friend fills in their own ticket**
- [x] `/tickets/{claim_token}`: shows the event and, if the ticket is blank, asks for a name and email. Once it's named, the same link shows that person's ticket and code
- [x] A ticket that already has a name can't be taken over through the link
- [x] When a ticket gets a new email (from the buyer, the friend or the desk), that person is emailed their own ticket ([TicketMail.php](app/Mail/TicketMail.php)), unless it's the buyer's own ticket
- [x] Friends are never sent to Mailchimp: they didn't tick the consent box

**Headset desk** ([HeadsetDesk.php](app/Filament/Pages/HeadsetDesk.php))
- [x] A page in the admin at `/admin/headset-desk`, under Tickets
- [x] One search box: ticket code (with or without the dash), holder's name or email, buyer's name or email, or order number. Tickets are grouped by order, so searching for the buyer shows everyone on their order
- [x] Each ticket shows Ready, Needs a name, Collected (when and by whom) or Not paid
- [x] "Fill in name": staff type in the name and email on the spot; that person is emailed their ticket
- [x] "Hand over headset": only for a paid, named ticket that hasn't had one. The ticket is locked while it's recorded, so two staff can't hand out a headset for the same ticket
- [x] A count at the top: headsets handed out out of paid tickets
- [x] Organisers add desk staff under Settings > People, giving them the "Headset desk" role

### ✅ Phase 9: Roles and permissions in the admin

- [x] Spatie's roles and permissions package, with one permission per section of the admin ([AdminPermission.php](app/Enums/AdminPermission.php)): dashboard, orders, headset desk, ticket types, ways to pay, birthdays, barracks permits, people and roles, webhooks, email tool, logs
- [x] Each section checks its permission through a policy ([app/Policies](app/Policies)), so a section someone can't use is missing from their menu and its address is refused
- [x] Starting roles: **Super admin** (everything, including sections added later; can't be edited), **Admin** (every section, editable) and **Headset desk** (only the desk). Any login that already exists when the migration runs becomes a super admin; on a fresh database, the first login is made one with `permission:assign-role`
- [x] Settings > Roles: create a role and tick the sections it sees. Settings > People: give each person one or more roles
- [x] `/admin` sends each person to the first section they can see, so desk staff land straight on the desk; the dashboard moved to `/admin/dashboard`
- [x] Only a super admin can make someone a super admin or change one; nobody can change their own roles or remove themselves; a role someone still has can't be removed

### ✅ Phase 10: QR codes on tickets, and undo for the buyer

Already in place from phases 8 and 9: a holder name and email on each ticket, a long random link per ticket (`claim_token`, separate from the short gate code), `checked_in_at` as the "headset collected" marker, and the desk's search by code, name, email or order. This phase adds the rest.

**QR code on every ticket**
- [x] Each ticket gets its own QR code, with the printed code (like `7K3Q-9PXM`) underneath for staff to type if the scan fails. One per ticket, never one per order
- [x] Shown on the ticket page (`/tickets/{link}`) once it has a name, on the receipt for the buyer's own ticket, in the friend's ticket email, and in the buyer's email for their own ticket. The buyer's email lists the other tickets' codes and names without QR codes
- [x] Made with `chillerlan/php-qrcode`, which is already installed because Filament uses it; it would be listed in `composer.json` directly rather than relied on through Filament. In emails the QR is attached as an inline image, because Gmail and others block images written into the email itself
- [x] The QR holds a link to the headset desk with that ticket's code already searched (`/admin/headset-desk?search=7K3Q-9PXM`). Desk staff scan it with their phone's normal camera, which opens the desk on that ticket; no scanner app or extra library. Anyone else who scans it just reaches the admin sign-in

**Receipt: every ticket with its status and buttons**
- [x] The buyer's own ticket: **Yours**
- [x] A named ticket: **Emailed**, with **Edit** and **Reissue**
- [x] A blank ticket: **No name yet**, with **Type details** and **Send link**
- [x] A blank ticket whose link was sent: **Link sent**, with **Copy link** and **Cancel link**. "Sent" is recorded when the buyer presses Send link (we can't see what happens in WhatsApp), in a new `link_shared_at` column

**The friend's link**
- [x] Opens only that one ticket: no other tickets, no price and no buyer details (it says "Someone got you a ticket"; the friend's email still names the buyer)
- [x] After claiming, the friend sees their ticket with its QR and gets it by email

**Undo, buyer only (from the receipt)**
- [x] **Cancel link**: the unclaimed link stops working (a new secret replaces it) and the ticket goes back to "No name yet"
- [x] **Reissue**: a new gate code and a new link, so the old QR, the old link and any screenshots stop working. The name and email are cleared, and the buyer names the ticket again or sends a new link
- [x] **Edit**: fixes a typo in the name or email and emails the ticket again with the same code (today it only emails again when the address changes)
- [x] None of these work once the headset has been collected. Whoever received a ticket can't pass it on or reissue it: their page has no such buttons, and the friend's link can't name a ticket that already has a name

**Headset desk after a scan or search**
- [x] Green **Paid, ready** with a **Give headset** button
- [x] Red **Headset already given**, with the time it was collected (and by whom)
- [x] Amber **Needs a name** with "Order has N unnamed tickets", and one **Save and give headset** button that asks for the name and email, replacing the two steps (Fill in name, then Hand over)
- [x] **Change name** on a named ticket fixes a typo at the desk and emails the ticket again

**Tests**
- [x] Every ticket has a QR that holds its own code; a reissued ticket's old code and old link no longer find it
- [x] Each receipt status shows the right buttons; Cancel link, Reissue and Edit work, and are refused after collection
- [x] The friend's page shows no price, other tickets or buyer details
- [x] Desk: each of the three results, and Save and give headset in one step

### ✅ Phase 11: Coupons

Lets organisers share discounts, and lets us try a live sale for a real ₦100 instead of the full ticket price.

**Rules**
- [x] Takes a percentage (up to 99%) or a naira amount off the order's total, and always leaves ₦100, the least Bachs will charge (it refuses anything lower, and ₦0). So there are no free tickets yet
- [x] Optional limits: start and end, how many orders can use it, how many tickets it covers in one order (any more are full price), and which tickets it works on
- [x] Can't be edited once made: the admin has no edit or delete button, and the model refuses any change except switching it off or on
- [x] Uses count like ticket holds: paid orders and orders still being paid for. Abandoned and refunded orders give their use back. The coupon's row is locked while counting, so two buyers can't both take its last use

**Buyer**
- [x] "Have a coupon?" on the ticket form, with Apply and Remove. The total follows the number of tickets. The code is checked again when the order is placed, which is also how it works without JavaScript
- [x] Checking a code is limited to 10 tries a minute per visitor, so codes can't be guessed
- [x] The order keeps its coupon and the amount taken off. `amount` is still what the buyer owes, so payments and sales reports need no changes
- [x] An abandoned order coming back has its coupon checked again. If it no longer works, the order goes back to full price and the buyer is told before paying

**Shown on**
- [x] The order page, the receipt, the tickets email (with a mail preview), the admin's order screen and list (with a filter), the CSV download, the buyer's journey, and what Bachs is told about the checkout

**Admin** ([CouponResource.php](app/Filament/Resources/Coupons/CouponResource.php))
- [x] Tickets > Coupons, with its own "Coupons" permission: create, view, disable, turn back on, and a link to the orders that used it

## Before going live

- [ ] Production `.env`: `APP_URL` (the real address), `DB_*` for MySQL, `BACHS_SECRET_KEY` (`sk_live_…`), `BACHS_WEBHOOK_SECRET`
- [ ] In the Bachs developer portal, add `https://<domain>/webhooks/payments/bachs` as a webhook destination
- [ ] Make sure PHP's GD extension is on (`php -m | grep gd`); ticket emails need it to draw the QR code
- [ ] Run a queue worker (`php artisan queue:work`) for ticket emails
- [ ] Run the scheduler (`php artisan schedule:run` every minute) for the 5-minute checks
- [ ] Choose and set up a mail service; mail currently only goes to the log file
- [ ] Run `php artisan db:seed --class=TicketTypeSeeder` and `--class=PaymentChannelSeeder`, then `php artisan payments:sync-channels`
- [ ] Create the first organiser with `php artisan make:filament-user`, then make them a super admin with `php artisan permission:assign-role "Super admin" <their id>`
- [ ] Only once the email tool is chosen, and if it's Mailchimp: set `EMAIL_TOOL=mailchimp`, `MAILCHIMP_API_KEY` and `MAILCHIMP_AUDIENCE_ID`, then run `php artisan email-tools:prepare` once to add the audience fields
- [ ] In Mailchimp, build the follow-up flow: start it on the tag `checkout-abandoned`, link to `*|RESUMEURL|*`, and stop it for anyone who gets the tag `ticket-paid`
- [ ] Set the Disco Run ticket limit (number of headsets) in the admin
- [ ] Run `php artisan migrate` on the fresh production database; it creates the tables, the admin's permissions and the starting roles
- [ ] Add an account for each person working the headset desk (Settings > People, role "Headset desk"); they sign in at `/admin` and only see the desk
- [ ] Do a real test purchase on the Bachs sandbox (the sandbox keys in the local `.env` work)

## Open questions

- **Ticket emails:** should Mailchimp also send them, or the mail service chosen above?
- **QR codes:** do you want a QR code on tickets for check-in?

## Decided

- **One ticket type, one price.** No early bird or group pricing.
- **A name and email on every ticket.** The buyer names extra tickets after paying, or sends each friend a link. On the day, the buyer can bring everyone to the desk and staff fill in any blank names. No headset without a paid ticket, and one headset per ticket.
- **Roles and permissions decide what each person sees in the admin.** Desk staff use the same `/admin` sign-in and only see the headset desk.
- **No OPay.** Card and bank transfer through Bachs are enough. We won't chase OPay with Bachs or add a second provider for it. The provider layer still allows one later if this changes.
- **No cookie notice.** The visitor and source cookies go ahead without one.

## Known gaps

- **Coupons and late bank transfers:** if a buyer's coupon ended and their abandoned order went back to full price, then a transfer for the old checkout still lands, the order counts as paid at full price in the reports although less came in. Very unlikely, and the payment attempt keeps the true amount.
- **Coupons haven't been paid with on a real checkout:** the discounted amount was accepted by the Bachs sandbox and the full flow is covered by tests with Bachs faked, but nobody has yet paid a discounted order through the pop-up.
- **Untested against real Bachs:** the webhook payloads and failure-reason wording come from Bachs's docs and haven't been checked against a real sandbox.
- **Untested against real Mailchimp:** there's no API key yet, so phase 7 has only run against a stand-in for Mailchimp's API, built from its docs. Check three things on a real account: that a new contact on an unpaid order gets the confirmation email, that a pending contact who pays becomes subscribed, and whether someone who confirms after their order was abandoned still gets the reminder.
- **No webhooks locally:** Bachs can't reach `lri.test`, so a local test purchase is confirmed by the order page asking Bachs directly. To try webhooks locally, share the site at a public address (`herd share`) and add that address as a webhook destination in the Bachs portal.
- **No follow-up before submit:** someone who leaves before submitting the form can't be followed up by email, because we don't have their email yet. They only appear in the funnel.
- **PHPUnit advisory:** `composer audit` reports a medium-severity issue in PHPUnit 13.4.3, which is only used for tests. It was already there before this work.
