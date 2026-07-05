(function (window) {
  const SERVICE_UUID = "000018f0-0000-1000-8000-00805f9b34fb";
  const CHARACTERISTIC_UUID = "00002af1-0000-1000-8000-00805f9b34fb";
  const CHUNK_SIZE = 180;
  const SETTINGS_KEY = "dejati_bluetooth_printer_settings_v1";

  const roles = {
    cashier: { label: "Cashier", legacyKey: "bt_cashier_printer_id" },
    kitchen: { label: "Kitchen", legacyKey: "bt_kitchen_printer_id" }
  };

  const state = {
    cashier: { device: null, characteristic: null, statusSelector: null },
    kitchen: { device: null, characteristic: null, statusSelector: null }
  };

  function supported() {
    return !!(navigator.bluetooth && navigator.bluetooth.requestDevice);
  }

  function canRestorePermission() {
    return !!(navigator.bluetooth && navigator.bluetooth.getDevices);
  }

  function readSettings() {
    try {
      return JSON.parse(localStorage.getItem(SETTINGS_KEY) || "{}");
    } catch (error) {
      return {};
    }
  }

  function writeSettings(settings) {
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
  }

  function getSaved(role) {
    const settings = readSettings();
    if (settings[role] && settings[role].id) return settings[role];

    const legacyId = localStorage.getItem(roles[role].legacyKey);
    if (!legacyId) return null;

    settings[role] = { id: legacyId, name: roles[role].label };
    writeSettings(settings);
    return settings[role];
  }

  function saveRole(role, device) {
    const settings = readSettings();
    settings[role] = { id: device.id, name: device.name || roles[role].label };
    writeSettings(settings);
    localStorage.setItem(roles[role].legacyKey, device.id);
  }

  function status(role, message, connected) {
    const selector = state[role].statusSelector;
    if (!selector || !window.jQuery) return;

    window.jQuery(selector)
      .text(`${roles[role].label}: ${message}`)
      .toggleClass("text-success", !!connected)
      .toggleClass("text-muted", !connected);
  }

  function bindStatus(selectors) {
    Object.keys(selectors || {}).forEach(role => {
      if (state[role]) state[role].statusSelector = selectors[role];
    });
    refreshStatus();
  }

  function refreshStatus() {
    Object.keys(roles).forEach(role => {
      const saved = getSaved(role);
      if (state[role].device && state[role].device.gatt && state[role].device.gatt.connected) {
        status(role, state[role].device.name || "connected", true);
      } else if (saved) {
        status(role, `saved (${saved.name || "ready"})`, false);
      } else {
        status(role, "not configured", false);
      }
    });
  }

  async function findSavedDevice(role) {
    const saved = getSaved(role);
    if (!saved) return null;
    if (!canRestorePermission()) {
      throw new Error("This browser cannot reuse saved Bluetooth permissions. Use latest Chrome/Edge Android, or keep printing from the same page after connecting.");
    }

    const devices = await navigator.bluetooth.getDevices();
    return devices.find(device => device.id === saved.id) || null;
  }

  async function setup(role) {
    if (!supported()) {
      throw new Error("Web Bluetooth requires Chrome/Edge on Android over HTTPS.");
    }

    status(role, "select printer", false);
    const device = await navigator.bluetooth.requestDevice({
      filters: [{ namePrefix: "RPP" }, { services: [SERVICE_UUID] }],
      optionalServices: [SERVICE_UUID]
    });

    state[role].device = device;
    state[role].characteristic = null;
    saveRole(role, device);
    await connect(role);
    return state[role];
  }

  async function connect(role) {
    if (!supported()) {
      throw new Error("Web Bluetooth requires Chrome/Edge on Android over HTTPS.");
    }

    if (!roles[role]) throw new Error(`Unknown printer role: ${role}`);

    let device = state[role].device;
    if (!device) {
      device = await findSavedDevice(role);
    }

    if (!device) {
      throw new Error(`${roles[role].label} printer is not configured. Click Connect ${roles[role].label} Printer once in printer settings.`);
    }

    state[role].device = device;
    if (!device.__dejatiDisconnectBound) {
      device.addEventListener("gattserverdisconnected", function () {
        state[role].characteristic = null;
        refreshStatus();
      });
      device.__dejatiDisconnectBound = true;
    }

    if (state[role].characteristic && device.gatt.connected) {
      status(role, device.name || "connected", true);
      return state[role];
    }

    status(role, "connecting", false);
    if (!device.gatt.connected) {
      await device.gatt.connect();
    }

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
      await new Promise(resolve => setTimeout(resolve, 50));
    }
  }

  async function writeSequential(jobs) {
    for (const job of jobs) {
      await write(job.role, job.escpos);
      await new Promise(resolve => setTimeout(resolve, 500));
    }
  }

  window.DejatiBluetoothPrinter = {
    supported,
    canRestorePermission,
    bindStatus,
    refreshStatus,
    setup,
    connect,
    write,
    writeSequential,
    getSaved
  };
})(window);