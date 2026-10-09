# LiteUp Security Review and Local Verification

This report reviews the LiteUp PHP application for file uploads, file inclusion, CORS, brute forcing, command injection, SQL injection, and CSRF. The two confirmed findings have been fixed in the application. The checks below are intended only for a local XAMPP instance and test data you control.

## Findings

| # | Severity | File | Lines before fix | Finding | Confidence |
|---|----------|------|------------------|---------|------------|
| 1 | MEDIUM | `includes/security.php`, `public/login.php` | `security.php:57-92`; `login.php:14-19, 57-63` | Concurrent login requests could race the separate rate-limit check and failure-write operations. | 9/10 |
| 2 | LOW | `public/logout.php`, `public/navbar.php` | `logout.php:4-6`; `navbar.php:69` | A cross-site GET could log the current user out; logout had no CSRF check. | 10/10 |

No exploitable issue was confirmed in the upload, file-inclusion, CORS, SQL-injection, or command-injection surfaces reviewed. This is a source review, not a guarantee that every deployment configuration or future code change is secure.

## 1. Brute-force rate limiting

### Before: vulnerable pattern

The former flow checked the count before password verification, then updated the count later. These operations were separate, so parallel requests could all pass the check before any failure was recorded:

```php
if (login_is_rate_limited($identity)) {
    deny_login();
}

// Verify password...
record_login_failure($identity);
```

The old JSON update used a write lock only while writing. It did not hold one lock across the read, count, and write.

### Protection now in the app

`public/login.php` calls `consume_login_attempt($emailOrPhone)` before querying/verifying credentials. The function in `includes/security.php` opens the counter file, obtains an exclusive `flock`, reads/prunes timestamps, checks the five-attempt limit, and writes the new attempt before releasing the lock. It is fail-closed: inability to read or update the limiter is surfaced as an error rather than treated as permission to continue.

The counter is keyed by client IP plus normalized identity and expires attempts after 15 minutes. Five attempts are admitted in that window; a later attempt is blocked. Successful authentication clears the counter. This local-file approach coordinates PHP workers sharing the same filesystem. A multi-host deployment should use a shared transactional store such as a database or Redis instead.

### Local verification

1. Start Apache and MySQL in XAMPP. Use a disposable local database and a dummy account, never a real user's account.
2. Open `http://localhost/liteup_app/public/login.php` in a browser. Submit an incorrect password five times for that dummy identity.
3. Submit a sixth attempt. It should receive the “Please try again later” message without checking the password. Since a CSRF token is required, use the browser form rather than a bare POST without a token.
4. Optional race check: use a small number of separate local browser sessions and submit incorrect passwords for the same dummy identity at nearly the same time. The shared local counter must still block attempts beyond the configured limit.
5. Wait 15 minutes before reusing the same IP and identity after a failed test.

Do not use password lists, real accounts, remote hosts, or high-volume request tools for this demonstration.

## 2. CSRF protection on logout

### Before: vulnerable pattern

```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    session_unset();
    session_destroy();
}
```

This allowed a top-level cross-site GET to destroy a user's session. `SameSite=Lax` cookies can still accompany top-level navigations.

### Protection now in the app

`public/logout.php` rejects every method except POST with HTTP 405, validates the session token using the shared `verify_csrf()` helper, clears the session and its cookie, then redirects to login. `public/navbar.php` renders logout as a POST form containing `csrf_field()`.

### Local verification

```powershell
curl.exe -i http://localhost/liteup_app/public/logout.php
curl.exe -i -X POST http://localhost/liteup_app/public/logout.php
```

Expected results: the GET responds `405 Method Not Allowed`; the POST without a token responds `403` with the invalid-token message. Then log in to a dummy account and use the navbar's Logout button; it should log out and redirect to the login page. A GET request must not log out the browser session.

## 3. File uploads

**Review result:** No exploitable upload issue was confirmed in the inspected profile-image and funding-proof handlers. They validate MIME/type and size, generate random names with allow-listed extensions, and the uploads directory has server-side execution restrictions in `.htaccess`.

### Vulnerable pattern to recognize (do not add)

