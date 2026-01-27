# Development Requirements Sheet (Improved)

**Project:** PrestaShop 8.2.x ↔ ShopVote VotesAPI Integration
**Target platform:** PrestaShop 8.2.x (PHP 8.1/8.2 typical), MySQL/MariaDB
**External API:** ShopVote "VotesAPI" (XML over HTTPS)
**Version:** 1.1 (Improved)

---

## 1. Goal and Scope

### 1.1 Goals

- Fetch ShopVote shop ratings and latest reviews from the ShopVote VotesAPI and store them locally
- Display:
  - Aggregated rating "stars" + counts (rating summary)
  - Latest reviews (up to 25), including responses (shop/customer)
- Provide configurable widgets/hooks for:
  - Site-wide header/footer "rating stars" snippet
  - Dedicated reviews block/page (optional)
  - Sidebar widgets for left/right columns
- Output JSON-LD structured data for SEO

### 1.2 Out of Scope (Phase 1)

- Writing reviews back to ShopVote (API is retrieval-focused)
- Product-level reviews mapping (ShopVote is shop-level)
- Multi-shop / multi-tenant support beyond standard PrestaShop multistore

---

## 2. ShopVote API Requirements

### 2.1 Base URL Format

```
https://api.shopvote.de/ratings/v1/[Function]/[ShopID]/[API-Key]
```

### 2.2 Supported Functions

| Function | Description | Required Addon |
|----------|-------------|----------------|
| `ratingstars` | Rating summary/statistics only | RatingStars |
| `last25` | Last 25 reviews only | VotesAPI |
| `last25ext` | Last 25 reviews + rating summary | RatingStars + VotesAPI (combo) |

**Module behavior:**
- Default: `last25ext`
- Fallback: If `last25ext` returns HTTP 400/403, automatically try `last25` + `ratingstars` separately
- Log fallback as warning

### 2.3 Error Handling

| HTTP Code | Meaning | Module Response |
|-----------|---------|-----------------|
| 200 | Success | Parse and store data |
| 400 | Invalid parameters | Log error, keep existing data, try fallback |
| 403 | Access denied | Log error, keep existing data, try fallback |
| 5xx | Server error | Log error, keep existing data, retry later |

**Logging requirements:**
- Log status code, message, and masked URL (API key replaced with `****`)
- Store last N sync logs in database

### 2.4 Response Format

XML response containing:
- Shop profile: `shopid`, `name`, `profile`, `shopurl`, `last_vote`
- Rating summary (in `ratingstars` + `last25ext`): `rating_value` (stars/score/word), counts
- Reviews (in `last25` + `last25ext`): individual `review` elements with attributes

**Parsing requirements:**
- Use SimpleXML or DOM with explicit encoding handling (UTF-8)
- Handle German decimal format (comma separator)
- Tolerate missing optional nodes gracefully
- Support multiple date formats

---

## 3. PrestaShop Module Requirements

### 3.1 Module Structure and Compatibility

**Compatibility:**
- PrestaShop 8.0.0 - 8.99.99
- PHP 8.1+
- Required extensions: `simplexml`, `dom`, `json`, `curl`

**Architecture:**
- PSR-4 autoloading with namespace `ShopVote\ShopVoteReviews`
- Symfony services for back office (PS 8+ pattern)
- No core overrides; use hooks + services + controllers only

**Directory structure:**
```
shopvotereviews/
├── config/
│   ├── admin/services.yml      # Back office services
│   ├── front/services.yml      # Front office services
│   ├── routes.yml              # Symfony routes
│   └── services.yml            # Common services
├── controllers/front/          # Legacy front controllers
├── src/
│   ├── Api/                    # API client, XML parser
│   ├── Controller/Admin/       # Symfony admin controllers
│   ├── Controller/Front/       # Symfony front controllers
│   ├── Install/                # Installation logic
│   ├── Repository/             # Database operations
│   └── Service/                # Business logic
├── tests/Unit/                 # PHPUnit tests
├── views/
│   ├── css/                    # Frontend styles
│   └── templates/
│       ├── admin/              # Twig templates (back office)
│       └── hook/               # Smarty templates (front office)
├── composer.json
├── config.xml
├── phpunit.xml
└── shopvotereviews.php         # Main module class
```

### 3.2 Configuration UI (Back Office)

**Symfony-based admin controller** extending `FrameworkBundleAdminController`

**Settings:**

