<?php

$root = dirname(__DIR__);
$gmPage = file_get_contents($root . '/html/admin/gm_approvals.html');
$gmApi = file_get_contents($root . '/api/admin/gm_approvals.php');
$poApi = file_get_contents($root . '/api/purchasing/purchase_orders.php');
$poPage = file_get_contents($root . '/html/purchasing/purchase_orders.html');
$smtpDiagnostics = file_get_contents($root . '/api/admin/smtp_diagnostics.php');
$mailerSource = file_get_contents($root . '/api/config/mailer.php');
$adminDashboard = file_get_contents($root . '/html/admin/dashboard.html');
$liveMailSync = file_get_contents($root . '/.github/scripts/sync_live_mail_env.py');
$deployWorkflow = file_get_contents($root . '/.github/workflows/deploy.yml');

$checks = [
    'GM approval explains that email is attempted immediately' =>
        str_contains($gmPage, 'the system will immediately email the signed PO'),
    'GM sees a persistent dispatch result instead of an ambiguous toast' =>
        str_contains($gmPage, 'poDispatchResultModal')
        && str_contains($gmPage, 'showPODispatchResult')
        && str_contains($gmPage, 'supplier_delivery'),
    'GM result distinguishes approval from supplier email status' =>
        str_contains($gmPage, 'GM approval')
        && str_contains($gmPage, 'Email status')
        && str_contains($gmPage, 'It does not prove that the supplier opened it.'),
    'Generic approval API cannot bypass signed PO dispatch workflow' =>
        str_contains($gmApi, 'Use the signed Purchase Order review to approve or reject this PO.'),
    'PO detail returns its recent email attempts' =>
        str_contains($poApi, "FROM purchase_order_email_attempts")
        && str_contains($poApi, "\$order['email_attempts']"),
    'Purchasing can see persistent email result and attempt history' =>
        str_contains($poPage, 'Email attempt history')
        && str_contains($poPage, 'Email not sent:')
        && str_contains($poPage, 'Email sent to')
        && str_contains($poPage, 'attempt.error_message'),
    'Expected SMTP failures keep the useful message instead of becoming generic 502 errors' =>
        str_contains($poApi, "'status' => 424")
        && str_contains($poApi, "Mailer::describeFailure")
        && !str_contains($poApi, "'status' => 502"),
    'Failed final-PO attempts refresh the detail and remain visibly retryable' =>
        str_contains($poPage, "await viewPODetail(id).catch(() => {})"),
    'Old misleading GM success wording is removed' =>
        !str_contains($gmPage, 'Purchasing can now send it to the supplier.'),
    'SMTP diagnostics require GM/Admin authentication' =>
        str_contains($smtpDiagnostics, "Auth::requireRole(['general_manager', 'admin'])"),
    'SMTP diagnostics are rate limited and avoid exposing credentials' =>
        str_contains($smtpDiagnostics, 'RateLimiter::check($rateLimitKey, 5, 600)')
        && str_contains($smtpDiagnostics, "'smtp_test:v2:user:'")
        && str_contains($smtpDiagnostics, '\' minute\' . ($retryMinutes === 1 ? \'.\' : \'s.\')')
        && !str_contains($smtpDiagnostics, "'username' => SMTP_USERNAME")
        && !str_contains($smtpDiagnostics, "'password' => SMTP_PASSWORD"),
    'SMTP diagnostics preserve actionable dependency errors' =>
        str_contains($smtpDiagnostics, '424')
        && !str_contains($smtpDiagnostics, 'Response::error(\'The SMTP test failed. Check the server error log for the technical detail.\', 502)'),
    'SMTP diagnostics expose safe route detail without resolved addresses' =>
        str_contains($smtpDiagnostics, 'safeSmtpTransportDetail')
        && str_contains($smtpDiagnostics, "'[resolved IPv4]'")
        && str_contains($smtpDiagnostics, "' Server detail: '"),
    'GoogieHost SMTP can use a certificate-verified server-local route' =>
        str_contains($mailerSource, "'server-local relay'")
        && str_contains($mailerSource, "'address' => '127.0.0.1'")
        && str_contains($mailerSource, 'str_ends_with(strtolower((string) $host), \'.googiehost.com\')'),
    'GoogieHost outbound mail uses the provider-assigned authenticated relay' =>
        str_contains($liveMailSync, '"SMTP_HOST": "cloud3.googiehost.com"')
        && str_contains($liveMailSync, '"SMTP_PORT": "587"')
        && str_contains($liveMailSync, '"SMTP_ENCRYPTION": "tls"')
        && str_contains($liveMailSync, '"SMTP_USERNAME": "notifications@highlandfresh.whf.bz"')
        && str_contains($liveMailSync, '"SMTP_PASSWORD": GOOGIEHOST_SMTP_PASSWORD'),
    'Customer-order POP3 remains separate from outbound GoogieHost SMTP' =>
        str_contains($liveMailSync, '"ORDER_MAILBOX_HOST": "pop.gmail.com"')
        && str_contains($liveMailSync, '"ORDER_MAILBOX_PASSWORD": GMAIL_APP_PASSWORD'),
    'Deployment reads the GoogieHost mailbox password only from a repository secret' =>
        str_contains($deployWorkflow, 'GOOGIEHOST_SMTP_PASSWORD: ${{ secrets.GOOGIEHOST_SMTP_PASSWORD }}')
        && !str_contains($liveMailSync, 'SMTP_PASSWORD = "'),
    'Admin dashboard exposes an explicit live email test' =>
        str_contains($adminDashboard, 'testEmailService(this)')
        && str_contains($adminDashboard, "api.post('/admin/smtp_diagnostics.php'"),
];

$failed = [];
foreach ($checks as $name => $passed) {
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) {
        $failed[] = $name;
    }
}

if ($failed) {
    fwrite(STDERR, PHP_EOL . count($failed) . ' PO email dispatch feedback check(s) failed.' . PHP_EOL);
    exit(1);
}

echo PHP_EOL . 'PO email dispatch feedback checks passed.' . PHP_EOL;
