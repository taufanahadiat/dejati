const state = {
  user: null,
  catalog: { categories: [], items: [] },
  cart: JSON.parse(localStorage.getItem("dejatiCart") || "[]"),
  category: "all",
  orders: []
};

const titles = {
  pos: ["Transaksi Kasir", "Build cafe and carwash orders from one cart."],
  reports: ["History Transaksi", "Review paid transactions and finalize open bills."],
  catalog: ["Produk", "Maintain cafe products and inspect the active catalog."],
  closing: ["Closing", "Summarize today's sales, expenses, and revenue split."]
};

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => Array.from(document.querySelectorAll(selector));
const rupiah = (value) => `Rp ${Number(value || 0).toLocaleString("id-ID")}`;
const imageBase = "https://cdn.jsdelivr.net/gh/araisantai/assets-automated@main/assets-cafe/img/products/";

async function api(path, options = {}) {
  const response = await fetch(`/api/${path}`, {
    headers: { "Content-Type": "application/json", ...(options.headers || {}) },
    ...options,
    body: options.body ? JSON.stringify(options.body) : undefined
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(data.error || "Request failed");
  return data;
}

async function boot() {
  const { user } = await api("me");
  if (user) {
    state.user = user;
    showApp();
    await loadCatalog();
    await loadOrders();
    await loadClosing();
  }
}

function showApp() {
  $("#loginView").classList.add("hidden");
  $("#appView").classList.remove("hidden");
  $("#userName").textContent = state.user.name;
  $("#userRole").textContent = state.user.role;
  $("#roleLabel").textContent = state.user.role;
  renderCart();
}

function initials(name) {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0].toUpperCase())
    .join("");
}

async function loadCatalog() {
  state.catalog = await api("catalog");
  renderCategories();
  renderProducts();
  renderCatalogAdmin();
}

async function loadOrders() {
  const data = await api("orders");
  state.orders = data.orders;
  renderOrders();
}

async function loadClosing() {
  const data = await api("closing");
  renderClosing(data.summary);
}

function renderCategories() {
  const tabs = [
    { id: "all", name: "All", icon: "grid_view" },
    { id: "carwash", name: "Carwash", icon: "local_car_wash" },
    ...state.catalog.categories
  ];
  $("#categoryTabs").innerHTML = tabs.map((cat) => `
    <button class="cat ${String(cat.id) === String(state.category) ? "active" : ""}" data-category="${cat.id}">
      <span class="material-symbols-outlined">${cat.icon}</span>
      <small>${cat.name}</small>
    </button>
  `).join("");
}

function renderProducts() {
  const query = $("#searchInput").value.trim().toLowerCase();
  const items = state.catalog.items.filter((item) => {
    const matchesCategory = state.category === "all" || String(item.categoryId) === String(state.category);
    const matchesSearch = item.name.toLowerCase().includes(query);
    return matchesCategory && matchesSearch;
  });
  $("#catalogCount").textContent = `${items.length} items`;
  $("#productGrid").innerHTML = items.map((item) => {
    const price = item.type === "carwash" ? item.price : item.hasVariants ? "Variants" : rupiah(item.price);
    const img = item.type === "product" && item.image
      ? `<img class="product-img" src="${imageBase}${item.image}" alt="">`
      : `<div class="product-img placeholder-img">${initials(item.name)}</div>`;
    return `
      <button class="product" data-id="${item.id}" data-type="${item.type}">
        ${img}
        <strong>${item.name}</strong>
        <small>${typeof price === "number" ? rupiah(price) : price}</small>
      </button>
    `;
  }).join("");
}

function renderCatalogAdmin() {
  $("#productMetric").textContent = state.catalog.items.filter((item) => item.type === "product").length;
  $("#carwashMetric").textContent = state.catalog.items.filter((item) => item.type === "carwash").length;
  $("#categoryMetric").textContent = state.catalog.categories.length;
  $("#cartMetric").textContent = state.cart.length;
  $("#newProductCategory").innerHTML = state.catalog.categories
    .map((cat) => `<option value="${cat.id}">${cat.name}</option>`)
    .join("");
}

function cartTotal() {
  return state.cart.reduce((sum, item) => sum + item.finalPrice * item.qty, 0);
}

function persistCart() {
  localStorage.setItem("dejatiCart", JSON.stringify(state.cart));
}