```php
move_uploaded_file($_FILES['file']['tmp_name'], 'uploads/' . $_FILES['file']['name']);
```

This trusts the client filename and does not validate file content or size. The app instead uses generated names and server-side checks. For sensitive proof documents, storing files outside the public web root and serving them through an authenticated download handler is additional privacy hardening.

### Local verification

With an authenticated dummy account, submit a harmless non-image file as a profile picture and confirm it is rejected. Inspect `uploads/.htaccess` and confirm script execution is denied. Test a valid image only against a disposable local database and local uploads folder; remove the test record/file using the app's normal cleanup procedure.

## 4. File inclusion

**Review result:** No request-controlled PHP include/require path was found; the application uses fixed include paths.

### Vulnerable pattern to recognize (do not add)

```php
include $_GET['page'];
```

If a page selector is needed, map a small allow-list of route names to fixed files rather than including a raw request value.

### Local source check

```powershell
Get-ChildItem .\public,.\admin,.\includes -Recurse -Filter *.php |
  Select-String -Pattern 'include|require'
```

Review any future include path built from request data; no runtime traversal payload is needed for this review.

## 5. CORS

**Review result:** No permissive `Access-Control-Allow-*` policy was found in the inspected PHP or `.htaccess` files. The reviewed app surfaces are server-rendered pages, not a cross-origin API.

### Vulnerable pattern to recognize (do not add)

```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
```

Do not combine credentialed requests with a wildcard origin. If a future API requires cross-origin access, allow only explicit trusted origins and methods.

### Local response check

```powershell
curl.exe -i -H "Origin: https://attacker.invalid" http://localhost/liteup_app/public/login.php
```

The response should not include `Access-Control-Allow-Origin`.

## 6. SQL injection

**Review result:** No attacker-controlled SQL injection was confirmed in the reviewed code. The login lookup and other inspected request-derived queries use prepared statements and bound parameters.

### Vulnerable pattern to recognize (do not add)

```php
$sql = "SELECT id FROM users WHERE email = '$email'";
$result = $conn->query($sql);
```

### Protected pattern used by the app

```php
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
```

### Local verification

On the local login form, use a throwaway identity such as `' OR 1=1 -- ` and an incorrect password. Authentication should fail with the ordinary generic message. This consumes one attempt for that identity, so use a separate dummy identity for the rate-limit demonstration.

## 7. Command injection

**Review result:** No shell/process-execution sink was found in the inspected application PHP.

### Vulnerable pattern to recognize (do not add)

```php
exec('some-command ' . $_GET['value']);
```

Avoid passing request data to shell commands. Prefer a library/API for the required operation; if a process is essential, use a fixed executable and validated argument arrays rather than constructing a shell command.

### Local source check

```powershell
Get-ChildItem .\public,.\admin,.\includes -Recurse -Filter *.php |
  Select-String -Pattern 'shell_exec|exec\s*\(|system\s*\(|passthru|popen|proc_open'
```

No command-injection payload should be run when the code has no such execution sink.

## Useful read-only scans and syntax checks

Run these from `C:\xampp\htdocs\liteup_app` in PowerShell:

```powershell
Get-ChildItem .\public,.\admin,.\includes -Recurse -Filter *.php |
  Select-String -Pattern '\$_FILES|move_uploaded_file|include|require|Access-Control-Allow|->query|->prepare|shell_exec|system\s*\(|exec\s*\(|verify_csrf|csrf_field'

$php = 'C:\xampp\php\php.exe'
& $php -l .\includes\security.php
& $php -l .\public\login.php
& $php -l .\public\logout.php
& $php -l .\public\navbar.php
```

If PHP is installed elsewhere, set `$php` to that installation's `php.exe`. These scans are prompts for manual code review, not proof by themselves.

## Files changed for the fixes

- `includes/security.php`: atomically consumes login attempts and clears counters under a lock.
- `public/login.php`: consumes an attempt before password verification and no longer has a separate failure-write step.
- `public/logout.php`: requires POST and a valid CSRF token; clears the session cookie.
- `public/navbar.php`: sends logout using a CSRF-protected POST form.
- `read.md`: updates the login-flow description to match the new limiter.
