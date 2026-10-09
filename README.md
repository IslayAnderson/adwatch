# AdWatch — This is satire

Users watch real YouTube ads and earn **90%** of the estimated impression value. Earnings sit in **escrow** for 7 days, then move into a withdrawable balance. No ad network is connected and no real money moves.

## Run it

```bash
composer install
cp .env.example .env && php artisan key:generate   # skip if .env already exists
php artisan migrate:fresh --seed
php artisan serve
```

The seeder loads the ad categories and the ad catalogue only; it creates no user accounts. Register at `/register`, then make your account an admin:

```bash
php artisan user:admin you@example.com
```

`user:admin you@example.com --remove` takes admin rights away again, and `user:password you@example.com` sets a new password.

Run `php artisan test` for the escrow and payout flow tests.

## How the money works

```
impression value (gross) = category CPM / 1000
user share               = floor(gross × 0.90)   -> escrow, released after 7 days
platform share           = gross − user share
```

Amounts are stored as integer **micro-dollars** because one view is worth a fraction of a cent. For example, a $10 CPM pays $0.01 gross, $0.009 to the user and $0.001 to the platform.

Each category is billed at the midpoint of its CPM range. Admins can edit the ranges at `/admin`.

| Category | CPM range (USD) |
|---|---|
| Finance & Insurance | 10.00 – 15.00 |
| Technology & Software | 8.00 – 12.00 |
| Automotive | 7.00 – 10.00 |
| Education / Health & Wellness | 6.00 – 9.00 |
| Travel / Beauty / Home & Lifestyle | 5.00 – 8.00 |
| Fashion & Sportswear | 4.00 – 7.00 |
| Food & Beverage | 4.00 – 6.00 |
| Gaming | 3.00 – 6.00 |
| Entertainment | 2.00 – 5.00 |

These are approximate, commonly published YouTube in-stream CPM estimates by vertical. They are placeholders, not an authoritative source, and real rates swing a lot by country, season and format.

The catalogue has **about 18,900 real YouTube ads from about 1,190 brands** across all 12 categories, in `database/data/youtube_ads.json`. **About 11,200 are British** and tagged `region = GB`: they show a 🇬🇧 badge and can be filtered with the **🇬🇧 British** chip on the ads page or the "British ads only" box in Watch Party. They were collected in two passes. The first searched YouTube as a US viewer for about 750 global brands ("<brand> commercial", "tv advert", "ad campaign"). The second searched as a UK viewer (`gl=GB`, `en-GB`) for about 700 British brands ("<brand> advert", "TV advert UK", "Christmas advert"); existing ads with British markers in the title ("advert", "UK", "ITV", "BBC", …) were tagged too. Results were kept only if the title contained the full brand name and looked like an ad, and the video was 10–180s long. Parodies, reviews and similar were filtered out, and every video was confirmed embeddable via oEmbed. Expect some noise, such as fan uploads, vintage ads, and the odd American ad tagged British. The original 13 hand-picked classics are also included. (The homepage deliberately claims "3.7M+ ads" and "2,000+ brands"; see `adwatch.marketing`.)

## Escrow lifecycle

1. **Start**: `POST /ads/{ad}/start` creates an `ad_views` row with a server timestamp.
2. **Webcam gate**: the player is locked behind an overlay until the webcam is on. A YouTube play event without a live camera track is paused immediately, and if the camera stops mid-ad the timer stops too. The feed is overlaid with a translucent pair of watchful eyes that look around and blink, plus a fake attention readout. Nothing is analysed, recorded or uploaded, and the check is client-side only.
3. **Watch**: the YouTube IFrame API counts only real playback progress. Seeking ahead, pausing or a hidden tab stops the counter. If an owner blocks embedding, a simulated timer is used instead.
4. **Complete**: the server requires the reported watch time **and** its own elapsed clock to reach `min(30s, ad length)`, which matches YouTube's view rule. Failures are stored as `rejected`. **Claim** then starts a random unwatched ad from the same category automatically.
5. **Escrow**: an `earnings` row is created with `status=escrow` and `release_at = now + 7d`.
6. **Release**: `php artisan escrow:release` (scheduled hourly) or the admin button moves due earnings to `released`. Admins can also release early or **reverse** (claw back) an earning.
7. **Withdraw**: users can request a payout of released funds (minimum $11.27). The Available balance is shown in whole cents, always rounded down. Admins mark requests paid or rejected, all simulated.

Abuse limits are a 50-view daily cap, a 24h cooldown per ad, one active view at a time, and single-use view tokens.

Settings live in `config/adwatch.php`. The core logic is in `app/Services/` (`PayoutCalculator`, `AdViewService`, `EscrowService`, `Wallet`).

## Watch party

`/watch-party` is hands-free mode. Press start (webcam required) and it plays random ads from every category back to back. Each ad is claimed automatically once it qualifies, followed by a 3-second "up next" countdown.