function renderCart() {
  persistCart();
  renderCatalogAdmin();
  $("#cartTotal").textContent = rupiah(cartTotal());
  $("#cartList").innerHTML = state.cart.length ? state.cart.map((item, index) => `
    <article class="cart-item">
      <div>
        <strong>${item.name}</strong>
        <p>${rupiah(item.finalPrice)} x ${item.qty}</p>
        ${item.notes ? `<small>${item.notes}</small>` : ""}
      </div>
      <div class="cart-actions">
        <button class="btn icon" data-cart-dec="${index}" title="Decrease">-</button>
        <button class="btn icon" data-cart-inc="${index}" title="Increase">+</button>
        <button class="btn icon danger" data-cart-remove="${index}" title="Remove">x</button>
      </div>
    </article>
  `).join("") : `<p class="error">Cart is empty.</p>`;
}

function addToCart(item) {
  const existing = state.cart.find((row) =>
    row.type === item.type &&
    row.productId === item.productId &&
    row.name === item.name &&
    row.finalPrice === item.finalPrice &&
    row.notes === item.notes
  );
  if (existing && item.type !== "carwash") {
    existing.qty += item.qty;
  } else {
    state.cart.push(item);
  }
  renderCart();
}

function openModal(title, content, footer = "") {
  $("#modalHost").innerHTML = `
    <div class="modal-backdrop">
      <section class="modal">
        <header>
          <h3>${title}</h3>
          <button class="btn icon" data-close-modal title="Close">x</button>
        </header>
        <main>${content}</main>
        ${footer ? `<footer>${footer}</footer>` : ""}
      </section>
    </div>
  `;
}

function closeModal() {
  $("#modalHost").innerHTML = "";
}

function chooseProduct(id, type) {
  const item = state.catalog.items.find((row) => String(row.id) === String(id) && row.type === type);
  if (!item) return;
  if (type === "carwash") {
    openCarwashModal(item);
  } else if (item.hasVariants) {
    openModal(
      item.name,
      `<div class="variant-list">${item.variants.map((variant) => `
        <button data-variant-product="${item.id}" data-variant-name="${variant.name}" data-variant-price="${variant.price}">
          <span>${variant.name}</span><strong>${rupiah(variant.price)}</strong>
        </button>
      `).join("")}</div>`
    );
  } else {
    openCafeModal(item.name, item.id, item.price);
  }
}

function openCafeModal(name, productId, price) {
  openModal("Add Item", `
    <div class="field"><label>Item</label><input class="input" id="itemName" value="${name}" readonly></div>
    <div class="field"><label>Quantity</label><input class="input" id="itemQty" type="number" min="1" value="1"></div>
    <div class="field"><label>Discount</label><input class="input" id="itemDiscount" type="number" min="0" value="0"></div>
    <div class="field"><label>Discount Type</label><select class="select" id="discountType"><option value="amount">Rp</option><option value="percent">Percent</option></select></div>
    <div class="field"><label>Notes</label><textarea class="textarea" id="itemNotes"></textarea></div>
  `, `<button class="btn" data-close-modal>Cancel</button><button class="btn primary" id="saveCafeItem">Add</button>`);

  $("#saveCafeItem").addEventListener("click", () => {
    const qty = Math.max(1, Number($("#itemQty").value || 1));
    const discount = Number($("#itemDiscount").value || 0);
    const finalPrice = $("#discountType").value === "percent"
      ? Math.max(0, price - Math.round(price * discount / 100))
      : Math.max(0, price - discount);
    addToCart({
      type: "product",
      productId,
      name,
      unitPrice: price,
      finalPrice,
      qty,
      notes: $("#itemNotes").value.trim()
    });
    closeModal();
  });
}

