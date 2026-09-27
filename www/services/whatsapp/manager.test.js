const { test } = require('node:test');
const assert = require('node:assert/strict');
const { EventEmitter } = require('node:events');
const Manager = require('./manager');
function setup(encodeQr = async q => `image:${q}`) {
  const clients = [], saved = [];
  const manager = new Manager({ createClient: () => {
    const client = new EventEmitter();
    client.initialize = async () => {};
    client.destroy = async () => { client.destroyed = true; };
    client.logout = async () => { client.loggedOut = true; client.emit('disconnected'); };
    client.info = { pushname: 'Test', wid: { user: '628123' } };
    clients.push(client);
    return client;
  }, encodeQr, setEnabled: async value => saved.push(value), retryMs: 10 });
  return { manager, clients, saved };
}
const tick = () => new Promise(resolve => setImmediate(resolve));
test('pairing, account display, logout and no duplicate clients', async () => {
  const { manager, clients, saved } = setup();
  await Promise.all([manager.connect(), manager.connect()]);
  assert.equal(clients.length, 1);
  clients[0].emit('qr', 'first'); await tick();
  assert.equal(manager.snapshot().qr, 'image:first');
  clients[0].emit('authenticated');
  assert.equal(manager.snapshot().qr, null);
  clients[0].emit('ready');
  assert.equal(manager.snapshot().account.number, '628123');
  await manager.disconnect();
  assert.equal(manager.snapshot().status, 'disconnected');
  assert.equal(clients[0].loggedOut, true);
  assert.deepEqual(saved, [true, false]);
  assert.equal(manager.timer, null);
});
test('late QR encoding cannot overwrite newer QR or authenticated status', async () => {
  const pending = [];
  const { manager, clients } = setup(q => new Promise(resolve => pending.push(() => resolve(q))));
  await manager.connect();
  clients[0].emit('qr', 'old'); clients[0].emit('qr', 'new');
  pending[1](); await tick(); pending[0](); await tick();
  assert.equal(manager.snapshot().qr, 'new');
  clients[0].emit('qr', 'late'); clients[0].emit('authenticated');
  pending[2](); await tick();
  assert.equal(manager.snapshot().status, 'authenticated');
  await manager.disconnect();
});
test('unexpected disconnect reconnects and ignores old client events', async () => {
  const { manager, clients } = setup();
  await manager.connect();
  clients[0].emit('ready'); clients[0].emit('disconnected');
  assert.equal(manager.snapshot().account, null);
  await new Promise(resolve => setTimeout(resolve, 30));
  assert.equal(clients.length, 2);
  assert.equal(clients[0].destroyed, true);
  clients[0].emit('ready');
  assert.equal(manager.snapshot().status, 'starting');
  await manager.disconnect();
});
test('failed initialization is retryable and clears sensitive state', async () => {
  const { manager, clients } = setup();
  const original = manager.createClient;
  manager.createClient = () => {
    const client = original();
    client.initialize = async () => { throw new Error('offline'); };
    return client;
  };
  await manager.connect(); await tick();
  assert.equal(manager.snapshot().status, 'error');
  assert.equal(manager.snapshot().qr, null);
  await manager.disconnect();
  await new Promise(resolve => setTimeout(resolve, 30));
  assert.equal(clients.length, 1);
});
