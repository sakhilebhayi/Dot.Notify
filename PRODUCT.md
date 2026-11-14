# Product

<!-- impeccable:product-schema 1 -->

> How this record was made (2026-11-14): no interview was possible in the session that wrote it. Every fact below
> was read from this repository (README.md, wiki.md, routes, models, policies, tests) or from the owner's written
> brief for the landing work. The two lines marked *owner brief* are the owner's words; nothing else is a
> preference. Open decisions are listed as open, not guessed.

## Platform

web

## Users

People who run a small or mid-sized company on the Dot ecosystem and are responsible for making sure the right
person hears about something that happened in another Dot app: a task assigned, a leave request waiting, an
auction outbid, a ride accepted. Usually the company's owner or an administrator, at a desk, a few times a week;
often arriving because someone said "I never got the email".

Two standings exist, set by team role:

- **Owner and administrator** can add, switch and remove channels, templates and rules, and switch events on.
- **Editor and viewer** can read everything on the same pages (log, channels, templates, rules, connected apps)
  and change nothing.

There is no separate home per role. Everyone lands on `/dashboard`.

## Product Purpose

Dot.Notify is the ecosystem's shared last mile. A Dot app reports that something happened; Dot.Notify decides,
by the company's own rules, who is told, on which channel, with which words, and keeps a record of every
attempt. Success is a person being told once, on time, and the company being able to see afterwards exactly
what was handed over and what was not.

## Positioning

Dot.Notify records only what it can know. "Sent" means the channel accepted the message. It receives no
opens, clicks, bounces or inbox confirmations and shows none. A neighbouring product that reports "delivered"
or an open rate is claiming something this one refuses to.

## Operating Context

- Sign-in is through InfoDot; a company is a team carrying InfoDot's company identifier. Public sign-up is closed.
- The whole product is two pages: the home page (`/dashboard`: delivery record, bulk sends, channels, templates,
  rules) and Connected apps (`/connected-apps`: the events each Dot app can send, each with a switch).
- The order a company really sets up in: add a channel (email first: catalogued events send by email), then
  switch on events under Connected apps. Switching an event on creates the company's own copy of the default
  message and a rule that sends it through the first active email channel. Rules and templates can then be
  edited by hand on the home page.
- Other apps call a signed server-to-server ingest API. External systems can post to a per-webhook signed URL.
- Delivery is queued, retried with backoff, and claimed per row so a crash cannot send twice.

## Capabilities and Constraints

- Channel types: email, SMS, push, webhook, Slack, in-app.
- Log statuses that something really produces: queued, sending, retrying, sent, failed, suppressed (the
  recipient opted out). Older rows may carry statuses nothing writes any more; they are never offered as filters.
- A channel records `is_active`, and `test_status` / `last_tested_at` when it has been tested. Channel health is
  never inferred beyond those fields and the log.
- Templates use `{{ variable }}` interpolation. A draft can be generated from a one-line purpose; with no model
  key configured the generator returns a deterministic stub.
- A rule maps one trigger event to one template and one channel, with optional conditions and an hourly ceiling.
  A condition may never read a recipient-activity field: no message may be triggered by someone's absence.
- Security notices are never suppressed by an opt-out.
- There is no screen yet for editing personal opt-outs, webhooks or schedules.
- Terminology: channel, template, rule, event, batch, log, sent, failed, retrying, suppressed, connected app.
- Open: whether the home page should be split into separate pages as the product grows.

## Brand Commitments

- Name: **Dot.Notify**, written exactly so.
- Logo: `public/images/logo.png` (for dark grounds) and `public/images/logo-light.png` (for light grounds), with
  square marks `mark.png` and `mark-light.png`. The artwork is never redrawn, recoloured or replaced.
- *Owner brief:* the product should feel "clear and dependable".
- *Owner brief:* its look must be its own, distinct from the other Dot platforms, and must not borrow the dark
  control-room look of the ecosystem's internal tools. It works in light and dark.
- Typefaces and icons are served by the app's own build; no font is loaded from a third-party host. (Known debt:
  the Jetstream account pages inside the shell still take Tailwind from its CDN script.)

## Evidence on Hand

- Real records per company: `notify_logs`, `notify_channels`, `notify_templates`, `notify_rules`,
  `notify_batches`, `notify_inbound_events`.
- The event catalogue in `config/notify_events.php`, with the default message for each event.
- No customer quotes, benchmarks, delivery-rate claims or uptime figures exist. None may be invented.

## Product Principles

1. Say only what the record shows. A channel that has never sent anything says so.
2. One next step at a time: the page always knows what this company should do next and offers that first.
3. Reading is for everyone, changing is for owners and administrators, and the page says which you are.
4. Nothing is sent because a person went quiet.
5. Words a business owner would use: "sent", "failed", "switched off", never queue or driver vocabulary.

## Accessibility & Inclusion

WCAG 2.2 AA: 4.5:1 body text and 3:1 large text in both themes, visible keyboard focus, 44px touch targets on
narrow screens, reduced motion honoured, status never carried by colour alone.
