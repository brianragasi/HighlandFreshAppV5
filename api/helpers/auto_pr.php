<?php
/**
 * Highland Fresh System - Purchase Request Threshold Shim
 *
 * Low-stock and reorder thresholds are advisory only. Warehouse Raw creates
 * Purchase Requests manually from the PR screen or low-stock guide.
 *
 * This shim preserves the old function name for compatibility, but it never
 * creates PRs or procurement notifications.
 *
 * @package HighlandFresh
 * @version 4.1
 */

if (!defined('HIGHLAND_FRESH')) {
    http_response_code(403);
    exit('Direct access not allowed');
}

function checkThresholdAndAutoCreatePR($db, $itemType, $itemId, $triggerUserId = null)
{
    return null;
}
