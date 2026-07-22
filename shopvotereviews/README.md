# ShopVote Reviews - PrestaShop Module

A PrestaShop 8.2.x module that integrates with the ShopVote VotesAPI to display shop ratings and customer reviews on your store.

## Features

- Fetch and display ShopVote shop ratings and statistics
- Show the latest customer reviews (up to 25)
- Display shop responses to reviews
- Compact header trust strip and responsive homepage review strip, plus configurable footer, theme-column, product, and checkout placements
- Optional official ShopVote RatingStars floating badge on full-width and column layouts
- Dedicated local page for the latest 25 reviews
- Optional EasyReviews and product-review collection on order confirmation
- Aggregate, PII-free conversion and review-health reporting
- Optional advanced JSON-LD output limited to the dedicated reviews page
- Automatic and manual sync options
- GDPR-compliant privacy settings (anonymize reviewer names)
- Secure cron endpoint with bearer-token POST authentication
- Concurrent execution prevention (mutex locking)
- Detailed sync logging and status monitoring

## Requirements

- PrestaShop 8.2.0 - 8.99.99
- PHP 8.1 or higher
- PHP extensions: `simplexml`, `dom`, `json`, `curl`, `mbstring`
- ShopVote account with VotesAPI access

## Installation

### Manual Installation

1. Download or clone this repository
2. Copy the `shopvotereviews` folder to your PrestaShop `/modules/` directory
3. Run Composer to install dependencies:
   ```bash
   cd /path/to/prestashop/modules/shopvotereviews
   composer install --no-dev
   ```
4. In your PrestaShop back office, go to **Modules > Module Manager**
5. Find "ShopVote Reviews" and click **Install**

### Updating an installed copy

1. Upload the latest module package in **Modules > Module Manager** and run the offered upgrade.
2. Keep the existing module installed; the upgrade preserves saved credentials, settings, ratings, and reviews.

Uploading replaces the module files, but PrestaShop intentionally does not execute versioned upgrade scripts during that upload. Click **Upgrade** once so PrestaShop can run those scripts and record the installed version. Starting with 1.2.3, the upgrade also clears compiled theme assets and Smarty templates automatically. If an external CDN or reverse proxy caches CSS, purge that cache separately.

### Via Composer (if available)

```bash
composer require shopvote/shopvote-reviews
```

## Configuration

1. After installation, go to **Modules > Module Manager** and find "ShopVote Reviews"
2. Click **Configure** to access the settings page

### API Settings

| Setting | Description |
|---------|-------------|
| **Enable Module** | Turn the module on/off |
| **ShopVote Shop ID** | Your shop ID from ShopVote dashboard |
| **API Key** | Your VotesAPI key from ShopVote dashboard |
| **API Mode** | Select based on your ShopVote subscription:<br>- `last25ext`: Premium - reviews + statistics in one call<br>- `last25 + ratingstars`: Standard - separate calls<br>- `ratingstars`: Statistics only |
| **Minimum Sync Interval** | Minimum seconds between API calls (60-86400) |

### Display Settings

| Setting | Description |
|---------|-------------|
| **Reviews to Display** | Number of reviews used by full review widgets (1-25). The homepage strip automatically evaluates the latest 25 and displays at most two useful excerpts. |
| **Show Reviewer Name** | Show full name or anonymize (GDPR) |
| **Review Excerpt Length** | Max characters to display (0 = full text) |
| **Show Shop Responses** | Display responses to reviews |
| **Display in Header Navigation** | Show the compact, fully clickable rating trust strip with ShopVote's official 50×50 seal in `displayNav1` |
| **Display in Footer** | Show rating badge in footer |
| **Homepage / Theme Side Column / Product / Checkout** | Toggle each placement independently. Homepage uses a compact strip with two excerpts on desktop and one on mobile. The side-column widget appears only where the active theme renders a left or right column. |
| **Advanced Structured Data** | Optional output on the dedicated reviews page; self-serving organization ratings are not eligible for Google review rich results |

### Optional Floating RatingStars Badge

The official ShopVote PrestaShop integration does not ship a floating badge layout in its module files. It accepts the merchant-specific **RatingStars JavaScript code**, and ShopVote's hosted widget creates and positions the badge. This module follows that integration pattern without copying the commercial module's source code.

1. In your ShopVote merchant account, open **Graphics & Seals** and copy the floating badge/RatingStars JavaScript code.
2. Paste the complete code into **RatingStars JavaScript code**.
3. Enable **Floating RatingStars Badge** and save.

