class WhatsAppManager {
  constructor({ createClient, encodeQr, setEnabled, retryMs = 10000 }) {
    Object.assign(this, { createClient, encodeQr, setEnabled, retryMs });
    this.state = { status: 'disconnected', qr: null, account: null };
    this.client = null;
    this.busy = false;
    this.enabled = false;
    this.timer = null;
  }
  snapshot() { return { ...this.state }; }
  clear(status) { this.state = { status, qr: null, account: null }; }
  async connect() {
    if (this.busy || ['starting', 'qr', 'authenticated', 'connected'].includes(this.state.status)) return;
    this.busy = true;
    try {
      await this.setEnabled(true);
      this.enabled = true;
      clearTimeout(this.timer);
      await this.dispose();
      this.clear('starting');
      const client = this.client = this.createClient();
      let qrVersion = 0;
      client.on('qr', async qr => {
        const version = ++qrVersion;
        try {
          const image = await this.encodeQr(qr);
          if (this.client === client && version === qrVersion) this.state = { status: 'qr', qr: image, account: null };
        } catch { if (this.client === client) this.fail(); }
      });
      client.on('authenticated', () => {
        ++qrVersion;
        if (this.client === client) this.clear('authenticated');
      });
      client.on('ready', () => {
        ++qrVersion;
        if (this.client === client) this.state = { status: 'connected', qr: null, account: {
          name: client.info?.pushname || '', number: client.info?.wid?.user || ''
        } };
      });
      client.on('auth_failure', () => { ++qrVersion; if (this.client === client) this.fail(); });
      client.on('disconnected', () => { ++qrVersion; if (this.client === client) this.fail(); });
      // Initialization waits for the user's QR scan; do not block HTTP requests.
      client.initialize().catch(() => { if (this.client === client) this.fail(); });
    } catch { this.fail(); }
    finally { this.busy = false; }
  }
  fail() {
    this.clear('error');
    clearTimeout(this.timer);
    if (this.enabled) this.timer = setTimeout(() => this.connect(), this.retryMs);
  }
  async dispose() {
    const client = this.client;
    this.client = null;
    if (client) await client.destroy().catch(() => {});
  }
  async disconnect() {
    if (this.busy) throw new Error('busy');
    this.busy = true;
    this.enabled = false;
    clearTimeout(this.timer);
    try {
      await this.setEnabled(false);
      const client = this.client;
      this.client = null;
      this.clear('disconnecting');
      try { if (client) await client.logout(); }
      finally { if (client) await client.destroy().catch(() => {}); }
      this.clear('disconnected');
    } catch { this.clear('error'); throw new Error('disconnect_failed'); }
    finally { this.busy = false; }
  }
}
module.exports = WhatsAppManager;
