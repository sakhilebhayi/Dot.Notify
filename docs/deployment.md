# Deploying Dot.Notify

Production target: `https://notify.infodot.co.za`, on the shared cPanel account that also runs the other Dot apps. This document describes the deploy pipeline in `.github/workflows/`. **Nothing here has been run against a host**: the workflows were written from the InfoDot and Dot.Agents pipelines, checked with actionlint and `bash -n`, and their host scripts were exercised only against a fake local host. The first real run is the first real test.

The pipeline is **inert** until you do the setup below: it only starts when someone dispatches it by hand, and it stops at its first SSH step until the secrets and the host exist. GitHub offers a `workflow_dispatch` workflow in the Actions tab only once the file is on the default branch (`main`); the trunk this pipeline is stacked on, `main`, is not merged there.

| Workflow | Runs | What it does |
|---|---|---|
| `ci.yml` | every PR, pushes to the trunk; called by `deploy.yml` | PHPUnit on PHP 8.4, in-memory SQLite. The release gate. No Pint, no asset build. |
| `drift.yml` | manual, **read-only** | Compares every tracked file of `main` with the host's copy (sha256) and prints counts and paths. Run it before the takeover. |
| `deploy.yml` | manual: `confirm=adopt` once, then `confirm=deploy` | See "What a deploy does". |

Both manual workflows refuse to run from any branch except `main` (the runner builds the frontend and runs the gate from the branch you start it on; the host checks out that same branch). When the trunk lands on `main`, change `DEPLOY_BRANCH` at the top of both files to `main`.

## Read this first: what the trunk contains

The branch the host runs, `main`, is the unmerged real-time trunk. These open PRs are stacked on it (merge order, bottom first). Deploy the stack, not the trunk alone.

| PR | Branch | What it adds | Migrations / data |
|---|---|---|---|
| #2 | `feat/sso-redeem` | signed InfoDot SSO redeem | `teams.infodot_team_uuid` |
| #3 | `feat/notify-ingest-reliability` | service ingest API (`POST /api/ingest/events`), queued delivery, hardening | four migrations: delivery columns and indexes; wider secret columns; **encrypts existing channel `config` and webhook `signing_secret` with `APP_KEY`** (idempotent, `down()` is a deliberate no-op); unique `(team_id, platform, idempotency_key)`. PR says back up first and do not change `APP_KEY` afterwards |
| #5 | `feat/viewer-role` | read-only viewer role | none |
| #6 | `feat/event-catalogue` | event catalogue, per-company switches, opt-outs | `notify_preferences.event`, `notify_logs.event` (additive) |

On the trunk alone Notify has **no ingest endpoint**, so no Dot app can send it anything, and SSO redeem needs a database shared with InfoDot. Notify is also where every other app's email goes: **deploy it before enabling `NOTIFY_INGEST_*` in Tasks, Auction, Ehail or Farms.**

Migrations are not run by `adopt` (unless you ask), but **a normal `deploy` always runs them**. The rollback copy contains a SQLite file; it does **not** contain a PostgreSQL or MySQL database.

**Back up the database before the first migration run, and keep a copy of `APP_KEY`.** PR #3 encrypts stored channel secrets in place; between deploying the code and that migration a plaintext channel config would fail to decrypt, which is why a deploy migrates in the same run. The rollback copy contains `.env` but no Postgres database.

## Reverb does not run here

This app broadcasts over Reverb (`deploy/reverb.service` is for a VPS). Reverb is a long-lived websocket server and shared cPanel allows no daemons, so **the pipeline never starts, reloads or proxies it**. What happens instead:

- The frontend is built on the runner with no `VITE_REVERB_*` values. The bundle then never creates `window.Echo`, so browsers never open a websocket (checked in `resources/js/bootstrap.js`: it only initialises when `VITE_REVERB_APP_KEY` is set).
- What stops: the notification bell stops lighting up on its own (resources/js/notifications.js only subscribes when window.Echo exists). Delivery of notifications does NOT depend on Reverb.
- Nothing polls: the app has no `wire:poll` and no timers. A page reload (or the next user action) shows the current state. There is no polling fallback in the code to switch on.
- Set `BROADCAST_CONNECTION=log` in the host `.env`. With `reverb`, every broadcast job is queued for a server that does not exist and fails (the deploy prints a warning when it sees `reverb`). `.env.example` ships `reverb`.
- To get live updates later you need a host that can run `php artisan reverb:start` under a supervisor, a TLS websocket proxy, and a rebuild with `VITE_REVERB_*` set at build time. That is a separate piece of work, not a setting.

