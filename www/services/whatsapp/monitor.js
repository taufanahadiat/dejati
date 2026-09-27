const DAY = 24 * 60 * 60 * 1000;
class ConnectionMonitor {
  constructor({ manager, save, now = Date.now, timeoutMs = 15000, restart = () => {} }) {
    Object.assign(this, { manager, save, now, timeoutMs, restart });
    this.report = { checkedAt: null, result: 'pending', dailyChecks: [], nextDailyCheckAt: null };
    this.running = false;
    this.stalledSince = null;
  }
  restore(report) {
    if (report && Array.isArray(report.dailyChecks)) this.report = report;
  }
  snapshot() { return { ...this.report }; }
  async check() {
    if (this.running) return;
    this.running = true;
    const now = this.now();
    let result = 'disabled';
    try {
      const manager = this.manager;
      if (manager.enabled) {
        const client = manager.client;
        const status = manager.snapshot().status;
        if (status === 'connected' && client && !manager.busy) {
          let timer;
          try {
            const state = await Promise.race([
              client.getState(),
              new Promise((_, reject) => { timer = setTimeout(() => reject(new Error('timeout')), this.timeoutMs); })
            ]);
            result = state === 'CONNECTED' ? 'connected' : 'reconnecting';
          } catch { result = 'reconnecting'; }
          finally { clearTimeout(timer); }
          // A logout/replacement during the probe must not restart an intentionally stopped session.
          if (manager.client !== client || !manager.enabled) result = 'changed';
          else if (result === 'reconnecting') manager.fail();
        } else if (status === 'qr') {
          result = 'needs_qr';
        } else {
          result = 'reconnecting';
          if (!manager.busy && !['starting', 'authenticated'].includes(status)) manager.fail();
        }
        if (result === 'reconnecting') {
          if (this.stalledSince === null) this.stalledSince = now;
          if (now - this.stalledSince >= 5 * 60 * 1000) this.restart();
        } else this.stalledSince = null;
      } else this.stalledSince = null;
      const checkedAt = new Date(now).toISOString();
      const dailyChecks = [...this.report.dailyChecks];
      const last = dailyChecks.at(-1);
      if (!last || now - Date.parse(last.checkedAt) >= DAY) {
        dailyChecks.push({ checkedAt, result });
      }
      const latest = dailyChecks.at(-1);
      this.report = {
        checkedAt, result, dailyChecks: dailyChecks.slice(-30),
        nextDailyCheckAt: new Date(Date.parse(latest.checkedAt) + DAY).toISOString()
      };
      await this.save(this.report);
    } finally { this.running = false; }
  }
}
module.exports = ConnectionMonitor;
