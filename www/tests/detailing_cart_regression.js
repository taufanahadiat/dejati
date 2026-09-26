// After the PHP read scenario, copy /tmp/detailing-pos-test.html from the container, then run:
// node tests/detailing_cart_regression.js /tmp/detailing-pos-test.html
const fs = require('fs'), vm = require('vm'), assert = require('assert');
const html = fs.readFileSync(process.argv[2] || '/tmp/detailing-pos-test.html', 'utf8');
const scripts = [...html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(m => m[1]);
const values = {}, data = {}, storage = {};
const events = {}, modalCalls = [];
function $(selector) {
  let proxy;
  const element = {
    val(value) { if (arguments.length) {values[selector] = value; return proxy;} return values[selector]; },
    data(key, value) {const k = selector + ':' + key; if(arguments.length === 2) {data[k]=value;return proxy;} return data[k];},
    removeData(key) { delete data[selector + ':' + key]; return proxy; },
    one(event, callback) { events[selector + ':' + event] = callback; return proxy; },
    modal(action) { modalCalls.push([selector, action]); return proxy; },
  };
  proxy = new Proxy(element, {get(target,key) {return target[key] || (() => proxy);}});
  return proxy;
}
const context = vm.createContext({$, document: {}, window: {}, console: {log(){}}, localStorage: {getItem:k => storage[k], setItem:(k,v) => storage[k]=v}, setTimeout(){}});
vm.runInContext(scripts.find(s => s.includes('const IMPORTED =')), context);
let imported = JSON.parse(storage.cart);
assert.equal(imported.length, 3);
let detail = imported.find(i => i.cartType === 'detailing');
assert.equal(detail.variantName, 'Large');
assert.equal(detail.finalPrice, 105000); assert.equal(detail.nopol, 'B 1234 TEST'); assert.equal(detail.id, '1');
vm.runInContext(scripts.find(s => s.includes('function showCarwashModal')), context);
vm.runInContext("showCarwashModal('1', 'Polish', 100000, 'detailing')", context);
values['input[name="vacuum"]:checked'] = 'yes';
values['#nopol'] = 'B 42'; values['#service'] = 'Polish'; values['#ukuran'] = 'Mobil Sedang';
vm.runInContext('addCarwashToCart(false)', context);
let cart = JSON.parse(storage.cart);
assert.equal(cart[3].cartType, 'detailing'); assert.equal(cart[3].finalPrice, 105000);
values['#buyQty']=3; values['#buyNotes']='Updated'; values['#orderTypeSwitch']='dine-in'; data['#buyQueryModal:edit-index']=3;
vm.runInContext('currentProduct = { id: cart[3].id, name: cart[3].name, price: cart[3].unitPrice }; saveBuyQuery()',context);
cart=JSON.parse(storage.cart);
assert.equal(cart[3].cartType,'detailing'); assert.equal(cart[3].qty,3); assert.equal(cart[3].nopol,'B 42');
vm.runInContext("showCarwashModal('1', 'Wash', 25000)",context);
values['input[name="vacuum"]:checked']='no';
vm.runInContext('addCarwashToCart(false)',context);
cart=JSON.parse(storage.cart); assert.equal(cart[4].cartType,'carwash');
console.log('PASS: imported detailing, service selection, vacuum total, cart edit preserves division and vehicle, carwash isolation');

for (const type of ['carwash', 'detailing']) {
  vm.runInContext(`showVariantModal('1', "Driver's Package", "Small;Large", "50000;100000", '${type}')`, context);
  assert.deepEqual(modalCalls.at(-1), ['#variantModal', 'show']);
  vm.runInContext(`addVariantToCart('1', "Driver's Package", 'Large', 100000, '${type}')`, context);
  assert.deepEqual(modalCalls.at(-1), ['#variantModal', 'hide']);
  events['#variantModal:hidden.bs.modal']();
  assert.deepEqual(modalCalls.at(-1), ['#carwashModal', 'show']);
  assert.equal(values['#carwash-type'], type); assert.equal(values['#carwash-variant'], 'Large');
  values['input[name="vacuum"]:checked'] = 'no';
  vm.runInContext('addCarwashToCart(false)', context);
  const item = JSON.parse(storage.cart).at(-1);
  assert.equal(item.variantName, 'Large'); assert.equal(item.cartType, type);
  assert.equal(item.finalPrice, 100000); assert.equal(item.name, "Driver's Package (Large)");
}
vm.runInContext("addVariantToCart('1', 'Coffee', 'Hot', 20000)", context);
events['#variantModal:hidden.bs.modal']();
assert.deepEqual(modalCalls.at(-1), ['#buyQueryModal', 'show']);
console.log('PASS: both vehicle variants wait for selection before service modal; chosen price/name persisted; Cafe variant flow preserved');

vm.runInContext("showBuyQueryModal('111', 'Order Type Coffee', 15000); saveBuyQuery()", context);
vm.runInContext("showBuyQueryModal('111', 'Order Type Coffee', 15000)", context);
values['#orderTypeSwitch'] = 'take-away';
vm.runInContext('saveBuyQuery()', context);
const cafeTypes = JSON.parse(storage.cart).filter(item => item.name === 'Order Type Coffee');
assert.equal(cafeTypes.length, 2);
assert.deepEqual(cafeTypes.map(item => item.orderType), ['dine-in', 'take-away']);
assert.equal(imported.find(item => item.cartType === 'product').orderType, null);
console.log('PASS: dine-in and take-away stay separate in cart; unknown imported type stays null');