The source remains visible and editable after saving. Current ShopVote snippets containing the external script's `defer` attribute and the official `loadBadge` window-load wrapper can be pasted unchanged. Before storefront output, the module validates the `widgets.shopvote.de` HTTPS script and the supported `createRBadge`, `createBadget`, or `createVBadge` initializer, then emits canonical code through the module-owned loader. Arbitrary inline JavaScript, other script attributes, and third-party script hosts are rejected. Position, alignment, and mobile-width arguments supplied by ShopVote's `createBadget` code are retained.

See ShopVote's [PrestaShop integration guide](https://plugins.shopvote.de/shopvote-integrationsanleitung-fuer-prestashop/) and [rating graphics documentation](https://faq.shopvote.de/shopbetreiber/bewertungsgrafiken/) for the current merchant-account workflow.

#### Other Graphics & Seals code

ShopVote currently supplies several technically different embed formats. They should not share one unrestricted code field:

| ShopVote format | Recommended use | Module support |
|-----------------|-----------------|----------------|
| Floating AllVotes / VoteBadge JavaScript | Persistent trust at the screen edge | Paste the complete script into **RatingStars JavaScript code** |
| Static AllVotes JavaScript with an HTML placeholder | A deliberate position in page content | Requires a future placement-aware static-badge field and hook |
| Five-review iframe slider | Homepage or footer social proof | Requires a future validated review-widget field and placement selector |
| Linked seal image | Compact footer or theme side-column trust mark | Requires a future validated seal field and placement selector |

Do not paste iframe, image, or static-placeholder snippets into **RatingStars JavaScript code**. Keeping these formats separate lets the module validate each ShopVote host, URL, and parameter and place the result in valid page markup.

### Optional EasyReviews

ShopVote supplies EasyReviews as two separate code blocks. Paste them without modifying the placeholders:

1. Paste the HTML block into **EasyReviews HTML code**.
2. Paste the JavaScript block into **EasyReviews JavaScript code**.
3. Enable **EasyReviews** and save the module configuration.

Both fields are required for an import. The source snippets are stored so they remain visible and editable after saving, but they are escaped in the back office and never rendered directly on the storefront. Only the validated ShopVote HTTPS script URL, token, and supported options are used there. Enable product-review collection only when the corresponding ShopVote entitlement is active. Review requests are never gated by customer satisfaction.

### Data Retention

| Setting | Description |
|---------|-------------|
| **Keep Reviews (days)** | Delete reviews older than N days (0 = keep forever) |
| **Keep Sync Logs** | Number of sync log entries to retain |

## Cron Setup

For automatic synchronization, set up a cron job to call the sync endpoint.

### Hetzner konsoleH

In the Hetzner Cronjob Manager, open **Erweiterte Ansicht** and add the complete generated line shown on the module configuration page:

```bash
*/15 * * * * /usr/bin/curl "https://your-shop.com/module/shopvotereviews/cron?token=YOUR_TOKEN"
```

Do not put the cURL options in the normal **Script** field; konsoleH passes that field as a single cURL argument. The query token can appear in server logs, so rotate it if it is exposed.

### Bearer-token POST alternative

The endpoint and token are displayed on the configuration page. Use POST so the secret does not enter URL logs.

### Example Crontab Entries

**Every 15 minutes (recommended):**
```bash
*/15 * * * * curl -sS -X POST -H "Authorization: Bearer YOUR_TOKEN" "https://your-shop.com/module/shopvotereviews/cron" > /dev/null 2>&1
```

**Every hour:**
```bash
0 * * * * curl -sS -X POST -H "Authorization: Bearer YOUR_TOKEN" "https://your-shop.com/module/shopvotereviews/cron" > /dev/null 2>&1
```

Query-token GET requests remain available for hosting control panels such as Hetzner konsoleH that do not accept the required cURL options in their normal Script field.

### ShopVote TLS compatibility

The module includes a provider-specific CA chain for `api.shopvote.de` because the ShopVote server currently omits its `Thawte TLS RSA CA G1` intermediate certificate. Peer and hostname verification remain enabled. The bundled intermediate expires on 2 November 2027 and must be reviewed if ShopVote changes certificate authorities.

### Token Rotation

For security, you can rotate the cron token at any time from the configuration page. After rotation, update the bearer token in your crontab.