| Category | Setting | Type | Default | Validation |
|----------|---------|------|---------|------------|
| General | Enable/disable | boolean | false | - |
| API | Shop ID | string | - | alphanumeric + `_-` |
| API | API Key | string (masked) | - | stored securely, masked in UI |
| API | Preferred mode | select | `last25ext` | enum validation |
| Sync | Min interval | integer | 300 | 60-86400 seconds |
| Display | Reviews to show | integer | 5 | 1-25 |
| Display | Show reviewer name | boolean | true | - |
| Display | Excerpt length | integer | 200 | 0-1000 chars |
| Display | Show responses | boolean | true | - |
| Display | Header display | boolean | false | - |
| Display | Footer display | boolean | true | - |
| SEO | Enable JSON-LD | boolean | true | - |
| Retention | Data retention days | integer | 365 | 0 = indefinite |
| Retention | Log retention count | integer | 10 | 1-100 |

**Actions:**
- "Fetch Now" button (manual sync)
- "Purge All Data" button (GDPR compliance)
- "Rotate Token" button (security)

### 3.3 Data Model (Database)

Use `{_DB_PREFIX_}` prefix (not hardcoded `ps_`).

**Tables:**

```sql
-- Rating summary
{prefix}shopvote_shop_summary (
    id_summary INT PRIMARY KEY AUTO_INCREMENT,
    shop_id VARCHAR(64),
    shop_name VARCHAR(255),
    rating_value_stars DECIMAL(3,2),
    rating_value_score DECIMAL(5,2),
    rating_word VARCHAR(64),
    ratings_count INT,
    ratings_positive INT,
    ratings_neutral INT,
    ratings_negative INT,
    comments_count INT,
    profile_url VARCHAR(512),
    shop_url VARCHAR(512),
    last_vote DATETIME,
    fetched_at DATETIME,
    id_shop INT
)

-- Reviews
{prefix}shopvote_review (
    id_review INT PRIMARY KEY AUTO_INCREMENT,
    review_id VARCHAR(64) UNIQUE,
    review_url VARCHAR(512),
    review_date DATETIME,
    reviewer VARCHAR(255),
    review_rating_stars DECIMAL(3,2),
    review_text TEXT,
    is_verified TINYINT(1),
    fetched_at DATETIME,
    id_shop INT
)

-- Review answers
{prefix}shopvote_review_answer (
    id_answer INT PRIMARY KEY AUTO_INCREMENT,
    review_id VARCHAR(64) FK,
    answer_type VARCHAR(32),  -- 'Shop' or 'Kunde'
    answer_date DATETIME,
    answer_text TEXT,
    id_shop INT
)

-- Sync logs
{prefix}shopvote_sync_log (...)

-- Sync lock (mutex)
{prefix}shopvote_sync_lock (...)
```

**Upsert rules:**
- Summary: Insert new row on each fetch (keep history)
- Reviews: Upsert by `review_id` (no duplicates)
- Answers: Replace all for a review on each fetch

### 3.4 Sync Mechanism

**Two modes:**
1. Manual: "Fetch Now" from admin
2. Cron: `POST /module/shopvotereviews/cron?token=...`

**Requirements:**
- Token validation (reject missing/invalid)
- Mutex locking (prevent concurrent runs)
- Rate limiting (respect `min_interval`)
- Automatic fallback (last25ext → last25 + ratingstars)
- Cleanup old data after successful sync

**Cron response:** JSON with status, counts, errors

### 3.5 Front Office Display (Hooks)

**Registered hooks:**

| Hook | Widget | Description |
|------|--------|-------------|
| `displayHeader` | `rating_snippet` | Small inline rating + JSON-LD |
| `displayFooter` | `rating_badge` | Rating badge with link |
| `displayHome` | `reviews_block` | Full reviews block |
| `displayLeftColumn` | `rating_sidebar` | Sidebar widget |
| `displayRightColumn` | `rating_sidebar` | Sidebar widget |
| `actionFrontControllerSetMedia` | - | CSS registration |

**Widget interface:** Implement `WidgetInterface` for flexible placement

**Template engine:**
- Back office: Twig (PrestaShop 8 standard)
- Front office: Smarty (theme compatibility)

### 3.6 SEO / Structured Data

Output JSON-LD for `AggregateRating` in `displayHeader`:

```json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Shop Name",
  "url": "https://shop.com",
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.5",
    "bestRating": "5",
    "worstRating": "1",
    "ratingCount": 150,
    "reviewCount": 120
  }
}
```

---

## 4. Security and Compliance

### 4.1 Secrets Handling

- API key: Stored in configuration, masked in UI and logs
- Cron token: Generated on install, rotatable, required for cron
- No secrets in error messages or public responses

