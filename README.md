<div align="center">

<img src="public/images/logo.png" alt="Dot.Notify" width="180" />

<br /><br />

**Send, track, and optimise notifications across email, SMS, push, and Slack.**

<br />

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white) ![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white) ![Livewire](https://img.shields.io/badge/Livewire-3-FB70A9?style=flat-square) ![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-336791?style=flat-square&logo=postgresql&logoColor=white)

<br /><br />

**Part of the [InfoDot Ecosystem](https://github.com/sakhileb/InfoDot)** &nbsp;·&nbsp; `notify.infodot.app`

</div>

---

## What is Dot.Notify?

Dot.Notify is the notification management platform in the InfoDot ecosystem. Teams configure delivery channels, build message templates with AI assistance, set event-triggered routing rules, and track every notification from queue to send. Other Dot apps can call it over a signed server-to-server API.

## Core Features

- Multi-channel delivery — email, SMS, push, webhook, Slack, and in-app
- Template builder with {{ variable }} interpolation
- AI template generation — describe the notification, get copy in seconds (falls back to a deterministic stub if no API key is configured)
- Rule engine model (`NotifyRule`) mapping trigger events to a channel + template — no management UI yet, see Roadmap
- Delivery log — audit trail with status per recipient (queued, sending, retrying, sent, failed, suppressed), searchable by recipient/subject and filterable by status. Notify records that a channel accepted a message; it does not receive opens, clicks, bounces or inbox-delivery confirmations, and does not show them
- Service ingest API — `POST /api/ingest/events`, signed per calling platform, idempotent, rate limited; routes through the company's existing rules, templates and channels
- Event catalogue — the 16 events the Dot apps send, each with a default email and the only data keys it may carry; a company switches them on from the Connected apps page (see below)
- Queued delivery — retries with exponential backoff, row claim so a crash cannot double-send, opt-outs enforced
- Secrets encrypted at rest (channel config, webhook signing secrets); outbound webhook/Slack calls block private networks
- User preference opt-outs per notification type, and per catalogued event, on a channel are enforced at send time (security notices are always sent); there is no UI to edit them yet
- Bulk batch sends with delivery rate tracking
- Auto-generated webhook endpoint tokens (`NotifyWebhook`) — the receiving HTTP endpoint itself is not implemented yet, see Roadmap
- In-app self-notifications for this platform's own operators (channel degraded, batch failed)
- Ecosystem SSO from InfoDot hub

## Domain Models

- **NotifyChannel** — configured delivery channel (email, SMS, etc.)
- **NotifyTemplate** — message template with variable support
- **NotifyRule** — trigger-to-channel routing rule
- **NotifyLog** — delivery audit record per recipient (status, attempts, last_error, next_retry_at)
- **NotifyPreference** — per-user opt-in/out by notification type + channel, or by one catalogued event (`event`)
- **NotifyBatch** — bulk send with progress/delivery-rate tracking
- **NotifyWebhook** — inbound webhook endpoint registration (token generation only; no receiving route yet)

> A `notify_schedules` table exists in the schema for recurring/cron-based sends, but there is no `NotifySchedule` model or UI built on it yet.

## Roadmap / Known Gaps

- Inbound webhook receiving endpoint (route + signature verification) — not implemented; `NotifyWebhook` currently only issues tokens
- `NotifyRule` management UI (the model and relationships exist, no Livewire component yet)
- `NotifySchedule` model + recurring-send execution (table exists, unused)
- Knowledge Pack publishing to Dot.Brain (`observation`/`insight`/`outcome`/`incident`) — see `wiki.md` for the target shape

## Event catalogue and enabling events

Dot apps do not invent event names. `config/notify_events.php` (version 1) is the one list of events the apps send to the ingest API, who each one is for, the email each starts from, and the only data keys each may carry. The calling apps are written against exactly these names and keys; to add or rename one, bump `version` and tell the calling apps first. The page **Connected apps** (`/connected-apps`) lists them for every company.

| App | Event | Recipient | Data keys kept |
|---|---|---|---|
| Dot.Tasks | `task.assigned` | user | `task_title`, `board_name`, `assigned_by`, `due_date`, `url` |
| Dot.Tasks | `task.due_soon` | user | `task_title`, `board_name`, `due_date`, `url` |
| Dot.Tasks | `task.escalated` | user | `task_title`, `board_name`, `owner_name`, `url` |
| Dot.Tasks | `task.mentioned` | user | `task_title`, `board_name`, `mentioned_by`, `url` |
| Dot.HR | `leave.requested` | approvers | `employee_name`, `leave_type`, `start_date`, `end_date`, `days`, `url` |
| Dot.HR | `leave.decided` | user | `leave_type`, `start_date`, `end_date`, `decision`, `decided_by`, `url` |
| Dot.Auction | `auction.outbid` | user | `auction_title`, `your_bid`, `current_price`, `currency`, `ends_at`, `url` |
| Dot.Auction | `auction.won` | user | `auction_title`, `final_price`, `currency`, `url` |
| Dot.Auction | `auction.sold` | user | `auction_title`, `final_price`, `currency`, `status`, `url` |
| Dot.Auction | `auction.ending_soon` | user | `auction_title`, `current_price`, `currency`, `ends_at`, `url` |
| Dot.Ehail | `ride.accepted` | user | `driver_name`, `vehicle`, `url` |
| Dot.Ehail | `ride.cancelled` | user | `cancelled_by_role`, `reason`, `url` |
| Dot.Ehail | `ride.expired` | user | `url` |
| Dot.Ehail | `driver.application_decided` | user | `decision`, `fleet_name`, `note`, `url` |
| Dot.Farms | `harvest.recorded` | user | `farm_name`, `field_name`, `crop`, `quantity`, `unit`, `harvested_at`, `url` |
| Dot.Billing | `invoice.overdue` | user | `team_name`, `invoice_number`, `total`, `balance`, `currency`, `due_date`, `days_overdue`, `invoice_url` |

`user` is the one person the event is about. `approvers` (leave requests only) are the people who must act on it: the calling app sends one call per approver, with that person as the recipient. Dot.Billing's link key is `invoice_url` (not `url`), because that is what its NotifyClient sends today.

### What a calling app sends

`POST /api/ingest/events`, server to server, JSON body, signed with the platform's own secret (`NOTIFY_INGEST_SECRET_<KEY>` here; the same value in the calling app):

```
X-InfoDot-Timestamp: <unix seconds, within 5 minutes of now>
X-InfoDot-Signature: sha256=<hex hmac_sha256("{timestamp}.{raw body}", platform secret)>
Content-Type: application/json
```

```json
{
  "platform": "tasks",
  "company_uuid": "<the InfoDot company uuid, teams.infodot_team_uuid>",
  "event": "task.assigned",
  "recipient": { "email": "amara@example.co.za" },
  "data": {
    "task_title": "Ship the launch page",
    "board_name": "Launch",
    "assigned_by": "Bongani",
    "due_date": "2026-11-02",
    "url": "https://tasks.infodot.app/boards/4?task=19"
  },
  "idempotency_key": "task.assigned:19:3f9a1c2e:2026-10-18"
}
```

Rules for `data` on a catalogued event (anything not catalogued is accepted as before, unfiltered):

- Only the keys in the table are kept. Anything else is dropped before the event is stored or the email is rendered. The names of dropped keys (never their values) are kept on the stored event as `dropped_data_keys`, so a misspelled key is easy to spot.
- Every value must be a single value: text, a number or true/false. A list or object under an allowed key is refused with `422 {"error":"invalid_request","errors":{"data.<key>":[...]}}`; under an unknown key it is simply dropped.
- Text has control characters and line breaks turned into spaces and is cut to 500 characters (`max_value_length`). Send nothing, or `null`, for a field you do not have: a line of the email whose fields are all empty is left out.
- A key named `url` or ending `_url` must be an absolute `https` URL on the calling platform's own host (the host of that platform's `DOT_<KEY>_URL` in `config/ecosystem.php`, no other port than 443, no user info, at most 2048 characters). Anything else, including `javascript:`, `http:` and links to other hosts, is dropped.
- `leave.requested` never carries a reason: there is no key for it, so one sent anyway is dropped and never stored or emailed.
- A catalogued event is only accepted from the platform that owns it (Dot.Billing cannot send `task.assigned`); from another platform it is recorded as `no_rule` and nothing it sent is kept.
- `notification_type` is taken from the catalogue for catalogued events (all `transactional` today); the caller's value is ignored.

Responses are those in `wiki.md` (202/200/401/404/422/429). `status` is `routed` (queued for delivery), `no_rule` (the company has not switched this event on), `ignored` (the rule's hourly limit) or `failed` (the rule has no usable channel or template).

**Idempotency key.** Send the same key for the same thing and a new key for a new thing; a repeat with the same key sends nothing. Build it as `{event}:{subject id}:{recipient hash}:{bucket}`: the event name, the id of the thing it is about (task, leave request, auction, ride, invoice), a short hash of the recipient's email (so each approver of one leave request gets their own message), and a bucket for how often the same event may repeat (a date for a daily reminder, the decision timestamp or version for a decision, nothing extra for a one-off). Keys are unique per company and platform, at most 120 characters.

### How a company turns events on

Nothing is sent for an event until the company switches it on. Three ways, all doing the same thing:

1. **Connected apps** (owner and admin): a switch per event. Editors and viewers see the same page read-only, with no switch.
2. `php artisan notify:enable-defaults {company_uuid} {platform?} {--events=a,b}`: for an operator. With no platform it switches on every catalogued event; `--events` names specific ones.
3. By hand: a `NotifyRule` for the event name under Routing Rules.

Switching an event on creates, once, the company's own copy of the default template (slug `catalogue.<event>`) and an active rule that points at it and at the company's first active email channel. It is safe to repeat. If the company already has a rule for the event it is switched back on as it is; a template that was edited is never overwritten and a channel the company chose is never replaced. Switching an event off only deactivates its rules: nothing is deleted.

If the company has no active email channel yet, the rule is still created but flagged **No email channel yet**: events arrive and are recorded as `failed`, and nothing is sent. Add an email channel, then press "attach it" on the page (or run the command again).

The page also shows, per event, when the company last received it and what happened to it (queued, no rule, held by a rate limit, could not be sent), so an owner can see an app is really calling. An app whose ingest secret is not configured here is marked as not connected.

### Opting out

A recipient who has a Dot.Notify account can switch one event type off per channel type (`NotifyPreference::setForEvent($user, 'task.due_soon', 'email', false)`; rows keep `event = ''` for the older category-wide opt-outs). The send step then marks the notification `suppressed` with the reason. `security` notices are never suppressed, and none of today's events is one. There is no screen for this yet, and email footers do not carry a signed unsubscribe link: both are out of scope for this change, so today an opt-out is recorded by an operator or a future preferences page.

## Responsive shell

The app shell lives in `resources/views/layouts/app.blade.php` (inline CSS and one small inline script; no build step, no extra dependency).

- **Breakpoint: 860px.** At or above it the sidebar is fixed beside the content. Below it (`max-width: 859.98px`) the sidebar becomes an off-canvas drawer opened from the top bar.
- **Hooks:** menu button `#shell-menu-button` (labelled "Menu", `aria-expanded`, `aria-controls="shell-drawer"`), drawer `#shell-drawer` (the sidebar), backdrop `#shell-backdrop`, close button `#shell-close`, top bar `.topbar`. The top bar and the content wrapper carry `data-shell-behind`: while the drawer is open they get `inert`, so focus stays in the drawer, and `html.shell-open` locks page scroll.
- **Closing:** Escape, the backdrop, the close button, following a nav link, or the viewport growing past 860px. Focus returns to the menu button. A closed drawer is `visibility:hidden`, so it is out of the tab order and hidden from assistive tech. Motion is off under `prefers-reduced-motion`.
- **Content offset:** `--topbar-h` is the single source for the top bar height and the content's top padding.
- **Page helpers:** `.page` (page padding), `.kpi-grid`, `.two-col` (stacks below 1100px), `.dot-row` / `.dot-row-main` / `.dot-row-actions` (list rows that wrap), `.form-grid-2` / `.form-grid-3` (single column on phones), `.row-action` (touch size on phones).
- **Adding a wide table:** wrap it in `<div class="table-scroll"><table>...</table></div>`. The wrapper scrolls sideways inside its own box and the page never does. Do not set fixed widths on cards or rows; use `min-width:0` and `overflow-wrap:anywhere` for long values.
- **Team and account links** (team settings, create team, switch team, log out) are in the drawer; the old Jetstream `navigation-menu` bar is no longer rendered in this layout.

Tests: `tests/Feature/ResponsiveShellTest.php` pins the markup hooks. It cannot check appearance.

## Metrics for Dot.Analytics

Notify also publishes its own numbers. Once a day (03:00 UTC, `notify:publish-metrics`) it queues one signed
snapshot per company to Dot.Analytics' server-to-server ingest (`POST /api/ingest/snapshots`) through
`App\Services\Analytics\AnalyticsClient` and `App\Jobs\SendAnalyticsSnapshot`. A company is a non-personal team
linked to InfoDot (`teams.infodot_team_uuid`); personal and unlinked teams are skipped.

What is sent, and nothing else (Dot.Analytics' `notify` catalogue; counts from the company's delivery log only,
never a recipient, subject, body, event name or error text, and no money, so no `currency`). Windows are UTC, the 7
days ending when the command runs, and a message counts when it reached its outcome rather than when it was queued:

| Key | Meaning |
|---|---|
| `sent_7d` | messages with status `sent` (and the legacy `delivered`/`opened`/`clicked`) whose `sent_at` is in the window. "Sent" means a channel accepted it, as everywhere in this app, not that it reached an inbox |
| `failed_7d` | messages with status `failed` (and legacy `bounced`) that failed in the window. There is no `failed_at` column; failed is terminal, so `updated_at` is when it failed |
| `suppressed_7d` | messages stopped by a recipient's opt-out in the window (same reasoning, `updated_at`) |

Queued, sending and retrying messages have no outcome yet and are counted in none of them. The snapshot is stamped
`taken_at` = the start of the UTC day, so a retry or a second run the same day is the same snapshot upstream
(Dot.Analytics keeps the first one it received for a day).

Nothing is sent until `ANALYTICS_INGEST_URL` (the full URL ending `/api/ingest/snapshots`) **and**
`ANALYTICS_INGEST_SECRET` are set; the secret is the same value as `ANALYTICS_INGEST_SECRET_NOTIFY` in
Dot.Analytics' `.env`, and `ANALYTICS_PLATFORM_KEY` defaults to `notify`. Requests are HMAC-SHA256 signed over
`"{timestamp}.{raw body}"` at send time, time out after 5 seconds, are retried up to 3 times with backoff, never
throw into the app, and stop for 5 minutes (circuit breaker) after 5 failures in a row, so an Analytics outage or a
wrong URL cannot affect delivery. This needs the scheduler (`* * * * * php artisan schedule:run`, already required
for delivery) and a queue worker. Try it safely with `php artisan notify:publish-metrics --dry-run` (prints the
payloads, sends nothing) and `--company=<uuid>`.

## Roles

Access follows the team (company) role. InfoDot is the source of truth and the role is re-applied at every sign-in.

| InfoDot role | Dot.Notify role | Can |
|---|---|---|
| owner, admin | admin (owner for the creator) | everything: create, change, toggle and delete channels, rules, templates, webhooks and schedules; use the AI template generator |
| editor | editor | read only here: the dashboard, the notification log and the channel, template and rule lists; the write and AI controls are not shown |
| viewer | viewer | the same read surface as an editor, nothing more |
| anything else | viewer | an unrecognised InfoDot role gets the least powerful role, never editor |

Channel credentials (the encrypted `config`), webhook signing secrets and message bodies are hidden from every model serialisation and are not rendered for any member. Moving someone to viewer in InfoDot takes effect at their next sign-in. The role matrix is in `tests/Feature/Notify/ViewerRoleMatrixTest.php`.

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 |
| Language | PHP 8.4 |
| Frontend | Livewire 3 · Alpine.js 3 · Tailwind CSS |
| Database | PostgreSQL 16 (shared across ecosystem) |
| Realtime | Laravel Reverb |
| Auth | Laravel Sanctum (InfoDot SSO) |
| AI | Anthropic Claude (`claude-sonnet-4-6`) |
| Storage | AWS S3 / Local (Flysystem) |
| Search | Laravel Scout · Meilisearch |
| Queue | Redis · Laravel Horizon |

## Quick Start

```bash
git clone https://github.com/sakhileb/Dot.Notify.git
cd Dot.Notify
cp .env.example .env
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate
php artisan serve
```

> **Ecosystem SSO:** Set `DB_*` env vars to the shared InfoDot PostgreSQL instance and `APP_URL=https://notify.infodot.app`. Users authenticated through InfoDot gain access automatically via Sanctum handoff tokens.

## 🚢 Deployment

### Production Checklist

1. **Set environment**
   ```bash
   APP_ENV=production
   APP_DEBUG=false
   ```

2. **Install dependencies (no dev)**
   ```bash
   composer install --optimize-autoloader --no-dev
   npm ci
   ```

3. **Build frontend assets**
   ```bash
   npm run build
   ```

4. **Cache configuration**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan event:cache
   ```

5. **Run migrations**
   ```bash
   php artisan migrate --force
   ```

6. **Start the queue worker** (use `deploy/queue-worker.service` for systemd or `deploy/queue-worker.supervisord.conf` for Supervisor). Requires `QUEUE_CONNECTION=redis`, matching `.env.production.example`.
   ```bash
   php artisan queue:work redis --tries=3 --timeout=90
   ```

7. **Start the Reverb WebSocket server** (use `deploy/reverb.service` for systemd or `deploy/reverb.supervisord.conf` for Supervisor) -- never run this as a bare foreground command in production, it needs the same process supervision as the queue worker.
   ```bash
   php artisan reverb:start
   ```
   Binds to `REVERB_SERVER_HOST`/`REVERB_SERVER_PORT` (loopback-only by default in `.env.production.example`) -- it is not meant to be reachable directly from the internet. See "WebSocket Reverse Proxy" below for how browsers actually reach it over `wss://`.

### Web Server Configuration

#### Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    http2 on;
    server_name your-domain.com;
    root /var/www/notify/public;

    ssl_certificate     /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Only Reverb's client-facing path (/app/{key}, what Echo/pusher-js
    # connects to) is proxied here. Its server-to-server publish API
    # (/apps/{id}/events etc.) is deliberately NOT exposed publicly --
    # config/broadcasting.php's reverb connection talks to it directly over
    # the internal REVERB_SERVER_HOST/PORT instead, so it never needs to be
    # reachable from outside this box.

    # WebSocket Reverse Proxy -- proxies wss://your-domain.com/app/... to the
    # internal reverb:start process (REVERB_SERVER_HOST/PORT, loopback-only
    # by default). The Upgrade/Connection headers are what turn this from a
    # plain HTTP proxy into a WebSocket one; without them the client's
    # protocol upgrade handshake fails and Echo falls back to polling or
    # errors outright. Long read_timeout because these are meant to be
    # long-lived connections, not request/response.
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 60s;
    }
}
```

#### Apache

Requires `mod_proxy`, `mod_proxy_wstunnel`, and `mod_ssl` enabled (`a2enmod proxy proxy_wstunnel ssl`).

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    Redirect permanent / https://your-domain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/notify/public

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/your-domain.com/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/your-domain.com/privkey.pem

    <Directory /var/www/notify/public>
        AllowOverride All
        Require all granted
    </Directory>

    # WebSocket Reverse Proxy -- see the Nginx /app block above for what
    # this achieves and why. ProxyPass's own ws:// scheme (rather than
    # http://) is what makes mod_proxy_wstunnel handle the Upgrade
    # handshake instead of treating this as a normal HTTP proxy.
    ProxyPass        /app ws://127.0.0.1:8080/app
    ProxyPassReverse /app ws://127.0.0.1:8080/app
</VirtualHost>
```

### Real-Time Health Check

`GET /up/realtime` checks broadcasting config, queue connection, and whether `reverb:start` is actually accepting connections -- independently, so it reports which link broke rather than a single healthy/unhealthy bit. See `app/Http/Controllers/RealtimeHealthController.php`.

---

## Ecosystem

**Dot.Notify** is one of **21 platforms** in the InfoDot ecosystem, connected via shared PostgreSQL and Sanctum SSO. Visit [InfoDot](https://github.com/sakhileb/InfoDot) to explore the full platform map.

## License

MIT © [SK Digital / BluPin Incorporated](https://github.com/sakhileb)
