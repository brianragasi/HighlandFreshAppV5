<?php
/**
 * Security unit tests for instructor demonstration.
 *
 * Run:
 *   php tests/security_unit_tests.php
 */

require_once __DIR__ . '/../api/helpers/security_unit_helpers.php';

$results = [];

function recordResult(&$results, $id, $target, $passed, $expected, $actual) {
    $results[] = [
        'id' => $id,
        'target' => $target,
        'passed' => (bool) $passed,
        'expected' => $expected,
        'actual' => $actual,
    ];
}

recordResult(
    $results,
    'UT-101',
    'validateEmailFormat(email)',
    validateEmailFormat('test@com') === false && validateEmailFormat('@@domain.com') === false,
    'Return false for badly typed email addresses',
    'test@com => ' . (validateEmailFormat('test@com') ? 'true' : 'false') . ', @@domain.com => ' . (validateEmailFormat('@@domain.com') ? 'true' : 'false')
);

recordResult(
    $results,
    'UT-102',
    'validatePasswordStrength(pwd)',
    validatePasswordStrength('Short1!') === false && validatePasswordStrength('Password1') === false,
    'Return false for short password or password without special character',
    'Short1! => ' . (validatePasswordStrength('Short1!') ? 'true' : 'false') . ', Password1 => ' . (validatePasswordStrength('Password1') ? 'true' : 'false')
);

$password = 'StrongPass1!';
$hashOne = hashUserPassword($password);
$hashTwo = hashUserPassword($password);
recordResult(
    $results,
    'UT-103',
    'hashUserPassword(plainText)',
    is_string($hashOne) && strlen($hashOne) >= 60 && $hashOne !== $password && $hashOne !== $hashTwo,
    'Return secure 60+ character salted hash, different each time',
    'hash length = ' . strlen($hashOne) . ', unique hash = ' . ($hashOne !== $hashTwo ? 'yes' : 'no')
);

recordResult(
    $results,
    'UT-104',
    'verifyPasswordMatch(plain, hash)',
    verifyPasswordMatch($password, $hashOne) === true && verifyPasswordMatch($password . 'x', $hashOne) === false,
    'Return true for correct password and false for changed password',
    'correct => ' . (verifyPasswordMatch($password, $hashOne) ? 'true' : 'false') . ', changed => ' . (verifyPasswordMatch($password . 'x', $hashOne) ? 'true' : 'false')
);

$token = generateSessionToken(123);
$tokenParts = explode('.', $token);
recordResult(
    $results,
    'UT-105',
    'generateSessionToken(userId)',
    count($tokenParts) === 3 && strlen($token) > 80,
    'Return structured session token',
    'parts = ' . count($tokenParts) . ', length = ' . strlen($token)
);

recordResult(
    $results,
    'UT-106',
    'isTokenExpired(timestamp)',
    isTokenExpired(time() - 60) === true && isTokenExpired(time() + 60) === false,
    'Return true for old timestamp and false for future timestamp',
    'old => ' . (isTokenExpired(time() - 60) ? 'true' : 'false') . ', future => ' . (isTokenExpired(time() + 60) ? 'true' : 'false')
);

$unsafe = '<script>malicious()</script>';
$clean = sanitizeHtmlInput($unsafe);
recordResult(
    $results,
    'UT-107',
    'sanitizeHtmlInput(userInput)',
    strpos($clean, '<script>') === false && strpos($clean, '&lt;script&gt;') !== false,
    'Return encoded text with no active script tag',
    $clean
);

$passedCount = 0;
echo "Security Unit Test Results\n";
echo str_repeat('=', 80) . "\n";
foreach ($results as $result) {
    if ($result['passed']) {
        $passedCount++;
    }
    echo $result['id'] . ' | ' . ($result['passed'] ? 'PASS' : 'FAIL') . ' | ' . $result['target'] . "\n";
    echo 'Expected: ' . $result['expected'] . "\n";
    echo 'Actual:   ' . $result['actual'] . "\n";
    echo str_repeat('-', 80) . "\n";
}

echo "Passed {$passedCount} of " . count($results) . " tests.\n";
exit($passedCount === count($results) ? 0 : 1);
