const { test } = require('node:test');
const assert = require('node:assert/strict');
const Monitor = require('./monitor');
function setup() {
  let now = Date.parse('2026-09-27T03:00:00Z');
  let failures = 0, restarts = 0, report;
  const manager = { enabled: true, busy: false, client: { getState: async () => 'CONNECTED' },
    snapshot: () => ({ status: 'connected' }), fail: () => { failures++; } };
  const monitor = new Monitor({ manager, now: () => now, timeoutMs: 5,
    save: async r => { report = r; }, restart: () => { restarts++; } });
  return { monitor, manager, advance: ms => { now += ms; }, counts: () => ({ failures, restarts }), saved: () => report };
}
test('probes actual connection and persists one daily record across restart', async () => {
  const ctx = setup();
  await ctx.monitor.check();
  assert.equal(ctx.saved().result, 'connected');
  assert.equal(ctx.saved().dailyChecks.length, 1);
  ctx.advance(60000); await ctx.monitor.check();
  assert.equal(ctx.saved().dailyChecks.length, 1);
  const reboot = setup();
  reboot.monitor.restore(ctx.saved());
  reboot.advance(86400000); await reboot.monitor.check();
  assert.equal(reboot.saved().dailyChecks.length, 2);
  assert.equal(reboot.saved().nextDailyCheckAt, '2026-09-29T03:00:00.000Z');
});
test('silent connection loss triggers recovery and prolonged failure restarts process', async () => {
  const ctx = setup();
  ctx.manager.client.getState = async () => 'TIMEOUT';
  await ctx.monitor.check();
  assert.equal(ctx.saved().result, 'reconnecting');
  assert.equal(ctx.counts().failures, 1);
  ctx.advance(300000); await ctx.monitor.check();
  assert.equal(ctx.counts().restarts, 1);
});
test('hung probe times out and overlapping checks are skipped', async () => {
  const ctx = setup();
  ctx.manager.client.getState = () => new Promise(() => {});
  await Promise.all([ctx.monitor.check(), ctx.monitor.check()]);
  assert.equal(ctx.counts().failures, 1);
  assert.equal(ctx.saved().result, 'reconnecting');
});
test('intentional logout and QR wait do not trigger restart', async () => {
  const ctx = setup();
  ctx.manager.enabled = false;
  await ctx.monitor.check();
  assert.equal(ctx.saved().result, 'disabled');
  ctx.manager.enabled = true;
  ctx.manager.snapshot = () => ({ status: 'qr' });
  ctx.advance(86400000); await ctx.monitor.check();
  assert.equal(ctx.saved().result, 'needs_qr');
  assert.deepEqual(ctx.counts(), { failures: 0, restarts: 0 });
});
test('logout during in-flight probe is not undone', async () => {
  const ctx = setup();
  ctx.manager.client.getState = async () => { ctx.manager.enabled = false; return 'TIMEOUT'; };
  await ctx.monitor.check();
  assert.equal(ctx.counts().failures, 0);
});
test('retains at most 30 daily records', async () => {
  const ctx = setup();
  for (let i = 0; i < 35; i++) { await ctx.monitor.check(); ctx.advance(86400000); }
  assert.equal(ctx.saved().dailyChecks.length, 30);
});
