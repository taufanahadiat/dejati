const http = require('node:http');
const fs = require('node:fs/promises');
const { Client, LocalAuth } = require('whatsapp-web.js');
const QRCode = require('qrcode');
const WhatsAppManager = require('./manager');
const ConnectionMonitor = require('./monitor');
const { createStockBot } = require('./stock-bot');
const { createClosingNotifier } = require('./closing-notifier');
let closingNotifier, closingTimer;
let stockBot;
const enabledFile = '/data/enabled';
const manager = new WhatsAppManager({
  createClient: () => {
    const client = new Client({
    authStrategy: new LocalAuth({ dataPath: '/data/auth', rmMaxRetries: 10 }),
    deviceName: 'Dejati Server',
    webVersionCache: { type: 'none' },
    puppeteer: { executablePath: '/usr/bin/chromium', headless: true,
      args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'] }
    });
    client.on('message', message => {
      if (manager.client === client && stockBot) stockBot.handle(message, client);
    });
    return client;
  },
  encodeQr: qr => QRCode.toDataURL(qr, { width: 320, margin: 4 }),
  setEnabled: enabled => enabled ? fs.writeFile(enabledFile, '1', { mode: 0o600 }) : fs.rm(enabledFile, { force: true })
});
const monitor = new ConnectionMonitor({
  manager,
  save: async report => {
    await fs.writeFile('/data/monitor.json.tmp', JSON.stringify(report), { mode: 0o600 });
    await fs.rename('/data/monitor.json.tmp', '/data/monitor.json');
  },
  restart: () => {
    console.error('WhatsApp connection stalled for 5 minutes; restarting service with saved session.');
    process.exit(1);
  }
});
let monitorTimer;
const server = http.createServer(async (req, res) => {
  res.setHeader('Content-Type', 'application/json');
  res.setHeader('Cache-Control', 'no-store');
  try {
    if (req.method === 'GET' && req.url === '/health') return res.end('{"ok":true}');
    if (req.method === 'GET' && req.url === '/status') return res.end(JSON.stringify({ ...manager.snapshot(), monitoring: monitor.snapshot() }));
    if (req.method === 'POST' && req.url === '/check') {
      await monitor.check();
      return res.end(JSON.stringify(monitor.snapshot()));
    }
    if (req.method === 'POST' && req.url === '/connect') await manager.connect();
    else if (req.method === 'POST' && req.url === '/disconnect') await manager.disconnect();
    else { res.statusCode = 404; return res.end('{"error":"Not found"}'); }
    res.end(JSON.stringify({ ...manager.snapshot(), monitoring: monitor.snapshot() }));
  } catch { res.statusCode = 503; res.end('{"error":"Operasi WhatsApp gagal. Coba lagi."}'); }
});
server.listen(3000, '0.0.0.0', async () => {
  console.log('WhatsApp service listening on port 3000');
  stockBot = await createStockBot();
  closingNotifier = await createClosingNotifier(manager);
  closingTimer = setInterval(() => closingNotifier.tick(), 30000);
  try { monitor.restore(JSON.parse(await fs.readFile('/data/monitor.json', 'utf8'))); } catch {}
  monitorTimer = setInterval(() => monitor.check().catch(() => console.error('Cannot persist WhatsApp monitoring report.')), 60000);
  if (await fs.access(enabledFile).then(() => true, () => false)) {
    // This volume belongs to a single service. Container replacement can leave
    // Chromium locks referencing the old hostname/PID; credentials stay intact.
    for (const name of ['SingletonLock', 'SingletonSocket', 'SingletonCookie', 'DevToolsActivePort']) {
      await fs.rm('/data/auth/session/' + name, { force: true });
    }
    await manager.connect();
  }
});
async function shutdown() {
  clearInterval(closingTimer);
  clearInterval(monitorTimer);
  manager.enabled = false;
  clearTimeout(manager.timer);
  server.close();
  await manager.dispose();
  process.exit(0);
}
process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
