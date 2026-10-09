<?php
/**
 * Localhost-only test script for the login rate limiter.
 * 
 * Demonstrates the atomic consume_login_attempt() behavior:
 * - Five failed attempts are allowed in a 15-minute window.
 * - The sixth attempt is rejected without password verification.
 * - The counter is protected by an exclusive file lock during read-modify-write.
 * 
 * DO NOT use this against production or with real user credentials.
 */

require_once 'includes/security.php';

header('Content-Type: text/plain; charset=utf-8');

if (php_sapi_name() !== 'cli') {
    http_response_code(400);
    exit("This test script must run from the CLI.\n");
}

$testIdentity = 'test-rate-limit-' . microtime(true);

echo "=== Login Rate Limiter Test ===\n";
echo "Test identity: $testIdentity\n";
echo "Window: 15 minutes, Max attempts: 5\n\n";

for ($i = 1; $i <= 7; $i++) {
    try {
        $limited = consume_login_attempt($testIdentity);
        echo "Attempt $i: " . ($limited ? 'BLOCKED' : 'ALLOWED') . "\n";
    } catch (RuntimeException $e) {
        echo "Attempt $i: ERROR - " . htmlspecialchars($e->getMessage()) . "\n";
        exit(1);
    }
}

echo "\n=== Expected behavior ===\n";
echo "Attempts 1-5 should be ALLOWED.\n";
echo "Attempt 6 should be BLOCKED (counter limit reached).\n";
echo "Attempt 7 should be BLOCKED (counter limit still active).\n";

try {
    clear_login_failures($testIdentity);
    echo "\n✓ Counter cleared successfully.\n";
} catch (RuntimeException $e) {
    echo "\nERROR clearing counter: " . htmlspecialchars($e->getMessage()) . "\n";
    exit(1);
}
