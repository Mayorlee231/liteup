# LiteUp Login and Registration Security

This document describes the security process used to protect the LiteUp registration and login flow from SQL injection, password attacks, forged requests, session attacks, and unsafe input.

For the current whole-project review, tested status, and production blockers, see [SECURITY-STATUS.md](SECURITY-STATUS.md). The downloadable short presentation is [LiteUp-Security-Briefing.pptx](LiteUp-Security-Briefing.pptx).

## Files Used

- `public/register.php`: registration form and account creation.
- `public/login.php`: the single supported login endpoint.
- `public/logout.php`: destroys the current session and redirects to login.
- `public/user_login.php`: legacy duplicate endpoint; it now redirects to `login.php`.
- `includes/security.php`: shared security functions.
- `includes/auth.php`: authenticated-session and admin checks.
- `includes/db.php`: database connection configuration.

## Registration Process

1. The registration page loads `includes/security.php`.
2. The security helper starts the session with strict session mode and secure cookie attributes.
3. A random CSRF token is generated with `random_bytes()` and placed in the form as a hidden field.
4. On submit, `verify_csrf()` compares the submitted token with the session token using `hash_equals()`.
5. All values are read with safe defaults instead of assuming that POST fields exist.
6. The full name is checked against a length-limited name pattern. HTML and SQL syntax are not accepted as a shortcut around validation.
7. The email is trimmed, lowercased, limited to the current database column length, and checked with `FILTER_VALIDATE_EMAIL`.
8. The phone number is restricted to an optional plus sign followed by 10 to 15 digits.
9. The password is checked on the server, not only by browser HTML validation. It must contain:
	- At least 8 characters and no more than 72 bytes for the current bcrypt-compatible password hashing path.
	- At least one uppercase letter.
	- At least one lowercase letter.
	- At least one number.
	- At least one special character.
10. The confirmation password must match exactly.
11. The existing email and phone check uses a prepared statement:

```php
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
$stmt->bind_param("ss", $email, $phone);
```

12. The password is stored only as a `password_hash()` result. The plain password is never saved.
13. The user insert uses another prepared statement, so quotes, semicolons, SQL comments, and SQL keywords remain data.
14. A wallet record is created with a prepared statement.
15. Successful registration redirects to login so refreshing the page does not repeat the POST request.

## Login Process

1. The login form receives a session-bound CSRF token.
2. The POST handler verifies the token before reading credentials.
3. The identity and password have maximum lengths to prevent oversized input.
4. `consume_login_attempt()` atomically reserves a login attempt before password verification. It locks the shared counter file while checking and updating it, preventing concurrent requests from bypassing the five-attempt limit within 15 minutes.
5. The rate limiter tracks the client address and normalized identity for a 15-minute window and blocks after five failed attempts.
6. The account lookup uses a prepared statement with bound parameters:

```php
$stmt = $conn->prepare(
	 "SELECT id, full_name, email, phone, password, role
	  FROM users WHERE email = ? OR phone = ? LIMIT 1"
);
$stmt->bind_param("ss", $emailOrPhone, $emailOrPhone);
```

7. The submitted password is checked with `password_verify()` against the stored hash.
8. Failed logins use the same generic message, `Invalid login details.` This avoids revealing whether an email or phone number exists.
9. Invalid credentials return the same generic message. Successful login clears the attempt counter.
10. On successful login, `session_regenerate_id(true)` prevents session fixation.
11. Only the required session values are stored: user ID, display name, and role.
12. The role determines whether the user goes to the public dashboard or the admin dashboard.

## How SQL Injection Is Blocked

SQL injection happens when request data is concatenated into a SQL string. The protected code does not do this for login or registration.

Unsafe pattern:

```php
$sql = "SELECT * FROM users WHERE email = '$email'";
```

Protected pattern:

```php
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
```

With a prepared statement, an input such as `anything' OR '1'='1` is treated as an email value. It cannot change the SQL command structure.

The same prepared-statement rule was applied to the admin delete, wallet approval, and tamper-code moderation actions. Numeric IDs are also validated as positive integers and allowed actions are restricted with an allow-list.

