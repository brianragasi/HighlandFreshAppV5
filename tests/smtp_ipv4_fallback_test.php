<?php
namespace SmtpRouteTest;

define('HIGHLAND_FRESH', true);
$calls = [];
$responses = [];
$resolved = ['192.0.2.1', '192.0.2.2', '192.0.2.3'];
function stream_socket_client($uri, &$errno, &$errstr, $timeout, $flags, $context) {
    global $calls, $responses;
    $calls[] = [$uri, $timeout, stream_context_get_options($context)];
    $next = array_shift($responses);
    $errno = $next[0];
    $errstr = $next[1];
    return $next[2];
}
function gethostbynamel($host) {
    global $resolved;
    return $resolved;
}
// Load the real implementation with socket/DNS calls replaced by deterministic
// fixtures. No network connection, credentials, or email is used in this test.
$source = file_get_contents(dirname(__DIR__) . '/api/config/mailer.php');
eval('namespace SmtpRouteTest; use \\Exception; use \\Throwable; ' . substr($source, 5));
$method = new \ReflectionMethod(Mailer::class, 'openSmtpSocket');
$method->setAccessible(true);
$context = stream_context_create(['ssl' => [
    'peer_name' => 'smtp.gmail.com', 'verify_peer' => true,
    'verify_peer_name' => true, 'SNI_enabled' => true,
]]);
function check($ok, $label) {
    if (!$ok) { throw new \RuntimeException($label); }
    echo "PASS: {$label}\n";
}
$responses = [[0, '', 'connected']];
check($method->invoke(null, 'smtp.gmail.com', 465, 'ssl', $context) === 'connected' && count($calls) === 1, 'healthy hostname route is unchanged');
$calls = [];
$responses = [[101, 'Network is unreachable', false], [0, '', 'ipv4-connected']];
check($method->invoke(null, 'smtp.gmail.com', 465, 'ssl', $context) === 'ipv4-connected', 'unreachable route recovers over IPv4');
check($calls[1][0] === 'ssl://192.0.2.1:465' && $calls[1][2]['ssl']['peer_name'] === 'smtp.gmail.com'
    && $calls[1][2]['ssl']['verify_peer'] && $calls[1][2]['ssl']['verify_peer_name'], 'IPv4 retains hostname certificate verification');
$calls = [];
$responses = [[0, 'certificate verification failed', false]];
try { $method->invoke(null, 'smtp.gmail.com', 465, 'ssl', $context); throw new \RuntimeException('Expected certificate rejection'); }
catch (\Exception $e) { check(count($calls) === 1 && str_contains($e->getMessage(), 'certificate verification failed'), 'certificate failure never triggers route retry'); }
$calls = [];
$responses = array_fill(0, 3, [101, 'Network is unreachable', false]);
try { $method->invoke(null, 'smtp.gmail.com', 465, 'ssl', $context); throw new \RuntimeException('Expected unreachable rejection'); }
catch (\Exception $e) { check(count($calls) === 3 && str_contains($e->getMessage(), 'alternate routes failed'), 'retries are bounded and both route failures are logged'); }
$calls = [];
$responses = [
    [111, 'Connection refused', false],
    [111, 'Connection refused', false],
    [111, 'Connection refused', false],
    [0, '', 'local-relay-connected'],
];
$googieContext = stream_context_create(['ssl' => [
    'peer_name' => 'cloud3.googiehost.com', 'verify_peer' => true,
    'verify_peer_name' => true, 'SNI_enabled' => true,
]]);
check($method->invoke(null, 'cloud3.googiehost.com', 465, 'ssl', $googieContext) === 'local-relay-connected', 'GoogieHost can recover through its server-local relay');
check($calls[3][0] === 'ssl://127.0.0.1:465'
    && $calls[3][2]['ssl']['peer_name'] === 'cloud3.googiehost.com'
    && $calls[3][2]['ssl']['verify_peer_name'], 'server-local route retains the public hostname certificate identity');