## Hooks

The module registers the following PrestaShop hooks:

| Hook | Usage |
|------|-------|
| `displayHeader` | Optional ShopVote-hosted floating RatingStars badge on every storefront page, plus JSON-LD on the dedicated reviews page |
| `displayNav1` | Compact header-navigation shop rating |
| `displayFooter` | Rating badge widget |
| `displayHome` | Responsive review strip placed before other homepage modules |
| `displayLeftColumn` | Theme side-column rating widget |
| `displayRightColumn` | Theme side-column rating widget |
| `displayLeftColumnProduct` | Theme side-column widget on product layouts with a left column |
| `displayRightColumnProduct` | Theme side-column widget on product layouts with a right column |
| `displayProductAdditionalInfo` | Clearly labelled “Shop rating” |
| `displayCheckoutSummaryTop` | Checkout trust rating |
| `displayOrderConfirmation` | Optional EasyReviews consent prompt |
| `actionFrontControllerSetMedia` | CSS registration |

## Widget Integration

You can also use the module as a widget in your theme:

```smarty
{widget name='shopvotereviews' hook='rating_badge'}
{widget name='shopvotereviews' hook='reviews_block'}
{widget name='shopvotereviews' hook='rating_sidebar'}
{widget name='shopvotereviews' hook='rating_snippet'}
```

## Database Tables

The module creates the following tables (prefixed with your DB prefix):

- `shopvote_shop_summary` - Rating statistics
- `shopvote_review` - Customer reviews
- `shopvote_review_answer` - Shop/customer responses
- `shopvote_sync_log` - Sync operation logs
- `shopvote_sync_lock` - Concurrency lock

## API Response Handling

### Successful Responses

The module parses XML responses from ShopVote containing:
- Shop profile information
- Rating summary (stars, score, counts)
- Individual reviews with reviewer, date, rating, text
- Review answers from shop and customers

### Error Handling

| HTTP Code | Handling |
|-----------|----------|
| 200 | Parse and store data |
| 400 | Invalid parameters - check configuration |
| 403 | Access denied - verify API key and subscription |
| 5xx | Server error - retry later |

When `last25ext` fails with 400/403, the module automatically falls back to separate `last25` + `ratingstars` calls.

## Troubleshooting

### Module not displaying ratings

1. Check if module is enabled in configuration
2. Verify Shop ID and API Key are correct
3. Click "Fetch Now" to manually sync
4. Enable the intended header, footer, homepage, theme-column, or floating-badge placement
5. Check sync logs for errors
6. For **Theme Side Column**, confirm the current page layout actually renders a left or right column. Full-width theme layouts do not call column hooks; custom themes can place `{widget name='shopvotereviews' hook='rating_sidebar'}` explicitly.
7. For a true floating badge on full-width pages, enable **Floating RatingStars Badge** and paste the current code from ShopVote **Graphics & Seals**. Confirm the code uses `https://widgets.shopvote.de/js/`.
8. Confirm version 1.3.0 or newer is installed and `/modules/shopvotereviews/config/front/services.yml` exists
9. If an external CDN or reverse proxy is present, purge its cache after installing or upgrading

### Cron not working

1. Verify the cron URL is accessible from your server
2. Check that the token is correct
3. Ensure minimum interval hasn't been reached
4. Look for "locked" status (another sync in progress)

### Reviews not updating

1. Check API mode - some require premium subscription
2. Verify minimum sync interval
3. Check sync logs for error messages
4. Try manual fetch with "Force" option

### Empty ratings display

The module shows nothing if:

- No data has been fetched yet
- ShopVote account has no ratings
- Module is disabled

## Security

- API keys are masked in logs and admin UI
- Cron endpoint requires valid token
- Concurrent sync prevention (mutex)
- TLS verification enabled for API calls
- SQL injection prevention via prepared statements

## GDPR Compliance

- Option to anonymize reviewer names
- Configurable data retention period
- "Purge All Data" action in admin
- VotesAPI credentials and rating synchronization remain server-side; the optional floating badge loads only the validated ShopVote-hosted widget selected by the merchant

## Attribution

When using ShopVote data, the language-neutral attribution `ShopVote.de` is included in the module templates. In the header, `(ShopVote.de)` links directly to the full ShopVote profile.

## Development

### Running Tests

```bash
cd modules/shopvotereviews
composer install
./vendor/bin/phpunit
```

### PrestaShop 8.2 integration environment