## Other Protections Added

### CSRF protection

Every state-changing form includes `csrf_field()`. Every POST handler calls `verify_csrf()`. This prevents another website from silently submitting a purchase, password change, wallet request, admin approval, or deletion using a victim's session.

### Session protection

`includes/security.php` enables strict session mode and configures `HttpOnly`, `SameSite=Lax`, and HTTPS-aware `Secure` cookies. Login also regenerates the session ID.

### Output escaping

Values read from the database are escaped with `htmlspecialchars()` before being placed into HTML. This protects transaction history, tamper history, admin tables, and profile-related output from stored XSS.

### File uploads

Profile images use generated random filenames, an allow-list of image MIME types, a 2 MB size limit, and `getimagesize()` validation. The web server should also be configured so scripts cannot execute inside `uploads/`; production storage outside the public web root is preferred.

### Database configuration

Database values can be supplied through these environment variables:

```text
LITEUP_DB_HOST
LITEUP_DB_USER
LITEUP_DB_PASSWORD
LITEUP_DB_NAME
```

For local XAMPP development, these values are stored in the project-root `.env` file. The loader reads that file without an external package. `.gitignore` excludes `.env`, and the root `.htaccess` denies web access to it. Replace the local defaults before production deployment.

### Gmail verification setup

1. Turn on two-step verification for the Gmail account that will send LiteUp mail.
2. Create a Gmail app password for LiteUp. Do not use the normal Gmail password.
3. Fill these values in `.env`:

```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-sender@gmail.com
MAIL_PASSWORD=your-16-character-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-sender@gmail.com
MAIL_FROM_NAME=LiteUp
```

4. Register a new account and confirm that the six-digit email arrives.
5. Sign in from a new browser or after deleting the `liteup_trusted_device` cookie. A second six-digit sign-in code should be required.

SMS verification is intentionally not enabled yet. Adding it requires an SMS provider and credentials; the email path is the active verification method.

Use a dedicated database user with only the permissions LiteUp requires. Do not use an unrestricted `root` account in production.

## Validation Performed

The complete PHP project was checked with:

