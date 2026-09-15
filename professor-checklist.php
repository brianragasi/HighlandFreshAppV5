<?php
/** Local demonstration companion. It never signs anyone in or changes business records. */
declare(strict_types=1);

function hfProfessorIsLocal(string $host, string $peer): bool
{
    $hostName = strtolower((string) parse_url('http://' . $host, PHP_URL_HOST));
    return in_array($hostName, ['localhost', '127.0.0.1', '[::1]'], true)
        && in_array($peer, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
}

if (PHP_SAPI === 'cli' || !hfProfessorIsLocal($_SERVER['HTTP_HOST'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '')) {
    http_response_code(404);
    exit('Not found');
}

header('Cache-Control: no-store');
session_name('HFProfessorChecklist');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict']);
$_SESSION['professor_csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['professor_csrf'];
session_write_close();

function hfProfessorJson(array $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!hash_equals($csrf, (string) ($_SERVER['HTTP_X_CHECKLIST_TOKEN'] ?? ''))) {
        hfProfessorJson(['error' => 'Reload this checklist and try again.'], 403);
    }
    if (!is_array($input) || ($input['action'] ?? '') !== 'run_security') {
        hfProfessorJson(['error' => 'Unknown checklist action.'], 400);
    }

    $testPath = __DIR__ . '/tests/security_unit_tests.php';
    $phpName = PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php';
    $phpPath = PHP_BINDIR . DIRECTORY_SEPARATOR . $phpName;
    // XAMPP's compiled PHP_BINDIR is not always its installed runtime folder.
    $iniPath = php_ini_loaded_file();
    if ($iniPath && is_file(dirname($iniPath) . DIRECTORY_SEPARATOR . $phpName)) {
        $phpPath = dirname($iniPath) . DIRECTORY_SEPARATOR . $phpName;
    }
    if (!is_file($testPath) || !is_file($phpPath) || !function_exists('proc_open')) {
        hfProfessorJson(['error' => 'The local test runner is unavailable. Run php tests/security_unit_tests.php in the project terminal.'], 503);
    }

    // Fixed command only: no browser-supplied executable, filename, or arguments.
    $process = proc_open([$phpPath, $testPath], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes, __DIR__, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        hfProfessorJson(['error' => 'The safety checks could not start.'], 503);
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $output = '';
    $errors = '';
    $deadline = microtime(true) + 20;
    $exitCode = -1;
    $timedOut = false;
    do {
        $output .= stream_get_contents($pipes[1]);
        $errors .= stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!$status['running']) {
            $exitCode = $status['exitcode'];
            break;
        }
        if (microtime(true) > $deadline) {
            $timedOut = true;
            proc_terminate($process);
            break;
        }
        usleep(50000);
    } while (true);
    $output .= stream_get_contents($pipes[1]);
    $errors .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    preg_match_all('/^(UT-\d+) \| (PASS|FAIL) \| (.+)$/m', $output, $matches, PREG_SET_ORDER);
    $results = [];
    foreach ($matches as $match) {
        $results[$match[1]] = ['status' => $match[2], 'target' => trim($match[3])];
    }
    $complete = !$timedOut;
    foreach (range(101, 107) as $id) {
        $complete = $complete && isset($results['UT-' . $id]);
    }
    hfProfessorJson([
        'results' => $results,
        'complete' => $complete,
        'exit_code' => $exitCode,
        'ran_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format(DateTimeInterface::ATOM),
        'output' => $output,
        'error' => $timedOut ? 'Checks timed out. Incomplete results are not a pass.' : (!$complete ? 'The required checks did not all return results. ' . trim($errors) : null),
    ]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    hfProfessorJson(['error' => 'Method not allowed.'], 405);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="checklist-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <title>Professor Checklist | Highland Fresh</title>
    <link rel="stylesheet" href="css/professor-checklist.css">
    <script src="js/professor-checklist-data.js" defer></script>
    <script src="js/professor-checklist.js" defer></script>
</head>
<body>
    <header class="masthead">
        <a class="brand" href="professor-checklist.php"><img src="html/images/logo.jpg" alt="Highland Fresh logo"><span>Highland Fresh<small>Local classroom demonstration</small></span></a>
        <div class="account"><span id="signedIn">Checking current account…</span><a href="html/login.html" target="_blank" rel="noopener">Open system ↗</a></div>
    </header>
    <main>
        <section class="intro">
            <div><p class="eyebrow">A guide to your professor’s testing plan</p><h1>What would you like to check?</h1><p>Choose a requirement. See the account to use, what to click, and what to show.</p></div>
            <div class="progress"><strong id="progressCount">0 / 17</strong><span>screen checks observed working</span><progress id="progressBar" max="17" value="0"></progress></div>
        </section>
        <div class="guide-note">Keep this checklist open. The real system opens in another tab. <strong>Tabs share the same login</strong>—use Logout in the system before changing accounts.</div>
        <section class="layout">
            <aside class="navigator" aria-label="Checklist sections">
                <label for="checkSearch">Find a check</label><input id="checkSearch" type="search" placeholder="Search name or ST-401…">
                <nav id="groupNav"></nav>
                <button id="downloadNotes" class="secondary">Download demo notes</button>
                <button id="printNotes" class="secondary">Print demo notes</button>
                <button id="resetNotes" class="text-button">Start a new observation record</button>
                <p class="small">Marks and notes stay in this browser. They are observations, not official test results.</p>
            </aside>
            <section class="workspace" aria-live="polite">
                <div class="section-heading"><div><p id="groupEyebrow" class="eyebrow"></p><h2 id="groupTitle"></h2><p id="groupHelp"></p></div><span id="groupCount" class="count"></span></div>
                <div id="readinessNotes"></div>
                <div id="testList"></div>
            </section>
        </section>
        <footer>This companion opens existing screens; it does not create accounts, complete sales, or change stock for you. Record only what you actually checked.</footer>
    </main>
    <dialog id="resetDialog"><form method="dialog"><h2>Start a new observation record?</h2><p>This clears checklist marks and notes only. Business records and test inventory are not changed.</p><div class="dialog-actions"><button value="cancel" class="secondary">Keep my notes</button><button value="reset" class="primary">Clear checklist notes</button></div></form></dialog>
    <section id="printSheet" class="print-sheet"></section>
    <noscript>This checklist needs JavaScript. Enable it, then reload this page.</noscript>
</body>
</html>
