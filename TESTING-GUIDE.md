# LiteUp Security Testing Guide

This guide explains how to test the two security fixes locally on your XAMPP instance.

## Setup

1. **Start XAMPP** (Apache + MySQL)
2. **Create a test database** using the existing schema in `sql/`
3. **Create a dummy user account** for testing (never use a real account)
4. **Test data to use:**
   - Email: `testuser@example.com`
   - Phone: `+2348123456789`
   - Password: `TestPass123!@`

## Test 1: Brute-force Rate Limiter (MEDIUM Severity)

### What was vulnerable before
- Five failed login attempts were checked before password verification
- The counter was updated *after* verification
- Multiple simultaneous requests could all pass the check, then update the counter separately
- This allowed attackers to bypass the 5-attempt limit using parallel requests

### How it's protected now
- `consume_login_attempt()` in `includes/security.php` now locks the counter file
- It atomically reads, checks, and increments the count *before* password verification
- Only 5 attempts per 15-minute window per IP + identity combo

### Local Test Steps

**Sequential Test (basic verification):**
1. Open `http://localhost/liteup_app/public/login.php` in your browser
2. Use your test account email/phone
3. Submit an **incorrect password** 5 times
4. On the 6th attempt, you should see: `"Invalid login details. Please try again later."`
5. This message appears WITHOUT attempting to verify the password

**Concurrent Test (verify race condition is fixed):**
1. Open 3 separate browser tabs to `http://localhost/liteup_app/public/login.php`
2. Use the same test identity (email/phone) in all 3
3. Fill in the form but don't submit
4. Quickly submit incorrect passwords in all 3 tabs within 2 seconds of each other
5. Expected: All three get different responses because only the first 5 are counted together
6. After 5 total attempts, the next requests are blocked

**Reset Test:**
- Wait 15 minutes, or use a different test identity
- Successful login automatically clears the counter

---

## Test 2: CSRF Protection on Logout (LOW Severity)

### What was vulnerable before
```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    session_unset();
    session_destroy();
}
```
- Logout accepted GET requests (no POST check)
- No CSRF token validation
- A cross-site link or redirect could log you out
- Example attack: `<img src="http://liteup.local/public/logout.php">`

### How it's protected now
- `public/logout.php` now requires POST method only (405 error for GET)
- CSRF token validation via `verify_csrf()`
- Navbar renders logout as a hidden form with CSRF token
- Session cookie is properly cleared with expiration headers

### Local Test Steps

**Test 1: GET request should be rejected**
```powershell
curl.exe -i http://localhost/liteup_app/public/logout.php
```
Expected response: `405 Method Not Allowed`

**Test 2: POST without CSRF token should be rejected**
```powershell
curl.exe -i -X POST http://localhost/liteup_app/public/logout.php
```
Expected response: `403 Forbidden` with message `"Invalid security token..."`

**Test 3: Normal logout flow should work**
1. Log in to your test account at `http://localhost/liteup_app/public/login.php`
2. Click the **Logout** button in the navbar (top-right dropdown)
3. Verify you are redirected to the login page
4. Verify you are logged out (try accessing `dashboard.php`, should redirect to login)

**Test 4: Cross-site GET cannot logout** (demonstrates the fix)
1. Log in to test account
2. Run this curl command: `curl.exe http://localhost/liteup_app/public/logout.php`
3. You should still be logged in (use browser to verify by visiting dashboard.php)
4. Only the navbar form button logs you out

---

## Test 3: SQL Injection (No vulnerability found)

### Local Verification
1. Open login form: `http://localhost/liteup_app/public/login.php`
2. In the email/phone field, enter: `' OR 1=1 -- `
3. Use any password
4. Expected: Authentication fails with generic message (not an SQL error)
5. This consumes one rate-limit attempt, so use a different test identity for rate-limit testing

### Why it's safe
The login query uses prepared statements:
```php
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1");
$stmt->bind_param("ss", $emailOrPhone, $emailOrPhone);
```

The SQL input `' OR 1=1 -- ` is treated as literal data, not as SQL syntax.

---