The persistent PrestaShop 8.2.1 and MariaDB environment is stored separately from this repository in the sibling `Prestashop82` project folder. Its base configuration does not depend on or mount this module repository.

```powershell
Set-Location ..\Prestashop82
docker compose up -d
docker compose down
```

See the standalone `Prestashop82\README.md` for local URLs, login details, and instructions for bind-mounting this module during live development.

### Project Structure

```
shopvotereviews/
├── config/                 # Symfony service definitions
│   ├── admin/
│   ├── front/
│   ├── routes.yml
│   └── services.yml
├── controllers/
│   └── front/
│       └── cron.php        # Legacy cron controller
├── src/
│   ├── Api/                # API client and XML parser
│   ├── Controller/         # Symfony controllers
│   ├── Install/            # Installation logic
│   ├── Repository/         # Database operations
│   └── Service/            # Business logic
├── tests/
│   └── Unit/               # PHPUnit tests
├── views/
│   ├── css/                # Frontend styles
│   └── templates/          # Twig/Smarty templates
├── composer.json
├── config.xml
├── phpunit.xml
└── shopvotereviews.php     # Main module file
```

## Support

For issues and feature requests, please open an issue on GitHub.

## License

MIT License - see LICENSE file for details.

## Changelog

### 1.3.0

- Replaced the five-card homepage feed with a compact conversion strip showing two automatically selected excerpts on desktop and one on mobile
- Prefer verified, meaningful, non-duplicate reviews without adding manual pinning or new analytics
- Position the module first in `displayHome` on installation or upgrade, while retaining the dedicated `/shop-reviews` page for the full review display
- Removed the duplicate reviews-page heading and reduced its excess top spacing
- Added responsive layouts for mobile, tablet, desktop, and wide desktop, and clear compiled theme assets during upgrade

### 1.2.3

- Kept the translated rating count (for example, `83 Bewertungen`) together on one line and made its color inherit the surrounding header contrast
- Clear PrestaShop's combined theme assets and Smarty cache during the module upgrade so revised storefront styling takes effect immediately

### 1.2.2

- Rebuilt the header trust strip as one compact profile link with ShopVote's official 50×50 dynamic seal (`bn=56`), stars, score, and rating count
- Added theme-resistant no-wrap sizing and a smaller mobile layout so rating fragments cannot break onto separate lines

### 1.2.1

- Accepted ShopVote's current official floating-badge snippet format, including the bare `defer` script attribute and exact `loadBadge` compatibility wrapper
- Kept strict canonical rendering: unsupported script attributes and arbitrary JavaScript inside the wrapper remain rejected

### 1.2.0

- Added the official ShopVote-hosted RatingStars/floating-badge integration pattern, including editable source persistence and strict script/initializer validation
- Redesigned the header rating as a compact, responsive one-line trust strip, with `(ShopVote.de)` linked directly to the full ShopVote profile
- Renamed the sidebar setting to **Theme Side Column** to distinguish layout-dependent column hooks from the floating badge
- Preserved the exact EasyReviews HTML and JavaScript input around leading/trailing whitespace after saving

### 1.1.3

- Kept validated EasyReviews HTML and JavaScript source visible after saving, while continuing to render only parsed values on the storefront
- Fixed switch help text overlapping the checkbox control in the PrestaShop 8.2 back office
- Added product left/right column hooks and clearer guidance for theme layouts that do not render sidebars

### 1.1.2

- Fixed the PrestaShop 8.2 storefront crash caused by MySQL `DECIMAL` ratings being returned as strings
- Added a persistent PrestaShop 8.2.1 integration environment and live widget smoke coverage

### 1.1.1

- Fixed empty front-office widgets by loading module services in PrestaShop's legacy frontend container
- Split the EasyReviews import into the separate HTML and JavaScript fields supplied by ShopVote

### 1.1.0

- Added admin permission/CSRF enforcement, URL/XML/API hardening, authenticated POST cron, and transactional partial-safe sync
- Added independent trust placements, a dedicated reviews page, accessibility improvements, EasyReviews import, product-review payloads, and aggregate growth reporting
- Added first/last-seen review timestamps, atomic upserts, batched answers, and a conservative 1.0-to-1.1 upgrade

### 1.0.0

- Initial release
- ShopVote VotesAPI integration
- Rating and review display widgets
- Cron synchronization
- Admin configuration interface
- JSON-LD structured data output
