<?php
/**
 * Shared CCP (Critical Control Point) standards
 *
 * Single source of truth for production logging and QC verification.
 * Aligned with system_context/production_staff.md:
 * - Pasteurization HTST: 75°C for 15 seconds
 * - Cooling / storage / chilling: ≤4°C
 *
 * @package HighlandFresh
 * @version 4.0
 */

if (!function_exists('ccp_get_configs')) {

/**
 * CCP check-type configurations used for pass/fail evaluation.
 */
function ccp_get_configs() {
    return [
        'chilling' => [
            'label' => 'Chilling',
            'target' => 4,
            'tolerance' => 1,
            'is_max' => true,
            'unit' => '°C',
            'standard_label' => 'Max: 4°C (±1°C)',
        ],
        'preheating' => [
            'label' => 'Pre-heating',
            'target' => 65,
            'tolerance' => 2,
            'is_max' => false,
            'unit' => '°C',
            'standard_label' => 'Min: 65°C (±2°C)',
        ],
        'homogenization' => [
            'label' => 'Homogenization',
            'target_min' => 1000,
            'target_max' => 1500,
            'unit' => 'psi',
            'standard_label' => '1000–1500 psi',
        ],
        'pasteurization' => [
            'label' => 'Pasteurization (HTST)',
            'target' => 75,
            'tolerance' => 2,
            'is_max' => false,
            'hold_time' => 15,
            'unit' => '°C',
            'standard_label' => 'Min: 75°C for 15 sec',
        ],
        'cooling' => [
            'label' => 'Cooling',
            'target' => 4,
            'tolerance' => 1,
            'is_max' => true,
            'unit' => '°C',
            'standard_label' => 'Max: 4°C (±1°C)',
        ],
        'storage' => [
            'label' => 'Storage',
            'target' => 4,
            'tolerance' => 1,
            'is_max' => true,
            'unit' => '°C',
            'standard_label' => 'Max: 4°C (±1°C)',
        ],
        'intermediate' => [
            'label' => 'Intermediate Holding',
            'target' => 4,
            'tolerance' => 2,
            'is_max' => true,
            'unit' => '°C',
            'standard_label' => 'Max: 4°C (±2°C)',
        ],
    ];
}

/**
 * CCP check types required before a production run can complete
 * and before QC can release a batch.
 */
function ccp_required_check_types() {
    return ['pasteurization', 'cooling'];
}

/**
 * Public numeric thresholds for list badges / UI (no tolerance fluff).
 */
function ccp_public_thresholds() {
    $configs = ccp_get_configs();
    return [
        'pasteurization_min' => (float) $configs['pasteurization']['target'],
        'cooling_max' => (float) $configs['cooling']['target'],
        'pasteurization_standard' => $configs['pasteurization']['standard_label'],
        'cooling_standard' => $configs['cooling']['standard_label'],
        'hold_time_secs' => (int) $configs['pasteurization']['hold_time'],
    ];
}

/**
 * Fetch latest CCP log per check_type for a production run.
 *
 * @return array[] list of log rows (one per check_type)
 */
function ccp_get_latest_logs_for_run(PDO $db, $runId) {
    if (!$runId) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT
            pcl.id,
            pcl.run_id,
            pcl.check_type,
            pcl.temperature,
            pcl.pressure_psi,
            pcl.hold_time_mins,
            pcl.hold_time_secs,
            pcl.target_temp,
            pcl.temp_tolerance,
            pcl.status,
            pcl.check_datetime,
            pcl.verified_by,
            pcl.notes,
            u.first_name,
            u.last_name
        FROM production_ccp_logs pcl
        LEFT JOIN users u ON pcl.verified_by = u.id
        WHERE pcl.run_id = ?
          AND pcl.check_datetime = (
              SELECT MAX(pcl2.check_datetime)
              FROM production_ccp_logs pcl2
              WHERE pcl2.run_id = pcl.run_id
                AND pcl2.check_type = pcl.check_type
          )
        ORDER BY FIELD(
            pcl.check_type,
            'chilling', 'preheating', 'homogenization', 'pasteurization',
            'cooling', 'storage', 'intermediate'
        ), pcl.check_datetime
    ");
    $stmt->execute([(int) $runId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Fetch full CCP log history for a production run (all readings).
 */
function ccp_get_all_logs_for_run(PDO $db, $runId) {
    if (!$runId) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT
            pcl.id,
            pcl.run_id,
            pcl.check_type,
            pcl.temperature,
            pcl.pressure_psi,
            pcl.hold_time_mins,
            pcl.hold_time_secs,
            pcl.target_temp,
            pcl.temp_tolerance,
            pcl.status,
            pcl.check_datetime,
            pcl.verified_by,
            pcl.notes,
            u.first_name,
            u.last_name
        FROM production_ccp_logs pcl
        LEFT JOIN users u ON pcl.verified_by = u.id
        WHERE pcl.run_id = ?
        ORDER BY pcl.check_datetime ASC, pcl.id ASC
    ");
    $stmt->execute([(int) $runId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Index latest logs by check_type.
 */
function ccp_index_logs_by_type(array $logs) {
    $byType = [];
    foreach ($logs as $log) {
        $byType[$log['check_type']] = $log;
    }
    return $byType;
}

/**
 * Extract pasteurization / cooling temps (and hold time) from latest logs.
 *
 * @return array{pasteurization_temp: ?float, cooling_temp: ?float, pasteurization_hold_secs: ?int, logs_by_type: array}
 */
function ccp_extract_temps_from_logs(array $latestLogs) {
    $byType = ccp_index_logs_by_type($latestLogs);

    $past = $byType['pasteurization'] ?? null;
    $cool = $byType['cooling'] ?? null;

    return [
        'pasteurization_temp' => isset($past['temperature']) && $past['temperature'] !== null
            ? (float) $past['temperature']
            : null,
        'cooling_temp' => isset($cool['temperature']) && $cool['temperature'] !== null
            ? (float) $cool['temperature']
            : null,
        'pasteurization_hold_secs' => isset($past['hold_time_secs'])
            ? (int) $past['hold_time_secs']
            : null,
        'pasteurization_status' => $past['status'] ?? null,
        'cooling_status' => $cool['status'] ?? null,
        'logs_by_type' => $byType,
    ];
}

/**
 * Format a human-readable recorded value from a CCP log row.
 */
function ccp_format_recorded_value(array $log) {
    $configs = ccp_get_configs();
    $type = $log['check_type'] ?? '';
    $config = $configs[$type] ?? null;

    if ($type === 'homogenization') {
        $psi = $log['pressure_psi'] ?? null;
        return $psi !== null && $psi !== '' ? $psi . ' psi' : '—';
    }

    $temp = $log['temperature'] ?? null;
    if ($temp === null || $temp === '') {
        return '—';
    }

    $value = rtrim(rtrim(number_format((float) $temp, 2, '.', ''), '0'), '.') . '°C';

    if ($type === 'pasteurization' && !empty($log['hold_time_secs'])) {
        $value .= ' / ' . (int) $log['hold_time_secs'] . 's';
    }

    return $value;
}

/**
 * Build side-by-side comparison rows (actual vs standard) for required + logged CCPs.
 */
function ccp_build_comparison_rows(array $latestLogs) {
    $configs = ccp_get_configs();
    $byType = ccp_index_logs_by_type($latestLogs);
    $required = ccp_required_check_types();

    // Show required first, then any other logged types
    $order = array_values(array_unique(array_merge($required, array_keys($byType))));

    $rows = [];
    foreach ($order as $type) {
        $config = $configs[$type] ?? null;
        $log = $byType[$type] ?? null;
        $label = $config['label'] ?? ucfirst(str_replace('_', ' ', $type));
        $standard = $config['standard_label'] ?? '—';

        if (!$log) {
            $rows[] = [
                'check_type' => $type,
                'control_point_name' => $label,
                'recorded_value' => null,
                'recorded_value_display' => '—',
                'unit' => $config['unit'] ?? '°C',
                'standard' => $standard,
                'target_temp' => $config['target'] ?? null,
                'is_max' => $config['is_max'] ?? null,
                'status' => 'missing',
                'status_label' => 'No Data',
                'met_standard' => false,
                'required' => in_array($type, $required, true),
                'check_datetime' => null,
                'recorded_by_name' => null,
            ];
            continue;
        }

        $status = $log['status'] ?? 'pass';
        $met = ($status === 'pass' || $status === 'warning'); // warning = marginal but allowed at production

        $statusLabels = [
            'pass' => 'Pass',
            'warning' => 'Warning',
            'fail' => 'Fail',
        ];

        $recordedBy = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''));

        $rows[] = [
            'check_type' => $type,
            'control_point_name' => $label,
            'recorded_value' => $type === 'homogenization'
                ? ($log['pressure_psi'] !== null ? (float) $log['pressure_psi'] : null)
                : ($log['temperature'] !== null ? (float) $log['temperature'] : null),
            'recorded_value_display' => ccp_format_recorded_value($log),
            'unit' => $config['unit'] ?? '°C',
            'standard' => $standard,
            'target_temp' => isset($log['target_temp']) ? (float) $log['target_temp'] : ($config['target'] ?? null),
            'is_max' => $config['is_max'] ?? null,
            'status' => $status,
            'status_label' => $statusLabels[$status] ?? ucfirst($status),
            'met_standard' => $met && $status !== 'fail',
            'required' => in_array($type, $required, true),
            'check_datetime' => $log['check_datetime'] ?? null,
            'recorded_by_name' => $recordedBy !== '' ? $recordedBy : null,
            'hold_time_secs' => isset($log['hold_time_secs']) ? (int) $log['hold_time_secs'] : null,
        ];
    }

    return $rows;
}

/**
 * Build a compact summary used by list views and release gating.
 */
function ccp_build_summary(array $latestLogs, $pasteurizationTemp = null, $coolingTemp = null) {
    $thresholds = ccp_public_thresholds();
    $extracted = ccp_extract_temps_from_logs($latestLogs);
    $required = ccp_required_check_types();
    $byType = $extracted['logs_by_type'];

    $pastTemp = $extracted['pasteurization_temp'] !== null
        ? $extracted['pasteurization_temp']
        : ($pasteurizationTemp !== null && $pasteurizationTemp !== '' ? (float) $pasteurizationTemp : null);
    $coolTemp = $extracted['cooling_temp'] !== null
        ? $extracted['cooling_temp']
        : ($coolingTemp !== null && $coolingTemp !== '' ? (float) $coolingTemp : null);

    $missing = [];
    $failed = [];
    $warnings = [];

    foreach ($required as $type) {
        if (!isset($byType[$type])) {
            // Fall back to denormalized batch fields only for temp presence
            if ($type === 'pasteurization' && $pastTemp === null) {
                $missing[] = $type;
            } elseif ($type === 'cooling' && $coolTemp === null) {
                $missing[] = $type;
            } elseif (!in_array($type, ['pasteurization', 'cooling'], true)) {
                $missing[] = $type;
            } else {
                // Have denormalized temp only — evaluate against standard
                $config = ccp_get_configs()[$type];
                $temp = $type === 'pasteurization' ? $pastTemp : $coolTemp;
                if ($config['is_max']) {
                    if ($temp > $config['target'] + $config['tolerance']) {
                        $failed[] = $type;
                    } elseif ($temp > $config['target']) {
                        $warnings[] = $type;
                    }
                } else {
                    if ($temp < $config['target'] - $config['tolerance']) {
                        $failed[] = $type;
                    } elseif ($temp < $config['target']) {
                        $warnings[] = $type;
                    }
                }
            }
            continue;
        }

        $status = $byType[$type]['status'] ?? 'pass';
        if ($status === 'fail') {
            $failed[] = $type;
        } elseif ($status === 'warning') {
            $warnings[] = $type;
        }
    }

    $allPassed = empty($missing) && empty($failed);

    return [
        'pasteurization_temp' => $pastTemp,
        'cooling_temp' => $coolTemp,
        'pasteurization_hold_secs' => $extracted['pasteurization_hold_secs'],
        'pasteurization_status' => $extracted['pasteurization_status']
            ?? ($pastTemp !== null ? (empty($failed) && !in_array('pasteurization', $warnings, true) ? 'pass' : (in_array('pasteurization', $failed, true) ? 'fail' : 'warning')) : 'missing'),
        'cooling_status' => $extracted['cooling_status']
            ?? ($coolTemp !== null ? (in_array('cooling', $failed, true) ? 'fail' : (in_array('cooling', $warnings, true) ? 'warning' : 'pass')) : 'missing'),
        'missing' => $missing,
        'failed' => $failed,
        'warnings' => $warnings,
        'all_required_logged' => empty($missing),
        'all_passed' => $allPassed,
        'can_release' => $allPassed,
        'thresholds' => $thresholds,
        'message' => ccp_summary_message($allPassed, $missing, $failed, $warnings),
    ];
}

function ccp_summary_message($allPassed, array $missing, array $failed, array $warnings) {
    if ($allPassed && empty($warnings)) {
        return 'All required CCP checks met production standards';
    }
    if ($allPassed && !empty($warnings)) {
        return 'Required CCP checks present with warnings: ' . implode(', ', $warnings);
    }
    $parts = [];
    if (!empty($missing)) {
        $parts[] = 'Missing: ' . implode(', ', $missing);
    }
    if (!empty($failed)) {
        $parts[] = 'Failed: ' . implode(', ', $failed);
    }
    return implode('; ', $parts);
}

/**
 * Normalize a raw production_ccp_logs row for the QC UI.
 */
function ccp_normalize_log_for_ui(array $log) {
    $configs = ccp_get_configs();
    $type = $log['check_type'] ?? '';
    $config = $configs[$type] ?? null;
    $recordedBy = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''));

    return [
        'id' => (int) ($log['id'] ?? 0),
        'run_id' => isset($log['run_id']) ? (int) $log['run_id'] : null,
        'check_type' => $type,
        'control_point_name' => $config['label'] ?? ucfirst(str_replace('_', ' ', $type)),
        'temperature' => isset($log['temperature']) && $log['temperature'] !== null ? (float) $log['temperature'] : null,
        'pressure_psi' => isset($log['pressure_psi']) && $log['pressure_psi'] !== null ? (int) $log['pressure_psi'] : null,
        'hold_time_secs' => isset($log['hold_time_secs']) ? (int) $log['hold_time_secs'] : null,
        'recorded_value' => $type === 'homogenization'
            ? ($log['pressure_psi'] ?? null)
            : ($log['temperature'] ?? null),
        'recorded_value_display' => ccp_format_recorded_value($log),
        'unit' => $config['unit'] ?? '°C',
        'standard' => $config['standard_label'] ?? '—',
        'target_temp' => isset($log['target_temp']) ? (float) $log['target_temp'] : null,
        'temp_tolerance' => isset($log['temp_tolerance']) ? (float) $log['temp_tolerance'] : null,
        'status' => $log['status'] ?? null,
        'check_datetime' => $log['check_datetime'] ?? null,
        'recorded_at' => $log['check_datetime'] ?? null,
        'recorded_by_name' => $recordedBy !== '' ? $recordedBy : null,
        'notes' => $log['notes'] ?? null,
    ];
}

/**
 * Persist denormalized CCP temps onto production_batches when missing.
 */
function ccp_persist_batch_temps(PDO $db, $batchId, $pasteurizationTemp, $coolingTemp) {
    if (!$batchId) {
        return;
    }

    $stmt = $db->prepare("
        UPDATE production_batches
        SET pasteurization_temp = COALESCE(pasteurization_temp, ?),
            cooling_temp = COALESCE(cooling_temp, ?)
        WHERE id = ?
    ");
    $stmt->execute([
        $pasteurizationTemp,
        $coolingTemp,
        (int) $batchId,
    ]);
}

/**
 * Enrich a single production_batches row with CCP logs, comparison, and summary.
 * Optionally backfills denormalized temp columns when empty.
 */
function ccp_enrich_batch(PDO $db, array $batch, $includeFullHistory = true, $persistMissingTemps = true) {
    $runId = $batch['run_id'] ?? null;

    $latestLogs = ccp_get_latest_logs_for_run($db, $runId);
    $allLogs = $includeFullHistory ? ccp_get_all_logs_for_run($db, $runId) : $latestLogs;

    $extracted = ccp_extract_temps_from_logs($latestLogs);

    $origPast = $batch['pasteurization_temp'] ?? null;
    $origCool = $batch['cooling_temp'] ?? null;
    $needsBackfill = $persistMissingTemps
        && !empty($batch['id'])
        && (
            (($origPast === null || $origPast === '') && $extracted['pasteurization_temp'] !== null)
            || (($origCool === null || $origCool === '') && $extracted['cooling_temp'] !== null)
        );

    // Prefer live production CCP values; fall back to denormalized columns
    if ($extracted['pasteurization_temp'] !== null) {
        $batch['pasteurization_temp'] = $extracted['pasteurization_temp'];
    }
    if ($extracted['cooling_temp'] !== null) {
        $batch['cooling_temp'] = $extracted['cooling_temp'];
    }

    if ($needsBackfill) {
        ccp_persist_batch_temps(
            $db,
            $batch['id'],
            $extracted['pasteurization_temp'],
            $extracted['cooling_temp']
        );
    }

    $comparison = ccp_build_comparison_rows($latestLogs);
    $summary = ccp_build_summary(
        $latestLogs,
        $batch['pasteurization_temp'] ?? null,
        $batch['cooling_temp'] ?? null
    );

    $batch['ccp_logs'] = array_map('ccp_normalize_log_for_ui', $allLogs);
    $batch['ccp_latest'] = array_map('ccp_normalize_log_for_ui', $latestLogs);
    $batch['ccp_comparison'] = $comparison;
    $batch['ccp_summary'] = $summary;
    $batch['ccp_standards'] = ccp_public_thresholds();

    return $batch;
}

/**
 * Efficient list enrichment: one query for all run_ids, fill temps + summary.
 */
function ccp_enrich_batches_list(PDO $db, array $batches) {
    if (empty($batches)) {
        return $batches;
    }

    $runIds = [];
    foreach ($batches as $b) {
        if (!empty($b['run_id'])) {
            $runIds[] = (int) $b['run_id'];
        }
    }
    $runIds = array_values(array_unique($runIds));

    $logsByRun = [];
    if (!empty($runIds)) {
        $placeholders = implode(',', array_fill(0, count($runIds), '?'));
        $stmt = $db->prepare("
            SELECT
                pcl.id,
                pcl.run_id,
                pcl.check_type,
                pcl.temperature,
                pcl.pressure_psi,
                pcl.hold_time_mins,
                pcl.hold_time_secs,
                pcl.target_temp,
                pcl.temp_tolerance,
                pcl.status,
                pcl.check_datetime,
                pcl.verified_by,
                pcl.notes,
                u.first_name,
                u.last_name
            FROM production_ccp_logs pcl
            LEFT JOIN users u ON pcl.verified_by = u.id
            WHERE pcl.run_id IN ({$placeholders})
              AND pcl.check_type IN ('pasteurization', 'cooling')
              AND pcl.check_datetime = (
                  SELECT MAX(pcl2.check_datetime)
                  FROM production_ccp_logs pcl2
                  WHERE pcl2.run_id = pcl.run_id
                    AND pcl2.check_type = pcl.check_type
              )
        ");
        $stmt->execute($runIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $logsByRun[(int) $row['run_id']][] = $row;
        }
    }

    $thresholds = ccp_public_thresholds();
    $enriched = [];

    foreach ($batches as $batch) {
        $runId = !empty($batch['run_id']) ? (int) $batch['run_id'] : null;
        $latestLogs = $runId && isset($logsByRun[$runId]) ? $logsByRun[$runId] : [];
        $extracted = ccp_extract_temps_from_logs($latestLogs);

        $origPast = $batch['pasteurization_temp'] ?? null;
        $origCool = $batch['cooling_temp'] ?? null;
        $needsBackfill = !empty($batch['id'])
            && (
                (($origPast === null || $origPast === '') && $extracted['pasteurization_temp'] !== null)
                || (($origCool === null || $origCool === '') && $extracted['cooling_temp'] !== null)
            );

        if ($extracted['pasteurization_temp'] !== null) {
            $batch['pasteurization_temp'] = $extracted['pasteurization_temp'];
        }
        if ($extracted['cooling_temp'] !== null) {
            $batch['cooling_temp'] = $extracted['cooling_temp'];
        }

        if ($needsBackfill) {
            ccp_persist_batch_temps(
                $db,
                $batch['id'],
                $extracted['pasteurization_temp'],
                $extracted['cooling_temp']
            );
        }

        $batch['ccp_summary'] = ccp_build_summary(
            $latestLogs,
            $batch['pasteurization_temp'] ?? null,
            $batch['cooling_temp'] ?? null
        );
        $batch['ccp_standards'] = $thresholds;
        $enriched[] = $batch;
    }

    return $enriched;
}

/**
 * Validate that a batch may be released by QC (required CCPs present and not failed).
 *
 * @return array{ok: bool, errors: array, summary: array}
 */
function ccp_validate_for_release(PDO $db, array $batch) {
    $runId = $batch['run_id'] ?? null;
    $latestLogs = ccp_get_latest_logs_for_run($db, $runId);
    $summary = ccp_build_summary(
        $latestLogs,
        $batch['pasteurization_temp'] ?? null,
        $batch['cooling_temp'] ?? null
    );

    $errors = [];

    if (!$runId && empty($latestLogs)) {
        // Legacy batch with no production run link
        if ($summary['pasteurization_temp'] === null || $summary['cooling_temp'] === null) {
            $errors['ccp_logs'] = 'No production CCP temperature data is linked to this batch. Cannot release without pasteurization and cooling verification.';
        } elseif (!$summary['can_release']) {
            $errors['ccp_logs'] = $summary['message'];
        }
    } else {
        if (!empty($summary['missing'])) {
            $errors['ccp_logs'] = 'Required production CCP logs missing: ' . implode(', ', $summary['missing'])
                . '. Production must log pasteurization (75°C / 15s) and cooling (≤4°C) before QC release.';
        }
        if (!empty($summary['failed'])) {
            $errors['ccp_failed'] = 'Required CCP check(s) failed: ' . implode(', ', $summary['failed'])
                . '. Batch cannot be released until production re-logs passing readings.';
        }
    }

    return [
        'ok' => empty($errors),
        'errors' => $errors,
        'summary' => $summary,
    ];
}

} // end function_exists guard