Five attention checks fire at random points in an ad, and the timer stops until they're cleared:
- **Look-away** (about 50% of ads): "You're not looking at the screen!". The eyes widen and the readout shows "EYES NOT DETECTED".
- **Undeclared viewer** (about 30% of ads): "Undeclared viewer detected". A fake "UNKNOWN 1" face box appears on one side of the camera feed and the eyes dart to it.
- **Competitor product** (about 20% of ads): "Competitor product detected". It names a rival advertiser from the same category as the current ad (e.g. Udemy during a Babbel ad) and shows a product-sized "UDEMY 97%" box. Pressing **I've removed it** runs a short fake "re-scan" before playback resumes.
- **Yell the brand** (about 15% of ads): 'Yell "TOYOTA!" at your screen'. This is the one real check: it opens the microphone and shows a live loudness meter, and clears once the level stays past the line for 0.3s. It measures volume only (no speech recognition), the audio never leaves the browser, and the mic is released straight away. If the mic is blocked or the permission prompt goes unanswered for 6s, an "I yelled it" button appears instead.

- **Negative emotions** (about 15% of ads): "Negative emotions detected, please smile to restart the ads". This is real smile detection using Google's MediaPipe Face Landmarker (`@mediapipe/tasks-vision@0.10.14`), running entirely in the browser on the webcam feed, so no frames are uploaded. It averages the `mouthSmileLeft`/`mouthSmileRight` scores, and a smile above 0.5 (`SMILE_SCORE`) held for 0.5s restarts the ad. It also downloads a photo of the grin (`good_girl_<YYYY-MM-DD_HH-MM-SS>.jpeg`) to the viewer's own device: the detected frame, mirrored like the preview, with a "SMILE DETECTED ✓" caption giving the advertiser and time. The fallback button doesn't take a photo. The model (a few MB) starts loading 5s into a party. If it can't load or can't find a face, an "I'm smiling" button appears after 8s.

All five tint the camera feed red. Playback controls are hidden, and unembeddable videos are skipped.

To trigger the checks manually:
- **Keyboard:** while an ad is playing, press `L` for look-away, `V` for undeclared viewer `C` for competitor product, `Y` for yell the brand or `S` for smile. The keys are listed in the browser console, not on the page.
- **URL:** `/watch-party?alert=viewer`, `?alert=lookaway`, `?alert=competitor`, `?alert=shout`, `?alert=smile`, or a comma-separated combination, forces those checks on every ad (at 3s, then 5s apart) instead of the random rolls.

Every view goes through the same `AdViewService` start/complete checks and escrow as manual watching, via JSON endpoints in `WatchPartyController`.

## Google Analytics (GA4)

Mostly server-side, via the GA4 Measurement Protocol (`app/Services/Analytics.php`), with the standard gtag.js tag on every page so Google detects the property.

**Setup:** set `GA_MEASUREMENT_ID` (already `G-DDY4W6BCG0`) and `GA_API_SECRET` in `.env`. You create the secret in GA under **Admin › Data streams › your web stream › Measurement Protocol API secrets**. Server-side sending is off until the secret is set. Set `GA_DEBUG=true` to also validate each hit against Google's debug endpoint (results go to `storage/logs`) and show events in DebugView. Set `GA_ENDPOINT=https://region1.google-analytics.com` for EU collection.

| Event | When |
|---|---|
| `page_view` | Every HTML page (middleware), with page title, URL and referrer |
| `sign_up`, `login` | Registration and login |
| `ad_view_start`, `ad_view_complete`, `ad_view_rejected` | Ad views, with advertiser, category, payout and source (`manual` or `watch_party`) |
| `purchase` | **Ecommerce:** each banked ad is a sale. `transaction_id` is `earning-<id>`, the value is the gross impression revenue in USD, there's one item per ad (brand = advertiser, category, variant = source), plus `viewer_payout` and `platform_revenue` |
| `refund` | An admin reverses an escrowed earning, refunding that transaction |
| `withdrawal_request` | Payout requested |
| `attention_check` | Watch Party alerts shown or cleared, with type, trigger and duration (reported by the page) |

Events are batched and sent after the response, with a 3s timeout, so a GA outage never slows or breaks a page. Each payload carries the visitor's IP (`ip_override`, used for location), a device category and language, a session id, and `user_id` for logged-in users (the numeric account id, never an email).

**Browser tag and consent:** gtag.js loads with `send_page_view: false`, because the server already sends page views. It's given the server's client id, and the server reuses gtag's `_ga` and `_ga_<id>` cookies when present, so browser and server hits count as the same visitor and session. Google Consent Mode v2 starts with everything denied, and a cookie banner asks first. Until a visitor accepts, nothing is tracked, browser or server. Ad signals are always denied. Set `GA_REQUIRE_CONSENT=false` to drop the opt-in, though UK/EU rules (PECR/GDPR) generally require it for analytics cookies.

## Not in this PoC

- Real ad serving or revenue. A real build would credit escrow from the ad network's reported revenue (and settle on net-30/60), not from CPM estimates.
- Real payouts, KYC/tax, fraud scoring, or device fingerprinting.
- Policy: Google AdSense/YouTube and most networks ban paying users to watch ads (incentivised traffic). A production version would need a network or direct advertisers that explicitly allow rewarded views.
