(function (window) {
  const SERVICE_UUID = "000018f0-0000-1000-8000-00805f9b34fb";
  const CHARACTERISTIC_UUID = "00002af1-0000-1000-8000-00805f9b34fb";
  const CHUNK_SIZE = 80;
  const WRITE_DELAY_MS = 120;
  const JOB_DELAY_MS = 800;
  const SETTINGS_KEY = "dejati_bluetooth_printer_settings_v2";
  const LEGACY_SETTINGS_KEY = "dejati_bluetooth_printer_settings_v1";

  const roles = {
    cashier: { label: "Cashier", legacyKey: "bt_cashier_printer_id", order: 0 },
    kitchen: { label: "Kitchen", legacyKey: "bt_kitchen_printer_id", order: 1 }
  };

  const state = {
    cashier: { device: null, characteristic: null, statusSelectors: [] },
    kitchen: { device: null, characteristic: null, statusSelectors: [] }
  };

  function supported() {
    return !!(navigator.bluetooth && navigator.bluetooth.requestDevice);
  }

  function canRestorePermission() {
    return !!(navigator.bluetooth && navigator.bluetooth.getDevices);
  }

  function readJson(key) {
    try {
      return JSON.parse(localStorage.getItem(key) || "{}");
    } catch (error) {
      return {};
    }
  }

  function readSettings() {
    const settings = readJson(SETTINGS_KEY);
    const legacySettings = readJson(LEGACY_SETTINGS_KEY);

    Object.keys(roles).forEach(role => {
      if (!settings[role] && legacySettings[role]) settings[role] = legacySettings[role];
      if (!settings[role]) {
        const legacyId = localStorage.getItem(roles[role].legacyKey);
        if (legacyId) settings[role] = { id: legacyId, name: roles[role].label };
      }
      if (settings[role] && !settings[role].macAddress) {
        settings[role].macAddress = extractMacAddress(settings[role].name);
      }
    });

    return settings;
  }

  function writeSettings(settings) {
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
  }

  function extractMacAddress(name) {
    const match = String(name || "").match(/([0-9A-F]{2}(?::[0-9A-F]{2}){5})/i);
    return match ? match[1].toUpperCase() : "";
  }

  function sameSavedDevice(device, saved) {
    if (!device || !saved) return false;
    const deviceName = device.name || "";
    const savedName = saved.name || "";
    const savedMac = (saved.macAddress || extractMacAddress(savedName)).toUpperCase();
    const deviceMac = extractMacAddress(deviceName);

    return (saved.id && device.id === saved.id)
      || (savedMac && deviceMac && savedMac === deviceMac)
      || (savedName && deviceName && savedName === deviceName);
  }

  function getSaved(role) {
    const settings = readSettings();
    return settings[role] && (settings[role].id || settings[role].macAddress) ? settings[role] : null;
  }

  function saveRole(role, device) {
    const settings = readSettings();
    settings[role] = {
      id: device.id,
      name: device.name || roles[role].label,
      macAddress: extractMacAddress(device.name),
      savedAt: new Date().toISOString()
    };
    writeSettings(settings);
    localStorage.setItem(roles[role].legacyKey, device.id);
  }

  function status(role, message, connected) {
    const selectors = state[role].statusSelectors;
    if (!selectors.length || !window.jQuery) return;

    window.jQuery(selectors.join(","))
      .text(`${roles[role].label}: ${message}`)
      .toggleClass("text-success", !!connected)
      .toggleClass("text-muted", !connected);
  }

  function bindStatus(selectors) {
    Object.keys(selectors || {}).forEach(role => {
      if (!state[role]) return;

      const roleSelectors = Array.isArray(selectors[role]) ? selectors[role] : [selectors[role]];
      roleSelectors.filter(Boolean).forEach(selector => {
        if (!state[role].statusSelectors.includes(selector)) {
          state[role].statusSelectors.push(selector);
        }
      });
    });
    refreshStatus();
  }

  function rppDevices(devices) {
    return devices.filter(device => (device.name || "").toUpperCase().indexOf("RPP") === 0);
  }

  async function getPermittedDevices() {
    if (!canRestorePermission()) return [];
    return navigator.bluetooth.getDevices();
  }

  async function repairSettingsFromPermissions() {
    if (!canRestorePermission()) return readSettings();

    const devices = rppDevices(await getPermittedDevices());
    if (!devices.length) return readSettings();

    const settings = readSettings();
    const usedIds = new Set(Object.values(settings).filter(Boolean).map(item => item.id));

    Object.keys(roles).forEach(role => {
      if (settings[role]) {
        const matched = devices.find(device => sameSavedDevice(device, settings[role]));
        if (matched) {
          settings[role] = {
            ...settings[role],
            id: matched.id,
            name: matched.name || settings[role].name || roles[role].label,
            macAddress: extractMacAddress(matched.name) || settings[role].macAddress || "",
            repaired: settings[role].repaired || false
          };
          usedIds.add(matched.id);
          return;
        }
      }

      const preferred = devices[roles[role].order] || devices.find(device => !usedIds.has(device.id));
      if (preferred) {
        settings[role] = {
          id: preferred.id,
          name: preferred.name || roles[role].label,
          macAddress: extractMacAddress(preferred.name),
          savedAt: new Date().toISOString(),
          repaired: true
        };
        usedIds.add(preferred.id);
      }
    });

    writeSettings(settings);
    return settings;
  }

  async function refreshStatus() {
    const settings = await repairSettingsFromPermissions().catch(() => readSettings());

    Object.keys(roles).forEach(role => {
      const saved = settings[role];
      if (state[role].device && state[role].device.gatt && state[role].device.gatt.connected) {
        status(role, state[role].device.name || "connected", true);
      } else if (saved && saved.id) {
        status(role, `saved (${saved.name || "ready"})`, false);
      } else if (!canRestorePermission()) {
        status(role, "browser cannot restore saved devices", false);
      } else {
        status(role, "not configured", false);
      }
    });
  }

  async function findSavedDevice(role) {
    const settings = await repairSettingsFromPermissions();
    const saved = settings[role];
    if (!saved || !saved.id) return null;

    if (!canRestorePermission()) {
      throw new Error("This browser cannot restore saved Bluetooth devices. Use Chrome/Edge Android with Web Bluetooth getDevices support.");
    }

    const devices = await getPermittedDevices();
    return devices.find(device => sameSavedDevice(device, saved)) || null;
  }

  async function setup(role) {
    if (!supported()) {
      throw new Error("Web Bluetooth requires Chrome/Edge on Android over HTTPS.");
    }

    if (!roles[role]) throw new Error(`Unknown printer role: ${role}`);

    status(role, "select printer", false);
    const device = await navigator.bluetooth.requestDevice({
      filters: [{ namePrefix: "RPP" }],
      optionalServices: [SERVICE_UUID]
    });

    state[role].device = device;
    state[role].characteristic = null;
    saveRole(role, device);
    await connect(role);
    await refreshStatus();
    return state[role];
  }

  async function setupCashierAndKitchen() {
    await setup("cashier");
    await setup("kitchen");
  }

  async function connect(role) {
    if (!supported()) {
      throw new Error("Web Bluetooth requires Chrome/Edge on Android over HTTPS.");
    }

    if (!roles[role]) throw new Error(`Unknown printer role: ${role}`);

    let device = state[role].device;
    if (!device) device = await findSavedDevice(role);

    if (!device) {
      throw new Error(`${roles[role].label} printer is not saved. Use Connect ${roles[role].label} Printer once, then print again.`);
    }

    state[role].device = device;
    const disconnectBoundKey = `__dejatiDisconnectBound_${role}`;
    if (!device[disconnectBoundKey]) {
      device.addEventListener("gattserverdisconnected", function () {
        state[role].characteristic = null;
        refreshStatus();
      });
      device[disconnectBoundKey] = true;
    }

    if (state[role].characteristic && device.gatt.connected) {
      status(role, device.name || "connected", true);
      return state[role];
    }

    status(role, "connecting", false);
    if (!device.gatt.connected) await device.gatt.connect();

    const service = await device.gatt.getPrimaryService(SERVICE_UUID);
    state[role].characteristic = await service.getCharacteristic(CHARACTERISTIC_UUID);
    status(role, device.name || "connected", true);
    return state[role];
  }

  async function write(role, escpos) {
    const printer = await connect(role);
    const bytes = new TextEncoder().encode(escpos);

    for (let offset = 0; offset < bytes.length; offset += CHUNK_SIZE) {
      const chunk = bytes.slice(offset, offset + CHUNK_SIZE);
      if (printer.characteristic.writeValueWithoutResponse) {
        await printer.characteristic.writeValueWithoutResponse(chunk);
      } else {
        await printer.characteristic.writeValue(chunk);
      }
      await new Promise(resolve => setTimeout(resolve, WRITE_DELAY_MS));
    }
  }

  async function writeSequential(jobs) {
    for (const job of jobs) {
      await write(job.role, job.escpos);
      await new Promise(resolve => setTimeout(resolve, JOB_DELAY_MS));
    }
  }

  function forget(role) {
    const settings = readSettings();
    if (role) {
      delete settings[role];
      localStorage.removeItem(roles[role].legacyKey);
      state[role].device = null;
      state[role].characteristic = null;
    } else {
      Object.keys(roles).forEach(item => {
        localStorage.removeItem(roles[item].legacyKey);
        state[item].device = null;
        state[item].characteristic = null;
      });
      localStorage.removeItem(SETTINGS_KEY);
      localStorage.removeItem(LEGACY_SETTINGS_KEY);
      refreshStatus();
      return;
    }
    writeSettings(settings);
    refreshStatus();
  }

  window.DejatiBluetoothPrinter = {
    supported,
    canRestorePermission,
    bindStatus,
    refreshStatus,
    setup,
    setupCashierAndKitchen,
    connect,
    write,
    writeSequential,
    getSaved,
    getPermittedDevices,
    repairSettingsFromPermissions,
    forget
  };
})(window);
