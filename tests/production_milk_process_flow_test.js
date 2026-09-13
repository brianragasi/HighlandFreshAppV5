'use strict';

const assert = require('assert');
const fs = require('fs');
require('../js/production/run-flow.js');

const flow = global.ProductionRunFlow;

const rawStarted = {
    status: 'in_progress',
    milk_source_type: 'raw',
    initial_volume_ml: 10000,
    ccp_logs: [],
};
assert.strictEqual(flow.getNextStage(rawStarted.status, rawStarted), 'pasteurization');
assert.deepStrictEqual(flow.allowedStageTransitions(rawStarted, true).allowed, ['pasteurization']);

const pasteurizedStarted = {
    status: 'in_progress',
    milk_source_type: 'pasteurized',
    initial_volume_ml: 10000,
    source_pasteurization_temp: 75,
    ccp_logs: [{ check_type: 'pasteurization', status: 'pass', temperature: 75 }],
};
assert.strictEqual(flow.getNextStage(pasteurizedStarted.status, pasteurizedStarted), 'processing');
assert.deepStrictEqual(flow.allowedStageTransitions(pasteurizedStarted, true).allowed, ['processing']);

const rawHeating = { ...rawStarted, status: 'pasteurization' };
assert.strictEqual(flow.allowedStageTransitions(rawHeating, true).blockForCcp, true);
rawHeating.ccp_logs = [{ check_type: 'pasteurization', status: 'pass', temperature: 75 }];
assert.deepStrictEqual(flow.allowedStageTransitions(rawHeating, true).allowed, ['processing']);

const processing = { ...rawHeating, status: 'processing' };
assert.deepStrictEqual(flow.allowedStageTransitions(processing, true).allowed, ['cooling']);

const cooling = { ...processing, status: 'cooling' };
assert.strictEqual(flow.allowedStageTransitions(cooling, true).blockForCcp, true);
cooling.ccp_logs.push({ check_type: 'cooling', status: 'pass', temperature: 4 });
assert.deepStrictEqual(flow.allowedStageTransitions(cooling, true).allowed, ['packaging']);
assert.strictEqual(flow.ccpStatus(cooling).requiredMet, true);

const pasteurizedCooling = {
    ...pasteurizedStarted,
    status: 'cooling',
    ccp_logs: [
        ...pasteurizedStarted.ccp_logs,
        { check_type: 'cooling', status: 'pass', temperature: 4 },
    ],
};
assert.strictEqual(flow.ccpStatus(pasteurizedCooling).requiredMet, true);
assert.strictEqual(flow.WIZARD_STEPS.length, 4);
assert.strictEqual(flow.WIZARD_STEPS.some(step => step.id === 'ccp'), false);

const batchesPage = fs.readFileSync(require.resolve('../html/production/batches.html'), 'utf8');
const legacyCcpPage = fs.readFileSync(require.resolve('../html/production/ccp_logging.html'), 'utf8');
assert.strictEqual(batchesPage.includes('ccp_logging.html?run_id='), false);
assert.ok(batchesPage.includes('run-workbench.html?run_id=${run.id}&panel=ccp'));
assert.ok(legacyCcpPage.includes("window.location.replace(workbenchUrl.pathname + workbenchUrl.search)"));

console.log('Production milk process flow tests passed.');
