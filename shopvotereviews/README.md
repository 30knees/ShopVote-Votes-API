# ShopVote Reviews - PrestaShop Module

A PrestaShop 8.2.x module that integrates with the ShopVote VotesAPI to display shop ratings and customer reviews on your store.

## Features

- Fetch and display ShopVote shop ratings and statistics
- Show the latest customer reviews (up to 25)
- Display shop responses to reviews
- Configurable widgets for header, footer, sidebar, and homepage
- JSON-LD structured data output for SEO (AggregateRating)
- Automatic and manual sync options
- GDPR-compliant privacy settings (anonymize reviewer names)
- Secure cron endpoint with token authentication
- Concurrent execution prevention (mutex locking)
- Detailed sync logging and status monitoring

## Requirements

- PrestaShop 8.0.0 - 8.99.99
- PHP 8.1 or higher
- PHP extensions: `simplexml`, `dom`, `json`, `curl`
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
| **Reviews to Display** | Number of reviews to show (1-25) |
| **Show Reviewer Name** | Show full name or anonymize (GDPR) |
| **Review Excerpt Length** | Max characters to display (0 = full text) |
| **Show Shop Responses** | Display responses to reviews |
| **Display in Header** | Show rating snippet in header |
| **Display in Footer** | Show rating badge in footer |
| **Enable JSON-LD** | Output structured data for search engines |

### Data Retention

| Setting | Description |
|---------|-------------|
| **Keep Reviews (days)** | Delete reviews older than N days (0 = keep forever) |
| **Keep Sync Logs** | Number of sync log entries to retain |

## Cron Setup

For automatic synchronization, set up a cron job to call the sync endpoint.

### Cron URL

The cron URL is displayed in the module configuration page. It includes a security token that must be provided.

```
https://your-shop.com/module/shopvotereviews/cron?token=YOUR_TOKEN
```

### Example Crontab Entries

**Every 15 minutes (recommended):**
```bash
*/15 * * * * curl -s "https://your-shop.com/module/shopvotereviews/cron?token=YOUR_TOKEN" > /dev/null 2>&1
```

**Every hour:**
```bash
0 * * * * curl -s "https://your-shop.com/module/shopvotereviews/cron?token=YOUR_TOKEN" > /dev/null 2>&1
```

**Using wget:**
```bash
*/15 * * * * wget -q -O /dev/null "https://your-shop.com/module/shopvotereviews/cron?token=YOUR_TOKEN"
```

### Token Rotation

For security, you can rotate the cron token at any time from the configuration page. After rotation, update your crontab with the new URL.

## Hooks

The module registers the following PrestaShop hooks:

| Hook | Usage |
|------|-------|
| `displayHeader` | Rating snippet + JSON-LD structured data |
| `displayFooter` | Rating badge widget |
| `displayHome` | Reviews block on homepage |
| `displayLeftColumn` | Rating sidebar widget |
| `displayRightColumn` | Rating sidebar widget |
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
4. Check sync logs for errors

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
- No direct API calls from frontend

## Attribution

When using ShopVote data, attribution "Quelle: ShopVote.de" is required and automatically included in all templates.

## Development

### Running Tests

```bash
cd modules/shopvotereviews
composer install
./vendor/bin/phpunit
```

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

### 1.0.0

- Initial release
- ShopVote VotesAPI integration
- Rating and review display widgets
- Cron synchronization
- Admin configuration interface
- JSON-LD structured data output