function openCarwashModal(item) {
  openModal("Add Carwash", `
    <div class="field"><label>Service</label><input class="input" value="${item.name}" readonly></div>
    <div class="field"><label>No Polisi</label><input class="input" id="nopol"></div>
    <div class="field"><label>Pegawai</label><input class="input" id="servicePerson" placeholder="Budi"></div>
    <div class="field"><label>Ukuran</label><select class="select" id="vehicleSize"><option>Motor</option><option>Mobil Kecil</option><option>Mobil Sedang</option><option>Mobil Besar</option></select></div>
    <div class="field"><label>Vacuum</label><select class="select" id="vacuum"><option value="no">Tidak</option><option value="yes">Ya (+Rp 5.000)</option></select></div>
  `, `<button class="btn" data-close-modal>Cancel</button><button class="btn warn" id="holdCarwash">Hold</button><button class="btn primary" id="saveCarwash">Add</button>`);

  const save = (hold) => {
    const vacuum = $("#vacuum").value;
    const finalPrice = item.price + (vacuum === "yes" ? 5000 : 0);
    const nopol = $("#nopol").value.trim();
    const service = $("#servicePerson").value.trim();
    const ukuran = $("#vehicleSize").value;
    addToCart({
      type: "carwash",
      productId: item.id,
      name: item.name,
      unitPrice: finalPrice,
      finalPrice,
      qty: 1,
      nopol,
      service,
      ukuran,
      vacuum,
      notes: hold ? `Hold, Vacuum: ${vacuum === "yes" ? "Ya" : "Tidak"}` : `NoPol: ${nopol}, Service: ${service}, Ukuran: ${ukuran}, Vacuum: ${vacuum === "yes" ? "Ya" : "Tidak"}`
    });
    closeModal();
  };
  $("#saveCarwash").addEventListener("click", () => save(false));
  $("#holdCarwash").addEventListener("click", () => save(true));
}

function openPaymentModal(openBill = false, orderId = null, total = cartTotal()) {
  openModal(openBill ? "Open Bill" : "Payment", `
    <div class="field"><label>Table Number</label><input class="input" id="payTable" type="number"></div>
    <div class="field"><label>Payment Method</label><select class="select" id="payMethod"><option value="cash">Cash</option><option value="qris">QRIS</option><option value="credit_card">Credit Card</option><option value="debit">Debit</option></select></div>
    <div class="field"><label>Total</label><input class="input" value="${rupiah(total)}" readonly></div>
    <div class="field ${openBill ? "hidden" : ""}"><label>Paid Amount</label><input class="input" id="paidAmount" type="number" min="0" value="${total}"></div>
  `, `<button class="btn" data-close-modal>Cancel</button><button class="btn primary" id="confirmPayment">${openBill ? "Save Open Bill" : "Confirm"}</button>`);

  $("#confirmPayment").addEventListener("click", async () => {
    const payload = {
      tableNumber: $("#payTable").value,
      paymentMethod: openBill ? "cash" : $("#payMethod").value,
      paidAmount: openBill ? 0 : Number($("#paidAmount").value || 0),
      items: state.cart
    };
    if (orderId) {
      await api(`orders/${orderId}`, { method: "PATCH", body: { paymentMethod: $("#payMethod").value, paidAmount: Number($("#paidAmount").value || 0) } });
      state.cart = [];
      renderCart();
    } else {
      await api("orders", { method: "POST", body: payload });
      state.cart = [];
      renderCart();
    }
    closeModal();
    await loadOrders();
    await loadClosing();
  });
}

function renderOrders() {
  $("#ordersBody").innerHTML = state.orders.map((order) => `
    <tr>
      <td>${order.createdAt}</td>
      <td>${order.tableNumber}</td>
      <td><span class="pill ${order.status}">${order.status}</span></td>
      <td>${order.paymentMethod}</td>
      <td>${rupiah(order.totalAmount)}</td>
      <td>
        <button class="btn" data-order-details="${order.id}">View</button>
        ${order.status === "open" ? `<button class="btn success" data-order-pay="${order.id}">Transact</button>` : ""}
      </td>
    </tr>
  `).join("");
}

function showOrderDetails(id) {
  const order = state.orders.find((row) => Number(row.id) === Number(id));
  if (!order) return;
  openModal(`Order #${order.id}`, `
    <table class="table">
      <thead><tr><th>Item</th><th>Qty</th><th>Total</th></tr></thead>
      <tbody>${order.items.map((item) => `<tr><td>${item.name}<br><small>${item.notes || ""}</small></td><td>${item.qty}</td><td>${rupiah(item.total)}</td></tr>`).join("")}</tbody>
    </table>
  `);
}

function renderClosing(summary) {
  const metrics = [
    ["Total Penjualan", summary.totalPenjualan],
    ["Cash", summary.cash],
    ["QRIS", summary.qris],
    ["Card", summary.card],
    ["Cafe", summary.cafe],
    ["Carwash", summary.carwash],
    ["Pengeluaran", summary.expenses.reduce((sum, row) => sum + Number(row.total), 0)],
    ["Net", summary.totalPenjualan - summary.expenses.reduce((sum, row) => sum + Number(row.total), 0)]
  ];
  $("#closingMetrics").innerHTML = metrics.map(([label, value]) => `<div class="metric"><span>${label}</span><strong>${rupiah(value)}</strong></div>`).join("");
  $("#expensesBody").innerHTML = summary.expenses.map((row) => `<tr><td>${row.createdAt}</td><td>${row.description}</td><td>${rupiah(row.total)}</td></tr>`).join("");
}

