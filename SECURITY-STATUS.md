# LiteUp Security Status

**Review date:** 2026-10-08  
**Scope:** PHP source in `public/`, `admin/`, and `includes/`; the local MariaDB schema; dependency advisories; and local-only checks. This is a source/configuration review, not a penetration-test certification.

## Verified protections

- Login and registration use prepared statements; the source scan found zero request-interpolated SQL candidates.
- File inclusion uses fixed code paths; the scan found zero request-controlled include paths.
- No shell execution APIs or permissive CORS headers were found in the reviewed application PHP.
- State-changing forms use CSRF tokens; logout is POST-only and revokes its remembered-device token.
- A Content Security Policy blocks inline JavaScript, limits script origins to the app and Bootstrap CDN, and disables plugins/frames. Inline styles remain allowed for compatibility and should be migrated to external stylesheets before a stricter policy is enforced.
- Login attempts are atomically rate-limited. The local test verifies attempts 1–5 are admitted and attempts 6–7 are blocked.
- Passwords use `password_hash()` / `password_verify()`. Login rotates the session ID; session cookies are `HttpOnly`, `SameSite=Lax`, and HTTPS-aware `Secure`.
- Email verification codes are salted password hashes, expire, are single-use, are attempt-limited under a row lock, and resend is throttled.
- Profile images and funding evidence are type/size/content checked and receive generated names. Funding evidence is stored in a denied directory and served only through an admin-authorized handler.
- Admin pages check roles, escape rendered database text, and protect mutations with CSRF.
- Service orders debit the wallet atomically, create a pending order and ledger row in the same transaction, use idempotency keys, and refund once on a failed order.
- The dashboard and landing page render at mobile and desktop widths without horizontal overflow. The homepage visual is a locally stored Unsplash image.

## Verification completed

- All application PHP files and the wallet-order integration test passed `php -l`.
- `tests/test_wallet_orders.php` passed: debit, idempotent replay, one-time refund, duplicate transition prevention, and completion.
- `test_rate_limiter.php` passed: five allowed attempts followed by blocked attempts.
- Composer audit found no known dependency advisories.
- The local browser smoke test passed on the homepage at 390px and 1440px and on login/registration; the hero image and CSRF form fields loaded, there was no horizontal overflow, and no console errors or failed requests were reported.
- Source scans found zero SQL interpolation candidates, zero request-controlled includes, zero shell-command sinks, and zero CORS wildcard headers.
- Live HTTP checks returned: public root redirects to the LiteUp home, `.env` is blocked with 403, and logout GET is rejected with 405.
- Browser evidence is saved in `docs/evidence-sql-injection.png` and `docs/evidence-csrf-logout.png` and embedded in the presentation.

## Presentation and evidence

- Download: `LiteUp-Security-Briefing.pptx` (six short slides).
- Rebuild: `python tools/build_security_presentation.py`.
- SQL-injection handling: `public/login.php:18` uses placeholders; `bind_param()` follows immediately after.
- CSRF verification: `includes/security.php:37`.
- Login throttling: `includes/security.php:67`.
- Profile image content check: `public/profile.php:50`.
- Admin-only proof download: `admin/download_proof.php:4`.
- Idempotent financial order processing: `includes/order_service.php:2`.

## Known limitations and launch blockers

1. **Utility fulfillment is manual.** Airtime, data, and electricity create tracked pending orders. They are not automatically fulfilled until the business connects and tests provider APIs, credentials, callbacks, reconciliation, and support workflows. Cable TV is shown as coming soon, not implemented.
2. **Local XAMPP is not a production environment.** Set up HTTPS, secure Apache/PHP configuration, least-privilege database credentials, environment secrets, backups, monitoring, and error-log access controls before launch.
3. **Apache `.htaccess` protections depend on server settings.** Confirm `AllowOverride` is enabled; otherwise upload and `.env` rules must be implemented in Apache virtual-host config.
4. **Rate limiting uses local filesystem state.** Replace it with a shared atomic store (Redis or database) for multiple web servers.
5. **No independent penetration test or privacy/legal review has been performed.** A company deployment requires an authorized external assessment and review of data-retention, consent, and incident-response requirements.
6. **Email secrets were previously exposed during troubleshooting.** Revoke the old Gmail App Password and create a new one; keep it only in an ignored `.env` or a production secret manager.
7. **Do not process real customer funds yet.** Confirm payment-provider settlement, refund authorization, reconciliation, audit logging, and operational controls before using real money.

## Safe local verification commands

Run these only from the project folder on the local XAMPP application using dummy accounts:

```powershell
php .\test_rate_limiter.php
php .\tests\test_wallet_orders.php

curl.exe -i http://localhost:8080/liteup_app/public/logout.php
curl.exe -i -X POST http://localhost:8080/liteup_app/public/logout.php
curl.exe -i -H "Origin: https://attacker.invalid" http://localhost:8080/liteup_app/public/login.php
```

Expected: logout GET returns 405; POST without a CSRF token returns 403; login response has no `Access-Control-Allow-Origin` header. For SQL-injection handling, enter the harmless string `' OR 1=1 -- ` in the login identity field with an incorrect password: it should show the ordinary invalid-login message and must not authenticate or change data.

For upload checks, use a harmless `.txt` file on your own profile form. It should be rejected; do not upload executable code.
