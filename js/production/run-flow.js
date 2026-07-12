/**
 * Highland Fresh — Production run flow helpers
 *
 * Single place that decides "what should the floor staff do next?"
 * Used by Dashboard next-step cards and the Active Run Workbench.
 */
(function (global) {
    'use strict';

    const STAGE_ORDER = ['planned', 'in_progress', 'pasteurization', 'processing', 'cooling', 'packaging', 'completed'];

    const STAGE_LABELS = {
        planned: 'Planned',
        in_progress: 'In Progress',
        pasteurization: 'Pasteurization',
        processing: 'Processing',
        cooling: 'Cooling',
        packaging: 'Packaging',
        completed: 'Completed',
        cancelled: 'Cancelled',
    };

    function latestCcpByType(ccpLogs) {
        const map = {};
        (ccpLogs || []).forEach((log) => {
            const t = log.check_type;
            if (!t) return;
            const prev = map[t];
            if (!prev || String(log.check_datetime || '') >= String(prev.check_datetime || '')) {
                map[t] = log;
            }
        });
        return map;
    }

    function ccpStatus(run) {
        const byType = latestCcpByType(run.ccp_logs);
        const past = byType.pasteurization;
        const cool = byType.cooling;
        const hasPast = !!past;
        const hasCool = !!cool;
        const pastFail = past && past.status === 'fail';
        const coolFail = cool && cool.status === 'fail';
        const pastOk = hasPast && !pastFail;
        const coolOk = hasCool && !coolFail;
        return {
            byType,
            hasPast,
            hasCool,
            pastFail,
            coolFail,
            pastOk,
            coolOk,
            requiredMet: pastOk && coolOk,
            message: !hasPast
                ? 'Pasteurization CCP not logged'
                : pastFail
                    ? 'Pasteurization CCP failed — re-log'
                    : !hasCool
                        ? 'Cooling CCP not logged'
                        : coolFail
                            ? 'Cooling CCP failed — re-log'
                            : 'Required CCP checks OK',
        };
    }

    /**
     * Compute the next recommended action for a production run.
     * @param {object} run - production run (ideally with ccp_logs)
     * @param {object} [extras] - optional { yieldData, estimates }
     */
    function getNextStep(run, extras = {}) {
        if (!run) {
            return {
                key: 'none',
                title: 'No run selected',
                detail: 'Pick an active production run to continue.',
                href: 'run-workbench.html',
                cta: 'Open My Runs',
                tone: 'ghost',
                icon: 'fa-inbox',
            };
        }

        const status = run.status || 'planned';
        const ccp = ccpStatus(run);
        const volumeSet = run.initial_volume_ml != null && Number(run.initial_volume_ml) > 0;
        const yieldData = extras.yieldData || null;
        const workbench = `run-workbench.html?run_id=${run.id}`;

        if (status === 'cancelled') {
            return {
                key: 'cancelled',
                title: 'Run cancelled',
                detail: 'This run is closed and cannot continue.',
                href: workbench,
                cta: 'View Run',
                tone: 'ghost',
                icon: 'fa-ban',
                ccp,
            };
        }

        if (status === 'completed') {
            const qc = run.qc_status || run.batch_qc_status;
            if (qc === 'released') {
                return {
                    key: 'package',
                    title: 'Package finished goods',
                    detail: 'Run complete and QC released — record packaging.',
                    href: `packaging.html?run_id=${run.id}`,
                    cta: 'Open Packaging',
                    tone: 'success',
                    icon: 'fa-boxes',
                    ccp,
                };
            }
            if (qc === 'pending' || qc === 'on_hold' || !qc) {
                return {
                    key: 'await_qc',
                    title: 'Waiting for QC release',
                    detail: 'Production is done. QC must release the batch before packaging.',
                    href: workbench,
                    cta: 'View Status',
                    tone: 'warning',
                    icon: 'fa-clipboard-check',
                    ccp,
                };
            }
            return {
                key: 'completed',
                title: 'Run completed',
                detail: 'No further production steps on this run.',
                href: workbench,
                cta: 'View Run',
                tone: 'success',
                icon: 'fa-check-circle',
                ccp,
            };
        }

        if (status === 'planned') {
            return {
                key: 'start',
                title: 'Start this run',
                detail: volumeSet
                    ? 'Confirm volume and start production.'
                    : 'Set initial milk volume, then start the run.',
                href: workbench + '&panel=overview',
                cta: 'Start Run',
                tone: 'primary',
                icon: 'fa-play',
                ccp,
            };
        }

        // Active statuses
        if (!volumeSet) {
            return {
                key: 'set_volume',
                title: 'Set initial volume',
                detail: 'Enter starting milk volume (mL) so losses and packaging estimates work.',
                href: workbench + '&panel=overview',
                cta: 'Set Volume',
                tone: 'warning',
                icon: 'fa-flask',
                ccp,
            };
        }

        if (!ccp.requiredMet) {
            return {
                key: 'ccp',
                title: ccp.pastFail || ccp.coolFail ? 'Fix failed CCP' : 'Log required CCPs',
                detail: ccp.message + ' (pasteurization 75°C / cooling ≤4°C).',
                href: workbench + '&panel=ccp',
                cta: 'Log CCP',
                tone: 'error',
                icon: 'fa-thermometer-half',
                ccp,
            };
        }

        // Stage advancement suggestions
        if (status === 'in_progress' || status === 'pasteurization') {
            return {
                key: 'advance_process',
                title: 'Continue processing',
                detail: 'CCPs look good. Move the run forward or record any losses.',
                href: workbench + '&panel=stages',
                cta: 'Update Stage',
                tone: 'primary',
                icon: 'fa-forward',
                ccp,
            };
        }

        if (status === 'processing') {
            return {
                key: 'advance_cool',
                title: 'Cool the product',
                detail: 'Advance to cooling when process steps are done. Record losses if any.',
                href: workbench + '&panel=stages',
                cta: 'Go to Cooling',
                tone: 'info',
                icon: 'fa-snowflake',
                ccp,
            };
        }

        if (status === 'cooling') {
            return {
                key: 'advance_pack',
                title: 'Ready for packaging stage',
                detail: 'Advance to packaging, then complete & reconcile the run.',
                href: workbench + '&panel=stages',
                cta: 'Go to Packaging Stage',
                tone: 'info',
                icon: 'fa-box',
                ccp,
            };
        }

        if (status === 'packaging') {
            const reconciled = run.material_reconciled == 1 || run.material_reconciled === true;
            if (!reconciled) {
                return {
                    key: 'reconcile',
                    title: 'Reconcile materials',
                    detail: 'Review losses, yield, and unaccounted volume before completing.',
                    href: workbench + '&panel=reconcile',
                    cta: 'Reconcile & Complete',
                    tone: 'warning',
                    icon: 'fa-balance-scale',
                    ccp,
                    yieldData,
                };
            }
            return {
                key: 'complete',
                title: 'Complete the run',
                detail: 'Material balance OK — finish the run. QC must still release the batch before finished-goods packaging.',
                href: workbench + '&panel=reconcile',
                cta: 'Complete Run',
                tone: 'success',
                icon: 'fa-check-double',
                ccp,
            };
        }

        return {
            key: 'open',
            title: 'Open run workbench',
            detail: `Status: ${STAGE_LABELS[status] || status}`,
            href: workbench,
            cta: 'Open Workbench',
            tone: 'primary',
            icon: 'fa-screwdriver-wrench',
            ccp,
        };
    }

    function stageIndex(status) {
        const i = STAGE_ORDER.indexOf(status);
        return i < 0 ? 0 : i;
    }

    function toneClasses(tone) {
        const map = {
            primary: { badge: 'badge-primary', btn: 'btn-primary', alert: 'alert-info', border: 'border-primary/30' },
            success: { badge: 'badge-success', btn: 'btn-success', alert: 'alert-success', border: 'border-success/30' },
            warning: { badge: 'badge-warning', btn: 'btn-warning', alert: 'alert-warning', border: 'border-warning/30' },
            error: { badge: 'badge-error', btn: 'btn-error', alert: 'alert-error', border: 'border-error/30' },
            info: { badge: 'badge-info', btn: 'btn-info', alert: 'alert-info', border: 'border-info/30' },
            ghost: { badge: 'badge-ghost', btn: 'btn-ghost', alert: 'alert-info', border: 'border-base-300' },
        };
        return map[tone] || map.primary;
    }

    /**
     * Linear wizard steps for floor production.
     * Order is enforced when wizard mode is ON.
     */
    const WIZARD_STEPS = [
        {
            id: 'volume',
            panel: 'overview',
            number: 1,
            title: 'Volume & Start',
            short: 'Volume',
            icon: 'fa-flask',
            description: 'Confirm starting milk (usually pre-filled from requisition/recipe) and start the run.',
        },
        {
            id: 'ccp',
            panel: 'ccp',
            number: 2,
            title: 'CCP checks',
            short: 'CCP',
            icon: 'fa-thermometer-half',
            description: 'Log pasteurization (75°C/15s) and cooling (≤4°C). Both must pass.',
        },
        {
            id: 'process',
            panel: 'stages',
            number: 3,
            title: 'Process stages',
            short: 'Process',
            icon: 'fa-stream',
            description: 'Advance one floor stage at a time. Record any milk losses here so yield recalculates. (FG packaging still needs QC later.)',
        },
        {
            id: 'yield',
            panel: 'yield',
            number: 4,
            title: 'Review yield',
            short: 'Yield',
            icon: 'fa-chart-line',
            description: 'Confirm net yield and packaging estimate before finishing.',
        },
        {
            id: 'complete',
            panel: 'reconcile',
            number: 5,
            title: 'Complete run',
            short: 'Finish',
            icon: 'fa-check-double',
            description: 'Reconcile materials and complete the run. A QC officer must then release the batch before finished-goods packaging.',
        },
    ];

    function volumeDone(run) {
        const vol = Number(run.initial_volume_ml || 0);
        const started = run.status && run.status !== 'planned' && run.status !== 'cancelled';
        return vol > 0 && started;
    }

    function processDone(run) {
        // Ready to finish once at packaging (or already completed)
        return ['packaging', 'completed'].includes(run.status);
    }

    function yieldDone(run, extras = {}) {
        // Yield review is complete once volume is set and we have estimate or packaging stage
        const hasVol = Number(run.initial_volume_ml || 0) > 0;
        const est = extras.estimates;
        const hasEst = !!(est && (
            (est.initial_estimate && est.initial_estimate.items && est.initial_estimate.items.length)
            || (est.revised_estimate && est.revised_estimate.items && est.revised_estimate.items.length)
        ));
        return hasVol && (hasEst || processDone(run) || run.status === 'completed');
    }

    function completeDone(run) {
        return run.status === 'completed';
    }

    /**
     * Evaluate wizard lock/done state for a run.
     * @returns {{ steps: Array, currentId: string|null, currentIndex: number, allDone: boolean }}
     */
    function getWizardState(run, extras = {}) {
        if (!run) {
            return {
                steps: WIZARD_STEPS.map((s, i) => ({
                    ...s,
                    done: false,
                    locked: i > 0,
                    current: i === 0,
                    lockReason: i > 0 ? 'Select a run first' : null,
                })),
                currentId: 'volume',
                currentIndex: 0,
                allDone: false,
            };
        }

        if (run.status === 'cancelled') {
            return {
                steps: WIZARD_STEPS.map((s) => ({
                    ...s,
                    done: false,
                    locked: true,
                    current: false,
                    lockReason: 'Run is cancelled',
                })),
                currentId: null,
                currentIndex: -1,
                allDone: false,
            };
        }

        const ccp = ccpStatus(run);
        const flags = {
            volume: volumeDone(run),
            ccp: ccp.requiredMet,
            process: processDone(run),
            yield: yieldDone(run, extras),
            complete: completeDone(run),
        };

        // Sequential unlock: step N unlocked only if steps 0..N-1 are done
        // Exception: losses panel is allowed once volume is done (optional side path)
        const steps = WIZARD_STEPS.map((s, i) => {
            const prevDone = WIZARD_STEPS.slice(0, i).every((p) => flags[p.id]);
            const done = !!flags[s.id];
            let locked = !prevDone && !done;
            let lockReason = null;

            if (locked) {
                const firstMissing = WIZARD_STEPS.slice(0, i).find((p) => !flags[p.id]);
                if (firstMissing) {
                    lockReason = `Finish “${firstMissing.title}” first`;
                }
            }

            // Completed runs: all steps unlocked and marked done where applicable
            if (run.status === 'completed') {
                locked = false;
                lockReason = null;
            }

            return {
                ...s,
                done,
                locked,
                current: false,
                lockReason,
                ccp,
            };
        });

        // Current = first not-done unlocked step; if all done, last step
        let currentIndex = steps.findIndex((s) => !s.done && !s.locked);
        if (currentIndex < 0) {
            currentIndex = steps.every((s) => s.done) ? steps.length - 1 : steps.findIndex((s) => !s.locked);
        }
        if (currentIndex < 0) currentIndex = 0;
        steps.forEach((s, i) => {
            s.current = i === currentIndex && !completeDone(run);
            if (completeDone(run) && i === steps.length - 1) s.current = true;
        });

        return {
            steps,
            currentId: steps[currentIndex] ? steps[currentIndex].id : null,
            currentIndex,
            allDone: steps.every((s) => s.done),
            ccp,
            flags,
        };
    }

    /**
     * Can this panel be opened in wizard mode?
     * losses is allowed after volume (optional side step).
     */
    function canOpenPanel(panel, wizardState, wizardMode) {
        if (!wizardMode || !wizardState) return { ok: true };
        if (panel === 'losses') {
            const vol = wizardState.steps.find((s) => s.id === 'volume');
            if (vol && !vol.done) {
                return { ok: false, reason: 'Set volume and start the run before recording losses.' };
            }
            return { ok: true };
        }
        const step = wizardState.steps.find((s) => s.panel === panel);
        if (!step) return { ok: true };
        if (step.locked) {
            return { ok: false, reason: step.lockReason || 'Complete previous steps first.' };
        }
        return { ok: true };
    }

    /**
     * Strict floor path: only one legal "next" stage (no random jumping).
     * planned/in_progress → pasteurization → processing → cooling → packaging
     */
    function getNextStage(status) {
        const map = {
            planned: 'pasteurization',
            in_progress: 'pasteurization',
            pasteurization: 'processing',
            processing: 'cooling',
            cooling: 'packaging',
            packaging: null,
            completed: null,
            cancelled: null,
        };
        return map[status] || null;
    }

    /**
     * Which production stage transitions are allowed right now.
     * Wizard mode: only the next forward stage (and packaging needs CCP).
     */
    function allowedStageTransitions(run, wizardMode) {
        const all = ['pasteurization', 'processing', 'cooling', 'packaging'];
        if (!run) {
            return { allowed: [], next: null, reason: 'No run selected.' };
        }
        if (!volumeDone(run)) {
            return { allowed: [], next: null, reason: 'Set volume and start the run first.' };
        }

        const ccp = ccpStatus(run);
        const next = getNextStage(run.status);

        if (!wizardMode) {
            // Free mode: still require CCP before packaging
            if (!ccp.requiredMet) {
                return {
                    allowed: all.filter((s) => s !== 'packaging'),
                    next,
                    reason: 'Complete required CCP checks before floor packaging stage.',
                    blockPackaging: true,
                };
            }
            return { allowed: all, next, reason: null };
        }

        // Wizard: only one step forward
        if (!next) {
            return {
                allowed: [],
                next: null,
                reason: run.status === 'packaging'
                    ? 'Already at floor packaging stage — record any final losses, then go to Yield / Finish.'
                    : 'No further stage to advance.',
            };
        }
        if (next === 'packaging' && !ccp.requiredMet) {
            return {
                allowed: [],
                next,
                reason: 'Complete pasteurization + cooling CCP checks before moving to floor packaging stage.',
                blockPackaging: true,
            };
        }
        return {
            allowed: [next],
            next,
            reason: null,
        };
    }

    global.ProductionRunFlow = {
        STAGE_ORDER,
        STAGE_LABELS,
        WIZARD_STEPS,
        latestCcpByType,
        ccpStatus,
        getNextStep,
        stageIndex,
        toneClasses,
        getWizardState,
        canOpenPanel,
        allowedStageTransitions,
        getNextStage,
        volumeDone,
        processDone,
    };
})(typeof window !== 'undefined' ? window : globalThis);
