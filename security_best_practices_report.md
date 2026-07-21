# ShopVote Reviews Security Implementation Report

## Summary

Release 1.1.0 addresses the audit's high-priority admin authorization/CSRF issue and the identified URL, API transport, XML, cron-secret, and sync-integrity hardening gaps. No raw review HTML is rendered, external ShopVote links remain contextually escaped, and newly persisted provider URLs are constrained to HTTPS `shopvote.de` hosts.

The module now requires PHP 8.1+, cURL, DOM, JSON, mbstring, and SimpleXML. All PHP files pass PHP 8.2 syntax checks and Composer metadata validates. PHPUnit dependencies are not installed in this workspace, so the committed unit tests were supplemented with focused executable PHP probes; PrestaShop database/browser integration still needs to run in a real Classic and Hummingbird test shop.

## Implemented findings

### SEC-01 — Admin authorization and CSRF (fixed)

- The configuration page is now a read-only GET action protected by `read` permission.
- Settings save is a separate POST action protected by `update` permission and a dedicated `shopvote_configuration` CSRF token.
- Fetch, purge, and token rotation retain action-specific permissions and CSRF validation.

### SEC-02 — External URL injection (fixed)

- ShopVote profile, review, and EasyReviews script URLs must use HTTPS on `shopvote.de` or a subdomain.
- User-info, control characters, non-443 explicit ports, third-party hosts, and non-HTTP schemes are rejected.
- Invalid provider URLs are persisted as `NULL`, and contextual output escaping remains in place.

### SEC-03 — HTTP and XML resource hardening (fixed)

- cURL redirects are disabled, HTTPS is the only allowed protocol, TLS peer/host verification remains enabled, and response bodies are capped at 2 MiB.
- Empty successful HTTP bodies are rejected and credentials reject control characters and excessive lengths.
- XML over 2 MiB or containing `DOCTYPE` is rejected and parsing uses `LIBXML_NONET | LIBXML_NOCDATA`.
- IDs, strings, ratings, scores, and unsigned counts are bounded to their database/domain ranges.

### SEC-04 — Cron query secret (mitigated with compatibility window)

- The preferred cron interface is an authenticated POST request with `Authorization: Bearer`.
- Responses send `Cache-Control: no-store` and `Referrer-Policy: no-referrer`.
- Query-token GET remains temporarily available for 1.1 compatibility and returns explicit deprecation headers/body text.

## Integrity and privacy controls

- Summary/review/answer persistence is transactional and failures roll back the snapshot.
- A missing or invalid partial summary never replaces the last valid rating.
- Review writes use an atomic upsert; answer reads are batched.
- Signed public metric events are allowlisted, short-lived, shop-bound, aggregate-only, and optionally rate-limited through APCu. No customer, order, email, IP, or session identifiers are written to the metrics table.
- EasyReviews stores no merchant-supplied executable code. Customer email/order reference are emitted only by the order-confirmation hook and are not logged or retained by the module.

## Remaining verification

1. Run PHPUnit after installing development dependencies.
2. Run install, uninstall, 1.0-to-1.1 upgrade, rollback, concurrent upsert/lock, retention, and multishop tests against supported MySQL/MariaDB versions.
3. Run Classic and Hummingbird browser tests for all placements, the dedicated reviews page, malicious URL fixtures, keyboard/screen-reader output, and order-confirmation-only EasyReviews behavior.
4. Validate the imported EasyReviews code and product-review container with a merchant's current ShopVote account and a test order before production enablement.