### 4.2 GDPR / Privacy

- Reviews contain personal data (reviewer names)
- Configuration options:
  - Anonymize reviewer names (show "M***" instead of "Max")
  - Truncate review text
  - Data retention limit
  - "Purge All Data" action
- No direct API calls from frontend (all data from local DB)

### 4.3 Security Measures

- TLS verification enabled for API calls
- SQL injection prevention (parameterized queries / pSQL)
- CSRF protection on admin forms
- Token validation for cron endpoint

---

## 5. Observability and Diagnostics

### 5.1 Admin Status Panel

Display:
- Configuration status (configured/not configured)
- Last successful fetch time
- Last error time + message
- Number of reviews stored
- Current rating summary

### 5.2 Logging

- Store last N sync logs with:
  - Timestamp
  - Function called
  - Status (success/error/warning)
  - HTTP code
  - Reviews updated count
  - Message

---

## 6. Performance Requirements

- Frontend never calls ShopVote API directly
- Frontend reads only from local DB
- Render time impact:
  - Stars snippet: O(1) DB lookup
  - Reviews block: Single query + optional join for answers
- Caching: Consider PS cache for widget output (future enhancement)

---

## 7. Edge Cases and Fallback Behavior

| Scenario | Handling |
|----------|----------|
| `last25ext` HTTP 400/403 | Fallback to `last25` + `ratingstars`, log warning |
| XML parsing fails | Keep previous data, log error with truncated raw XML |
| Shop has < 25 reviews | Display available reviews |
| No `rating_summary` | Show "No rating available" state |
| No reviews | Show summary only (if available) |
| Concurrent sync attempts | Second request rejected, return "locked" status |
| Network timeout | Log error, keep existing data |

---

## 8. Testing Requirements

### 8.1 Unit Tests

- XML parsing for all response types
- Date format parsing
- Decimal format parsing (German comma)
- Missing node handling
- Upsert logic (no duplicates)

### 8.2 Integration Tests (Optional)

- Mock HTTP client responses (200/400/403)
- Cron token validation
- Sync locking mechanism

### 8.3 Manual QA Checklist

- [ ] Fresh install → configure → manual fetch → widgets show data
- [ ] Wrong API key → 403 handled, no frontend break
- [ ] Rate limit (min interval) respected
- [ ] Purge data removes table rows
- [ ] Uninstall cleans up tables
- [ ] Token rotation updates cron URL
- [ ] JSON-LD validates at schema.org validator

---

## 9. Deliverables

1. **Source code:**
   - PrestaShop module (PS 8.2 compatible)
   - Symfony services and controllers
   - Database migrations (install/uninstall)

2. **Documentation:**
   - README with configuration steps
   - Cron setup examples
   - Troubleshooting section

3. **Tests:**
   - PHPUnit test suite
   - phpunit.xml configuration

4. **Assets:**
   - Admin Twig templates
   - Frontend Smarty templates
   - CSS styles

---

## 10. Acceptance Criteria

- [ ] Module installs/uninstalls cleanly on PS 8.2.x
- [ ] Configuration UI allows setting all parameters
- [ ] Valid API credentials → successful fetch and storage
- [ ] Frontend displays rating summary and reviews
- [ ] Invalid credentials → graceful error handling
- [ ] Cron endpoint works with valid token
- [ ] Concurrent sync prevented
- [ ] JSON-LD output validates
- [ ] GDPR options work (anonymize, purge)
- [ ] All unit tests pass

---

## 11. Implementation Notes

### HTTP Client

- Use cURL with:
  - Connect timeout: 10s
  - Request timeout: 30s
  - TLS verification: enabled
  - Follow redirects: max 3

### XML Parsing

- SimpleXML with `LIBXML_NOCDATA` flag
- Handle German decimal format (comma → dot)
- Multiple date format support

### Secret Masking

```php
// Show first 4 and last 4 characters
function maskSecret(string $secret): string {
    if (strlen($secret) <= 8) {
        return str_repeat('*', strlen($secret));
    }
    return substr($secret, 0, 4) . str_repeat('*', strlen($secret) - 8) . substr($secret, -4);
}
```

### Attribution Requirement

ShopVote requires displaying "Quelle: ShopVote.de" when using their data. This is included in all templates.

---

## Revision History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2025-01-27 | Initial requirements |
| 1.1 | 2025-01-27 | Added: PSR-4 namespace, Symfony services structure, specific hooks, Widget interface, database prefix clarification, attribution requirement |