## Test 4: File Upload Validation (No vulnerability found)

### Local Verification
1. Log in with your test account
2. Go to **Profile** (click your avatar → Profile)
3. Try uploading a non-image file (`.txt`, `.pdf`, `.html`):
   - Expected: Upload rejected
   - Message: File type or size validation error
4. Upload a valid image (`.jpg`, `.png`):
   - Expected: Success message
   - Image appears in profile
5. Clean up: Delete the test image from your profile afterward

### Why it's safe
- Only allow-listed MIME types accepted
- Random filename generated (original name discarded)
- Size limited to 2 MB
- Server-side execution disabled in `uploads/.htaccess`

---

## Test 5: CORS (No vulnerability found)

### Local Check
```powershell
curl.exe -i -H "Origin: https://attacker.invalid" http://localhost/liteup_app/public/login.php
```

Expected: Response does NOT include `Access-Control-Allow-Origin` header

---

## Test 6: File Inclusion (No vulnerability found)

### Local Source Check
```powershell
Get-ChildItem C:\xampp\htdocs\liteup_app\public,C:\xampp\htdocs\liteup_app\admin,C:\xampp\htdocs\liteup_app\includes -Recurse -Filter *.php |
  Select-String -Pattern 'include\s+.*\$_|require\s+.*\$_'
```

Expected: No results (all includes use fixed paths)

---

## Test 7: Command Injection (No vulnerability found)

### Local Source Check
```powershell
Get-ChildItem C:\xampp\htdocs\liteup_app\public,C:\xampp\htdocs\liteup_app\admin,C:\xampp\htdocs\liteup_app\includes -Recurse -Filter *.php |
  Select-String -Pattern 'shell_exec|exec\s*\(|system\s*\(|passthru|proc_open'
```

Expected: No results (no shell execution functions found)

---

## Quick Reference: PHP Syntax Check

If you modify any of the fixed files, verify syntax:
```powershell
cd C:\xampp\htdocs\liteup_app
$php = 'C:\xampp\php\php.exe'
& $php -l .\includes\security.php
& $php -l .\public\login.php
& $php -l .\public\logout.php
& $php -l .\public\navbar.php
```

All should output: `No syntax errors detected`

---

## Files Modified for Security Fixes

| File | Change | Impact |
|------|--------|--------|
| `includes/security.php` | Replaced `login_is_rate_limited()` + `record_login_failure()` with atomic `consume_login_attempt()` | Rate limiter now holds lock across read-check-write |
| `public/login.php` | Call `consume_login_attempt()` before password check | Prevents bypass via concurrent requests |
| `public/logout.php` | Require POST + CSRF token | Cross-site logout no longer possible |
| `public/navbar.php` | Render logout as hidden form | User clicks button, form auto-submits |
| `read.md` | Updated documentation | Reflects new rate-limiter behavior |

---

## Presentation Summary for Your Final Project

**Vulnerability 1: Brute-Force Race Condition (MEDIUM)**
- **Issue:** Concurrent login attempts could bypass rate limiter
- **Fix:** Atomic file locking around counter read/update
- **Test:** Sequential attempts blocked at 6th, concurrent attempts also blocked

**Vulnerability 2: CSRF on Logout (LOW)**
- **Issue:** GET request to logout.php destroyed session without token
- **Fix:** POST-only, CSRF token required, proper cookie expiration
- **Test:** GET returns 405, logout button uses hidden form

**No Exploitable Issues Found:** SQL injection, file inclusion, CORS, command injection, file upload handling

---

## Important Notes

⚠️ **DO NOT test with:**
- Real user credentials
- Remote servers or production environments
- Automated brute-force tools or high-volume requests
- Public networks

✅ **DO test with:**
- Local XAMPP instance only
- Dummy test accounts
- Manual browser/curl requests
- Disposable test database

---

## Clean Up After Testing

```powershell
# Remove test script (optional)
Remove-Item C:\xampp\htdocs\liteup_app\test_rate_limiter.php

# Clean test database records
# Use phpMyAdmin or SQL client to delete test accounts/uploads
```

Good luck with your final project presentation!