## Scheduler and queue (cron)

The pipeline installs these through the account's **shared crontab**, marked `# dot-notify`:

```
* * * * * cd <app folder> && <php> artisan schedule:run > storage/logs/cron-scheduler.log 2>&1 # dot-notify
* * * * * cd <app folder> && <php> artisan queue:work --stop-when-empty --tries=3 --timeout=60 --max-time=50 > storage/logs/cron-queue.log 2>&1 # dot-notify
```

`schedule:run` is needed because `routes/console.php` schedules `notify:dispatch-queued` (sweeps due logs onto the queue) and `notify:dispatch-schedules` (fires recurring schedules, matched to the minute) every minute. `queue:work` is **mandatory**: delivery is the queued `DeliverNotifyLog` job (PR #3: "the scheduler alone no longer sends"); with no worker nothing is delivered, and `QUEUE_CONNECTION` must not be `sync`. `DeliverNotifyLog` is unique per log, so `CACHE_STORE` must support locks (`database` does). A worker exits after at most ~50 s, so delivery can lag by up to a minute; retries back off 30 s, 2 min, 8 min, 32 min.

How the write is made safe (copied from the Dot.Agents/Dot.docs pipelines, after two incidents):

- The write takes the account-wide lock `~/.dot-crontab.lock` (`flock`, waits up to 120 s), because deploys of different apps can coincide and one crontab is shared by every Dot app.
- `crontab -l` can transiently answer *empty*. A read counts only if it is non-empty or the host says "no crontab for"; it is retried five times, and the pipeline **refuses to write** after five bad reads (an empty read once led a pipeline to install an empty crontab and kill Dot.Mines' scheduler for six weeks).
- Only lines carrying `# dot-notify` are replaced; every other line is kept. After the write the crontab is read back: the number of other lines must be unchanged and ours must be exactly 2, otherwise the previous crontab is written back and the run fails.
- If a hand-made cron line for this app exists (no marker) it is left alone and a warning says so: delete it in cPanel (Cron Jobs) or the scheduler and worker run twice.
- Each line writes its last output to `storage/logs/cron-scheduler.log` / `cron-queue.log` (overwritten every minute): read these first when a job seems not to run.
- `queue:restart` runs after the write so a worker that started before the release exits and the next minute's worker has the new code.
- The queue worker is cron-driven (`--stop-when-empty --max-time=50`, one per minute), not a daemon. Jobs wait up to a minute for a worker. Do not use the repository's `deploy/queue-worker.*`, `deploy/scheduler.*` files here: they are for a server with systemd or Supervisor.

## Prerequisites checklist

Do these once, in this order. A box ticked here means the item exists on the host or in GitHub, not just that you decided it.

**Domain and host**

- [ ] DNS for `notify.infodot.co.za` points at the cPanel account, and AutoSSL has issued a certificate (the smoke check requests `https://notify.infodot.co.za`).
- [ ] cPanel subdomain `notify.infodot.co.za` with its docroot at `~/notify.infodot.co.za`.
- [ ] MultiPHP: PHP 8.4 for that subdomain (`composer.json` needs `^8.3`, CI tests 8.4), and `php -v` over SSH shows the same version (cron and the pipeline run the CLI `php`). Extensions: the PDO driver you chose, `mbstring`, `openssl`, `curl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, plus `intl` and `bcmath` (CI installs both), and `sodium` (the DKP incident-pack command signs with it; standard in PHP 8).
- [ ] `composer`, `git`, `rsync` and `flock` exist for the SSH user (the pipeline fails with a clear message when `flock` is missing). There is no Node on the host and none is needed.
- [ ] An SSH key for GitHub Actions in the cPanel account's `authorized_keys`, and the host's key fingerprints for `DEPLOY_KNOWN_HOSTS`.

**Database**

- [ ] A database and user for this app, credentials in the host `.env`. Notify was run on SQLite and on a throwaway PostgreSQL 16 (PR #3, including the encryption backfill on legacy rows). No PR reports a MySQL run. `.env.example` defaults to PostgreSQL.

**The app folder (first install, by hand)**

The pipeline updates a live install; it does not create one. Layout, as on the other Dot apps on this account:

```
~/notify.infodot.co.za/            docroot (what the web server serves)
  index.php            shim: <?php require __DIR__.'/notify/public/index.php';
  .htaccess            Laravel rewrite rules
  build -> notify/public/build     (the deploy creates this link if it is missing)
  images -> notify/public/images ; apple-touch-icon.png, favicon-16x16.png, favicon-32x32.png, robots.txt  (each -> notify/public/<name>)
  notify/                the app folder = DEPLOY_PATH (artisan is here)
```

- [ ] Put a copy of the code in `~/notify.infodot.co.za/notify/` (cPanel Git clone of `main`, or an upload; it need not be current: `adopt` replaces tracked files) and create `.env` there (skeleton below). `.env` must exist, or the deploy stops before touching anything.
- [ ] `php artisan key:generate --force` once on the host. **It encrypts channel configs and webhook signing secrets (PR #3): copy it somewhere safe now, back it up with every database backup, and do not change it afterwards without re-encrypting.**
- [ ] The docroot files above. Copy `public/.htaccess` to `~/notify.infodot.co.za/.htaccess`; the other files in `public/` are linked from the docroot (`ln -sfn notify/public/<name> ~/notify.infodot.co.za/<name>`).
- [ ] The app folder itself must not be downloadable. The smoke check requests `/notify/.env`, `/notify/artisan` and `/notify/database/database.sqlite` and fails the run on a 200. If it does, deny the folder (for example an untracked `.htaccess` in the app folder containing `Require all denied`; not tested on this host) and run again.
- [ ] Remove any hand-made cron lines for this app in cPanel (the pipeline installs its own).

**Host `.env` skeleton** (no secret values here; fill them on the host only)

```
APP_NAME="Dot.Notify"
APP_ENV=production
APP_KEY=                      # php artisan key:generate --force, on the host, once
APP_DEBUG=false
APP_URL=https://notify.infodot.co.za

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=log      # not reverb: see "Reverb does not run here"
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_SCHEME=smtp              # or smtps, as the host's SMTP requires
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="no-reply@infodot.co.za"
MAIL_FROM_NAME="${APP_NAME}"

# SSO through InfoDot (the secret must equal ECOSYSTEM_SECRET_NOTIFY in InfoDot's .env)
INFODOT_REDEEM_URL=https://infodot.co.za/api/ecosystem/redeem
INFODOT_PLATFORM_KEY=notify
INFODOT_SERVICE_SECRET=

# Platform switcher links: one DOT_<KEY>_URL per platform in config/ecosystem.php
DOT_INFODOT_URL=https://infodot.co.za
# DOT_<KEY>_URL=https://<key>.infodot.co.za for each app you link to

# One secret per CALLING platform: openssl rand -hex 32. The same value goes in that
# app's own NOTIFY_INGEST_SECRET. Blank = that platform cannot call.
# .env.example lists only BILLING, FILES, HR, PROJECTS and TASKS; the key is read as
# NOTIFY_INGEST_SECRET_<PLATFORM KEY UPPER-CASED>, so add the others you need:
NOTIFY_INGEST_SECRET_TASKS=
NOTIFY_INGEST_SECRET_AUCTION=
NOTIFY_INGEST_SECRET_EHAIL=
NOTIFY_INGEST_SECRET_FARMS=
NOTIFY_INGEST_SECRET_BILLING=
NOTIFY_INGEST_SECRET_HR=
NOTIFY_INGEST_RATE_LIMIT=120

# The catalogue only accepts a "url" or "*_url" data value that is absolute https on the
# CALLING platform's own host from config/ecosystem.php. Defaults are *.infodot.app, so
# set the real hosts or those events are rejected (422):
DOT_TASKS_URL=https://tasks.infodot.co.za
DOT_AUCTION_URL=https://auction.infodot.co.za
DOT_EHAIL_URL=https://ehail.infodot.co.za
DOT_FARMS_URL=https://farms.infodot.co.za
DOT_BILLING_URL=https://billing.infodot.co.za
DOT_HR_URL=https://hr.infodot.co.za
```

Why these values: `QUEUE_CONNECTION`, `CACHE_STORE` and `SESSION_DRIVER` must be `database` (there is no Redis; this app's `.env.production.example` says `QUEUE_CONNECTION=redis`). `APP_ENV=production` and `APP_DEBUG=false` are forced by every deploy anyway. The `DOT_*_URL` values matter: `config/ecosystem.php` defaults them to `https://<key>.infodot.app`, which is not where these apps run, and the platform switcher builds its links from them. Mail: with `MAIL_MAILER=log` (the default) verification and password-reset emails vanish; use the SMTP account the other Dot apps use.

**InfoDot (the SSO hub) and Dot.Notify**

- [ ] In **InfoDot's** `.env`: `ECOSYSTEM_SECRET_NOTIFY` = a new random value (`openssl rand -hex 32`). In this app's `.env`: `INFODOT_SERVICE_SECRET` = the same value, `INFODOT_PLATFORM_KEY=notify`. Until the secret is set, SSO sign-in does not call InfoDot.
- [ ] Mark `notify` live in InfoDot's platform registry. InfoDot refuses to redeem tokens for platforms not marked live, so every sign-in would be refused with 403. (In this repo's copy of `config/ecosystem.php` each platform has an `active` flag; InfoDot's own registry is the authority.)
- [ ] Check that InfoDot's `ECOSYSTEM_TRUSTED_DOMAINS` (named in InfoDot's `docs/deployment-cpanel.md`) allows `notify.infodot.co.za`.
- [ ] Companies: the first sign-in by an InfoDot owner or admin creates the company's team here, linked by `teams.infodot_team_uuid`; a team that predates SSO is linked by hand with `php artisan infodot:link-team {team} {infodot-team-uuid}` (see the SSO PR).
- [ ] This app is Dot.Notify itself: set `NOTIFY_INGEST_SECRET_<KEY>` for every calling app (list in the `.env` skeleton) and give each caller the same value as its `NOTIFY_INGEST_SECRET`. Also set `DOT_<KEY>_URL` for each caller to its real `https://<key>.infodot.co.za` host, or catalogued events carrying a `url` are rejected with 422.
- [ ] Each company must be linked first (`php artisan infodot:link-team {team} {infodot-team-uuid}`), then its events switched on at `/connected-apps` (owner or admin) or with `php artisan notify:enable-defaults {team_uuid} {platform?}`. With no active rule an event is recorded as `no_rule` and nothing is sent.
- [ ] SMTP (or whatever channel you use) must work from this host: Notify delivers email itself, and Slack/webhook channels need outbound HTTPS. Webhook and Slack URLs must resolve to public addresses (private ranges are blocked by design).
- [ ] This app reads no `ANALYTICS_*` variables (the only analytics reference in `config/ecosystem.php` is the `DOT_ANALYTICS_URL` link target).

**GitHub**

- [ ] Repository secrets: `DEPLOY_HOST` (`notify.infodot.co.za`), `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`. `DEPLOY_PATH` is optional; set it to `/home/<user>/notify.infodot.co.za/notify` to skip discovery (without it the app is the one `artisan` within two levels of `~/notify.infodot.co.za`; zero or several is an error, not a guess). Do not set `DEPLOY_PATH` to a folder outside `~/notify.infodot.co.za`: the docroot is taken as its parent folder.
- [ ] No GitHub environment or required reviewer is configured by these files. If you want a second pair of eyes before a release, add a protection rule yourself.

## First deploy, in order

1. Tick the checklist above, then take a **database backup** in cPanel and copy `.env` (and `APP_KEY`) somewhere safe.
2. If the folder already holds a running copy of the app, run **Drift Report** (`drift.yml`, from `main`) and read it: how many tracked files differ, which are absent, host-only migrations.
3. Run **Deploy to Production** with `confirm=adopt`. It makes a rollback copy, turns the folder into a checkout of `origin/main` without deleting anything untracked (`.env`, `storage/`, uploads), installs dependencies, ships the build, forces `APP_DEBUG=false` and `APP_ENV=production`, **lists** pending migrations (it does not run them), rebuilds caches, installs the cron lines and checks the site.
4. Read the pending-migration list and the stacked-PR table above. Back up again if the list contains a data-changing migration.
5. Run **Deploy to Production** with `confirm=deploy` (or `adopt` with `migrate=migrate` if you want the takeover to migrate). It runs the CI gate first, then everything above, and always migrates.
6. Open the site, sign in through InfoDot, and watch `storage/logs/cron-queue.log` once to see a worker run.

## Every release after that

Run **Deploy to Production** with `confirm=deploy`, started from `main`. If a PR is merged into the trunk, the next run ships it.

## What a deploy does

1. **Check the inputs**: `confirm` must be `deploy` or `adopt`; the run must start from `main`.
2. **CI gate**: the whole of `ci.yml`. Red means nothing was touched. (`ci.yml` declares `cancel-in-progress` for its `ci-<ref>` group: a push to the trunk while a release's gate is running may cancel one of the two, and the release then goes red before it reaches the host. Run it again.)
3. **Build on the runner** (`npm ci && npm run build`, Node 22), then check `public/build/manifest.json`: every `resources/css|js/...` path an `@vite()` call asks for is a manifest key, and every built file exists and is non-empty.
4. **On the host**: a rollback copy `~/dot-notify-<mode>-<time>.tgz` (the newest three kept; excludes `vendor`, `node_modules`, `storage/logs`, `public/build`); maintenance mode; code to `origin/main` (an explicit refspec, so a single-branch checkout works); host-only migration files moved to `~/dot-notify-quarantine-<time>/`; `composer install --no-dev`; `.env` invariants; the staged build swapped into `public/build` and mirrored into the docroot's `build/`; migrations; targeted cache clears and rebuilds; `php artisan up`.
5. **Cron lines** (above).
6. **Smoke check from the runner**: `/` and `/login` answer 200, `/up` answers 200, every built CSS and JS file answers 200 from `/build/`, a `POST /up` (a harmless 405) does not return a debug page, and the app folder is not served. Each page is asked up to three times (the host's edge firewall sometimes refuses a runner IP with 403).

Never touched: `.env` (except forcing `APP_DEBUG` and `APP_ENV`), `storage/`, `database/` (including a SQLite file), uploads.

## If a run fails

- **Before maintenance mode** (inputs, CI gate, build, SSH, upload, the early host checks): the site is still serving the previous release. The log says so. Fix and re-run.
- **After maintenance mode started**: the site is left **down on purpose**, with the rollback copy's name in the log, rather than guessing at an automatic rollback. Read the log, fix the cause and re-run the workflow in the same mode (a clean run brings the site up), or restore by hand.
- **After `php artisan up`** (cron lines or smoke check): the site is serving the new release; the run is red because a cron line could not be written or a check failed. Read the message; the previous crontab is restored if verification failed.

## Rollback

Code and files only. Over SSH: put the site in maintenance mode, `tar xzf ~/dot-notify-<mode>-<time>.tgz -C <app folder>` (it restores tracked files, `.env`, `storage/` and a SQLite file as they were), `composer install --no-dev`, rebuild the caches, `php artisan up`. This pipeline always deploys the branch tip, so reverting a release for good means reverting the commit on `main` and deploying that.

**A migration is not undone by the rollback copy** unless the database is a SQLite file. Restore the database from your own backup, and read the data-changing migrations in the stacked-PR table before you do (several have a `down()` that is lossy or a no-op).

## How to read the log

- Lines starting `==` are the host script's own progress (rollback copy name, release before and after, forced invariants, pending migrations, docroot build state, crontab result).
- `::warning::` lines are things the pipeline saw and did not change: `APP_URL` is `http://`, `BROADCAST_CONNECTION=reverb`, `redis` or `sync` drivers, a stray unmarked cron line, a failed `queue:restart`.
- On the host: `storage/logs/laravel-*.log` or `laravel.log` (depending on `LOG_STACK`), `storage/logs/cron-scheduler.log`, `storage/logs/cron-queue.log`, and `php artisan queue:failed` for jobs that gave up. There is no `server-logs.yml` in this repo (InfoDot has one); use the cPanel terminal.
- The smoke check prints each URL and status. A 403 or 000 after three tries is the host or its firewall, not the app; a 500 on `/login` is the app.

## Known limits

- **Nothing was run against a host.** Untested on the real account: the `find` for the app folder, `rsync` to `public/build.incoming`, `flock`, the real `crontab`, cPanel's LiteSpeed in front of the app, the docroot wiring, the smoke URLs from a GitHub runner.
- The shared-crontab block was run against a fake `crontab` (empty first reads, other apps' lines, a stray line, a bad read-back); `flock` itself was not exercised (macOS has none), the real file lock is only as good as the reference pipelines that use it.
- No Reverb (above): no live updates, by design, until a host that can run it exists.
- No scheduler watchdog and no `/up/scheduler` probe (Dot.Agents has both); a dead cron is found by reading `cron-scheduler.log` or by a missing effect.
- No automated database backup, and no restore automation. The pipeline neither takes nor verifies a database backup; it prints the driver and warns.
- A deploy always migrates; there is no "deploy without migrating" except in `adopt`.
- A release deploys the branch tip, not a chosen commit or tag.
- One server, one crontab, one queue: the worker count is whatever `* * * * *` and `--max-time=50` give you.
- `ci.yml` is the other PR's file and is not changed here. It runs PHPUnit only; it does not run Pint, PHPStan or an asset build, and it never touches Postgres or MySQL. No stacked PR reports a MySQL run.
- Email is plain text only (PR #3 "Not done").
- Only the first matching active rule per event is used (no fan-out).
- Webhook-inbound and schedule logs are delivered by the minute sweeper, not instantly.
- There is no preferences screen and no unsubscribe link in email footers (PR #6).
