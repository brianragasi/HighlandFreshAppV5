<?php

/**
 * Checks the company order mailbox and imports new customer PO attachments.
 *
 * Run from Windows Task Scheduler every five minutes:
 * C:\xampp\php\php.exe C:\xampp\htdocs\HighlandFreshAppV4\cron\sync_customer_orders.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This mailbox job can only run from the server command line.');
}

define('HIGHLAND_FRESH', true);
require_once dirname(__DIR__) . '/api/config/config.php';
require_once dirname(__DIR__) . '/api/config/database.php';
require_once dirname(__DIR__) . '/api/helpers/customer_order_import.php';
require_once dirname(__DIR__) . '/api/helpers/pop3_mailbox.php';

try {
    $db = Database::getInstance()->getConnection();
    $result = hfSyncCustomerOrderMailbox($db);
    echo sprintf(
        "[%s] Checked %d email(s): %d new, %d duplicate, %d rejected.\n",
        date('Y-m-d H:i:s'),
        $result['checked'],
        $result['imported'],
        $result['duplicates'],
        $result['rejected']
    );
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