```powershell
Get-ChildItem -Path . -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

All PHP files passed syntax validation. A source scan also checked that no PHP file contains:

- Request values directly interpolated into SQL.
- Dynamic `include` or `require` paths.
- Command execution functions such as `exec`, `system`, or `shell_exec`.
- Permissive CORS headers.

## Vulnerability Details

### File Uploads

**Problem:** A browser's file extension and client-side form rules can be bypassed. Storing an uploaded file in a public directory can be dangerous if the web server executes scripts there.

**How it could be reached:** An attacker could send a crafted multipart request directly to `public/profile.php` with a large file or a file that only pretends to be an image.

**Protection added:** The application checks the upload error, limits files to 2 MB, detects MIME type with `finfo`, requires JPEG/PNG/GIF/WebP, verifies image contents with `getimagesize()`, and creates a random filename.

**Still required:** Disable PHP execution in `uploads/`, or move uploads outside the public web root. Re-encode images in production and use restrictive permissions.

### File Inclusion

**Problem:** Including a path controlled by a request could let an attacker load local files or remote files.

**How it could be reached:** An unsafe pattern would be `include $_GET['page'];`. LiteUp does not use request values as include paths.

**Protection:** LiteUp uses fixed paths such as `require_once '../includes/db.php'`. No direct LFI/RFI path was found. Legacy broken include paths were corrected to the shared include files.

### CORS

**Problem:** A policy such as `Access-Control-Allow-Origin: *` can allow unrelated websites to read API responses. It becomes more dangerous when credentials are also allowed.

**How it could be reached:** A malicious website could use browser JavaScript to request an overly permissive endpoint and read its response.

**Protection:** No permissive CORS header was found. The browser's same-origin policy remains in effect. If an API is added later, allow only specific trusted origins and never use wildcard CORS for authenticated data.

### Brute Force

**Problem:** Unlimited login attempts let attackers guess passwords or reuse stolen credentials.

**How it could be reached:** An automated client could repeatedly POST credentials to `public/login.php`; browser validation would not stop it.

**Protection added:** Login input lengths are limited, errors are generic, failed attempts are tracked by address and identity, five failures are blocked for 15 minutes, and successful login clears the failure record. Admin MFA and a shared Redis/database limiter are recommended for production.

### Command Injection

**Problem:** Passing user input to `exec`, `system`, `shell_exec`, `passthru`, or similar functions could let the input become an operating-system command.

**Protection:** No command execution functions were found in LiteUp, and no user input is passed to a shell. Continue using PHP libraries instead of shell commands. If a command is ever unavoidable, use fixed executables and allow-listed arguments.

### SQL Injection

**Problem:** Concatenating request data into SQL can let input change the meaning of a query.

Unsafe pattern:

```php
$sql = "DELETE FROM users WHERE id = " . $_GET['delete'];
```

**How it could be reached:** The original admin deletion, tamper moderation, and wallet approval flows accepted request IDs and inserted them into SQL. An attacker could try to alter the query or cause an unauthorized change, especially because deletion used GET and had no CSRF protection.

**Protection added:** Mutations use POST, IDs are validated as positive integers, actions are allow-listed, and SQL uses prepared statements with `?` placeholders and `bind_param()`. Login and registration use the same prepared-statement approach.

With a prepared statement, an input such as `anything' OR '1'='1` is treated as a value. It cannot become SQL syntax.

### CSRF

**Problem:** Browsers automatically send session cookies. Without a CSRF token, another website could cause a logged-in user to submit a valid LiteUp request.

**How it could be reached:** An attacker could place a hidden form on another website that submits a wallet request, purchase, password change, admin approval, or deletion while the victim is logged in.

**Protection added:** `includes/security.php` creates a random session-bound token with `random_bytes()`. Forms output it with `csrf_field()`, and POST handlers verify it with `hash_equals()`. Invalid or missing tokens receive HTTP 403. Destructive user deletion was changed from a GET link to a POST form.

### Passwords and Sessions

Passwords are validated server-side with a 12-character minimum, uppercase, lowercase, number, and special character requirements. Passwords are stored with `password_hash()` and checked with `password_verify()`.

Sessions use strict mode, `HttpOnly`, `SameSite=Lax`, HTTPS-aware `Secure` cookies, and `session_regenerate_id(true)` after login. The browser's `minlength` attribute is only a user-interface convenience; PHP validation is the real control.

### Output Injection and XSS

Database values shown in transaction history, tamper history, admin tables, and profile pages are escaped with `htmlspecialchars()`. This prevents stored text from becoming executable HTML or JavaScript in another user's browser.

## Production Follow-up

Source-level protection is not the same as complete production security. Before deployment:

1. Enable HTTPS and set the production database environment variables.
2. Disable script execution in `uploads/` or move uploads outside the public web root.
3. Add database uniqueness constraints for `users.email` and `users.phone`.
4. Use database transactions and conditional balance updates for every financial operation.
5. Replace the temporary file-based login limiter with a shared store such as a database or Redis when using multiple servers.
6. Review Apache, PHP, MySQL, firewall, backup, and file-permission settings.
7. Run authenticated penetration tests against the deployed configuration.

## Completed Project Hardening

The live local database was checked before normalizing financial operations. The application now uses `wallets` as the canonical wallet table. Missing wallet rows were created with a zero balance, and a unique constraint was added so each user can have only one wallet. The migration is recorded in `sql/001_wallet_consistency.sql`.

Airtime, data, electricity, and wallet-funding approval now use conditional wallet updates and database transactions. Transaction records use the live `utility_purchase` or `deposit` enum values. Funding approval claims a pending request before crediting the wallet, preventing two concurrent approvals from crediting the same request twice.

Baseline response headers are emitted by `includes/security.php`, and `uploads/.htaccess` denies PHP/script execution in the upload directory. These protections still depend on Apache allowing the relevant `.htaccess` rules.