function switchView(view) {
  $$(".view").forEach((el) => el.classList.add("hidden"));
  $(`#${view}View`).classList.remove("hidden");
  $$("#nav button").forEach((button) => button.classList.toggle("active", button.dataset.view === view));
  $("#viewTitle").textContent = titles[view][0];
  $("#viewSubtitle").textContent = titles[view][1];
}

$("#loginForm").addEventListener("submit", async (event) => {
  event.preventDefault();
  $("#loginError").textContent = "";
  try {
    const { user } = await api("login", { method: "POST", body: { username: $("#username").value, password: $("#password").value } });
    state.user = user;
    showApp();
    await loadCatalog();
    await loadOrders();
    await loadClosing();
  } catch (error) {
    $("#loginError").textContent = error.message;
  }
});

$("#logoutBtn").addEventListener("click", async () => {
  await api("logout", { method: "POST" });
  location.reload();
});

$("#nav").addEventListener("click", (event) => {
  const button = event.target.closest("button[data-view]");
  if (button) switchView(button.dataset.view);
});

$("#categoryTabs").addEventListener("click", (event) => {
  const button = event.target.closest("button[data-category]");
  if (!button) return;
  state.category = button.dataset.category;
  renderCategories();
  renderProducts();
});

$("#productGrid").addEventListener("click", (event) => {
  const button = event.target.closest("button[data-id]");
  if (button) chooseProduct(button.dataset.id, button.dataset.type);
});

$("#modalHost").addEventListener("click", (event) => {
  if (event.target.matches("[data-close-modal]") || event.target.classList.contains("modal-backdrop")) closeModal();
  const variant = event.target.closest("[data-variant-product]");
  if (variant) {
    const product = state.catalog.items.find((item) => String(item.id) === String(variant.dataset.variantProduct));
    openCafeModal(`${product.name} (${variant.dataset.variantName})`, product.id, Number(variant.dataset.variantPrice));
  }
});

$("#cartList").addEventListener("click", (event) => {
  const inc = event.target.closest("[data-cart-inc]");
  const dec = event.target.closest("[data-cart-dec]");
  const remove = event.target.closest("[data-cart-remove]");
  if (inc) state.cart[Number(inc.dataset.cartInc)].qty += 1;
  if (dec) state.cart[Number(dec.dataset.cartDec)].qty = Math.max(1, state.cart[Number(dec.dataset.cartDec)].qty - 1);
  if (remove) state.cart.splice(Number(remove.dataset.cartRemove), 1);
  if (inc || dec || remove) renderCart();
});

$("#searchInput").addEventListener("input", renderProducts);
$("#refreshBtn").addEventListener("click", loadCatalog);
$("#clearCartBtn").addEventListener("click", () => { state.cart = []; renderCart(); });
$("#payBtn").addEventListener("click", () => state.cart.length && openPaymentModal(false));
$("#openBillBtn").addEventListener("click", () => state.cart.length && openPaymentModal(true));
$("#reloadOrdersBtn").addEventListener("click", loadOrders);

$("#ordersBody").addEventListener("click", (event) => {
  const details = event.target.closest("[data-order-details]");
  const pay = event.target.closest("[data-order-pay]");
  if (details) showOrderDetails(details.dataset.orderDetails);
  if (pay) {
    const order = state.orders.find((row) => Number(row.id) === Number(pay.dataset.orderPay));
    state.cart = order.items;
    renderCart();
    switchView("pos");
    openPaymentModal(false, order.id, order.totalAmount);
  }
});

$("#productForm").addEventListener("submit", async (event) => {
  event.preventDefault();
  await api("products", {
    method: "POST",
    body: {
      name: $("#newProductName").value,
      categoryId: Number($("#newProductCategory").value),
      price: Number($("#newProductPrice").value || 0),
      hasVariants: false,
      variants: []
    }
  });
  event.target.reset();
  await loadCatalog();
});

$("#expenseForm").addEventListener("submit", async (event) => {
  event.preventDefault();
  await api("expenses", { method: "POST", body: { description: $("#expenseDescription").value, total: Number($("#expenseTotal").value || 0) } });
  event.target.reset();
  await loadClosing();
});

$("#saveClosingBtn").addEventListener("click", async () => {
  await api("closing", { method: "POST" });
  await loadClosing();
});

boot().catch(() => {});
