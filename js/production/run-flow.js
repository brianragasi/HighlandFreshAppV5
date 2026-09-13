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

    function needsPasteurization(run) {
        return String(run?.milk_source_type || 'raw').toLowerCase() !== 'pasteurized';
    }

    function stageOrderFor(run) {
        return needsPasteurization(run)
            ? STAGE_ORDER
            : ['planned', 'in_progress', 'processing', 'cooling', 'packaging', 'completed'];
    }

    function ccpStatus(run) {
        const byType = latestCcpByType(run.ccp_logs);
        const past = byType.pasteurization;
        const cool = byType.cooling;
        const needsPast = needsPasteurization(run);
        const hasPast = !!past;
        const hasCool = !!cool;
        const pastFail = past && past.status === 'fail';
        const coolFail = cool && cool.status === 'fail';
        const sourceHasProof = !needsPast && (
            hasPast || (run.source_pasteurization_temp !== null && run.source_pasteurization_temp !== undefined)
        );
        const pastOk = needsPast ? (hasPast && !pastFail) : (sourceHasProof && !pastFail);
        const coolOk = hasCool && !coolFail;
        return {
            byType,
            needsPasteurization: needsPast,
            hasPast,
            hasCool,
            pastFail,
            coolFail,
            pastOk,
            coolOk,
            requiredMet: pastOk && coolOk,
            message: !pastOk
                ? (needsPast ? 'Record the pasteurization temperature' : 'The source pasteurization record needs attention')
                : pastFail
                    ? 'Pasteurization check failed — record a correct reading'
                    : !hasCool
                        ? (needsPast ? 'Record the cooling temperature' : 'Milk was already pasteurized; record the cooling temperature')
                        : coolFail
                            ? 'Cooling check failed — record a correct reading'
                            : (needsPast ? 'Required temperature checks are complete' : 'Source pasteurization and cooling checks are complete'),
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
                    key: 'released',
                    title: 'QC released — ready for warehouse',
                    detail: 'Batch released by QC. Warehouse FG can now receive it into chillers.',
                    href: workbench,
                    cta: 'View Run',
                    tone: 'success',
                    icon: 'fa-check-circle',
                    ccp,
                };
            }
            if (qc === 'pending' || qc === 'on_hold' || !qc) {
                return {
                    key: 'await_qc',
                    title: 'Waiting for QC verification',
                    detail: 'Production is done and packed. QC must verify counts, packaging, and safety before release.',
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

        // Stage advancement suggestions
        if (status === 'in_progress') {
            return {
                key: 'advance_process',
                title: ccp.needsPasteurization ? 'Begin pasteurization' : 'Begin recipe processing',
                detail: ccp.needsPasteurization
                    ? 'The milk arrived raw. Move to pasteurization first.'
                    : 'The milk arrived already pasteurized. Continue with the recipe; do not pasteurize it again.',
                href: workbench + '&panel=stages',
                cta: ccp.needsPasteurization ? 'Go to Pasteurization' : 'Go to Processing',
                tone: 'primary',
                icon: 'fa-forward',
                ccp,
            };
        }

        if (status === 'pasteurization') {
            if (!ccp.pastOk) {
                return {
                    key: 'ccp',
                    title: ccp.pastFail ? 'Correct the pasteurization check' : 'Record pasteurization temperature',
                    detail: 'After heating the milk, record 75°C for at least 15 seconds before processing.',
                    href: workbench + '&panel=ccp',
                    cta: 'Record temperature',
                    tone: 'error',
                    icon: 'fa-thermometer-half',
                    ccp,
                };
            }
            return {
                key: 'advance_process',
                title: 'Continue with the recipe',
                detail: 'Pasteurization is recorded. Move to recipe processing.',
                href: workbench + '&panel=stages',
                cta: 'Go to Processing',
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
            if (!ccp.coolOk) {
                return {
                    key: 'ccp',
                    title: ccp.coolFail ? 'Correct the cooling check' : 'Record cooling temperature',
                    detail: 'After cooling the product, record 4°C or lower before packing.',
                    href: workbench + '&panel=ccp',
                    cta: 'Record temperature',
                    tone: 'error',
                    icon: 'fa-snowflake',
                    ccp,
                };
            }
            return {
                key: 'advance_pack',
                title: 'Ready for packaging stage',
                detail: 'Cooling is recorded. Advance to packaging, then finish the run.',
                href: workbench + '&panel=stages',
                cta: 'Go to Packaging Stage',
                tone: 'info',
                icon: 'fa-box',
                ccp,
            };
        }

        if (status === 'packaging') {
            if (!ccp.requiredMet) {
                return {
                    key: 'ccp',
                    title: 'Temperature record needs attention',
                    detail: ccp.message,
                    href: workbench + '&panel=ccp',
                    cta: 'Review temperature',
                    tone: 'error',
                    icon: 'fa-thermometer-half',
                    ccp,
                };
            }
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
                detail: 'Material balance OK — record packaging output and finish. QC will then verify your counts.',
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

    function stageIndex(status, run = null) {
        const i = stageOrderFor(run).indexOf(status);
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
            id: 'process',
            panel: 'stages',
            number: 2,
            title: 'Make the product',
            short: 'Process',
            icon: 'fa-stream',
            description: 'Follow one step at a time. Record the hot and cold temperatures only when you reach those steps.',
        },
        {
            id: 'yield',
            panel: 'yield',
            number: 3,
            title: 'Review yield',
            short: 'Yield',
            icon: 'fa-chart-line',
            description: 'Confirm net yield and packaging estimate before finishing.',
        },
        {
            id: 'complete',
            panel: 'reconcile',
            number: 4,
            title: 'Complete run',
            short: 'Finish',
            icon: 'fa-check-double',
            description: 'Record what you packed (bottle sizes + counts), reconcile materials, and complete. QC will verify your counts before releasing.',
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
     * Raw milk: pasteurization → processing → cooling → packaging.
     * Pasteurized milk: processing → cooling → packaging.
     */
    function getNextStage(status, run = null) {
        const map = {
            planned: needsPasteurization(run) ? 'pasteurization' : 'processing',
            in_progress: needsPasteurization(run) ? 'pasteurization' : 'processing',
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
        if (!run) {
            return { allowed: [], next: null, reason: 'No run selected.' };
        }
        if (!volumeDone(run)) {
            return { allowed: [], next: null, reason: 'Set volume and start the run first.' };
        }

        const ccp = ccpStatus(run);
        const next = getNextStage(run.status, run);

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
        if (run.status === 'pasteurization' && !ccp.pastOk) {
            return {
                allowed: [],
                next,
                reason: ccp.pastFail
                    ? 'The pasteurization reading failed. Record a correct reading before processing.'
                    : 'Record the pasteurization temperature before processing.',
                blockForCcp: true,
            };
        }
        if (run.status === 'cooling' && !ccp.coolOk) {
            return {
                allowed: [],
                next,
                reason: ccp.coolFail
                    ? 'The cooling reading failed. Record a correct reading before packaging.'
                    : 'Record the cooling temperature before packaging.',
                blockForCcp: true,
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
        needsPasteurization,
        stageOrderFor,
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
