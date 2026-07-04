package com.dejaticoffee.pos;

import android.Manifest;
import android.app.Activity;
import android.app.AlertDialog;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothSocket;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.graphics.Typeface;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.view.Gravity;
import android.view.View;
import android.widget.ArrayAdapter;
import android.widget.Button;
import android.widget.EditText;
import android.widget.GridLayout;
import android.widget.HorizontalScrollView;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.Spinner;
import android.widget.TextView;
import android.widget.Toast;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.OutputStream;
import java.util.ArrayList;
import java.util.List;
import java.util.Set;
import java.util.UUID;

public class MainActivity extends Activity {
    private static final int BLUE = Color.rgb(37, 99, 235);
    private static final int GREEN = Color.rgb(19, 138, 91);
    private static final int RED = Color.rgb(208, 68, 68);
    private static final int AMBER = Color.rgb(183, 121, 31);
    private static final int INK = Color.rgb(29, 36, 51);
    private static final int MUTED = Color.rgb(102, 112, 133);
    private static final int PANEL = Color.WHITE;
    private static final int WASH = Color.rgb(244, 247, 251);
    private static final int NAV = Color.rgb(32, 41, 56);
    private static final UUID SPP_UUID = UUID.fromString("00001101-0000-1000-8000-00805F9B34FB");

    private ApiClient api;
    private SessionStore session;
    private LinearLayout root;
    private LinearLayout body;
    private JSONArray catalogItems = new JSONArray();
    private JSONArray categories = new JSONArray();
    private JSONArray cart = new JSONArray();
    private JSONArray transactions = new JSONArray();
    private String activeCategory = "all";
    private String activeScreen = "dashboard";

    @Override
    protected void onCreate(Bundle bundle) {
        super.onCreate(bundle);
        session = new SessionStore(this);
        api = new ApiClient(session.apiBaseUrl(BuildConfig.API_BASE_URL));
        api.setToken(session.token());
        if (session.token().isEmpty()) showLogin(); else showShell("dashboard");
    }

    private TextView tv(String text, int sp, int style) {
        TextView view = new TextView(this);
        view.setText(text);
        view.setTextSize(sp);
        view.setTypeface(Typeface.DEFAULT, style);
        view.setTextColor(INK);
        view.setPadding(dp(10), dp(6), dp(10), dp(6));
        return view;
    }

    private TextView small(String text) {
        TextView view = tv(text, 12, Typeface.NORMAL);
        view.setTextColor(MUTED);
        return view;
    }

    private Button btn(String text) {
        Button button = new Button(this);
        button.setText(text);
        button.setAllCaps(false);
        button.setMinHeight(dp(42));
        return button;
    }

    private Button coloredButton(String text, int color) {
        Button button = btn(text);
        button.setTextColor(Color.WHITE);
        button.setBackgroundColor(color);
        return button;
    }

    private EditText input(String hint) {
        EditText edit = new EditText(this);
        edit.setHint(hint);
        edit.setSingleLine(true);
        edit.setTextColor(INK);
        edit.setHintTextColor(MUTED);
        edit.setPadding(dp(10), dp(6), dp(10), dp(6));
        return edit;
    }

    private int dp(int value) {
        return (int) (value * getResources().getDisplayMetrics().density + 0.5f);
    }

    private void toast(String text) {
        runOnUiThread(() -> Toast.makeText(this, text, Toast.LENGTH_LONG).show());
    }

    private void showLogin() {
        ScrollView scroll = new ScrollView(this);
        scroll.setBackgroundColor(WASH);
        LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL);
        box.setPadding(dp(28), dp(52), dp(28), dp(28));
        scroll.addView(box);

        TextView logo = tv("Dejati POS", 30, Typeface.BOLD);
        logo.setGravity(Gravity.CENTER_HORIZONTAL);
        box.addView(logo);
        TextView subtitle = small("Cafe Garden & Carwash Mobile Cashier");
        subtitle.setGravity(Gravity.CENTER_HORIZONTAL);
        box.addView(subtitle);

        EditText apiUrl = input("API URL");
        apiUrl.setText(api.getBaseUrl());
        EditText username = input("Username");
        username.setText("admin");
        EditText password = input("Password");
        password.setText("admin123");
        password.setInputType(0x00000081);
        Button login = coloredButton("Log In", BLUE);
        box.addView(label("API URL"));
        box.addView(apiUrl);
        box.addView(label("Username"));
        box.addView(username);
        box.addView(label("Password"));
        box.addView(password);
        box.addView(login, matchWrap());

        login.setOnClickListener(v -> {
            api.setBaseUrl(apiUrl.getText().toString().trim());
            JSONObject body = new JSONObject();
            try {
                body.put("username", username.getText().toString().trim());
                body.put("password", password.getText().toString());
            } catch (Exception ignored) {}
            api.post("/auth/login", body, new ApiClient.Callback() {
                public void onSuccess(JSONObject data) {
                    try {
                        String token = data.getString("token");
                        JSONObject user = data.getJSONObject("user");
                        api.setToken(token);
                        session.save(token, user.optString("name"), user.optString("role"), api.getBaseUrl());
                        runOnUiThread(() -> showShell("dashboard"));
                    } catch (Exception e) { toast(e.getMessage()); }
                }
                public void onError(String message) { toast(message); }
            });
        });
        setContentView(scroll);
    }

    private TextView label(String text) {
        TextView view = tv(text, 12, Typeface.BOLD);
        view.setTextColor(MUTED);
        return view;
    }

    private void showShell(String screen) {
        activeScreen = screen;
        root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setBackgroundColor(WASH);

        LinearLayout header = new LinearLayout(this);
        header.setOrientation(LinearLayout.VERTICAL);
        header.setPadding(dp(12), dp(12), dp(12), dp(8));
        header.setBackgroundColor(NAV);
        TextView title = tv("Dejati Coffee Garden & Carwash", 18, Typeface.BOLD);
        title.setTextColor(Color.WHITE);
        TextView user = tv(session.name() + " • " + session.role(), 12, Typeface.NORMAL);
        user.setTextColor(Color.rgb(201, 212, 228));
        header.addView(title);
        header.addView(user);
        root.addView(header);

        ScrollView navScroll = new ScrollView(this);
        LinearLayout nav = new LinearLayout(this);
        nav.setOrientation(LinearLayout.VERTICAL);
        nav.setPadding(dp(8), dp(8), dp(8), dp(8));
        nav.setBackgroundColor(Color.rgb(238, 242, 247));
        navScroll.addView(nav);
        addNav(nav, "dashboard", "Dashboard");
        addNavHeader(nav, "Data Master");
        addNav(nav, "produkCafe", "  Produk Cafe");
        addNav(nav, "produkCarwash", "  Produk Carwash");
        addNav(nav, "transaksi", "Transaksi");
        addNav(nav, "report", "Report");
        addNav(nav, "stock", "Stock Management");
        root.addView(navScroll, new LinearLayout.LayoutParams(-1, dp(260)));

        body = new LinearLayout(this);
        body.setOrientation(LinearLayout.VERTICAL);
        body.setPadding(dp(12), dp(10), dp(12), dp(18));
        ScrollView bodyScroll = new ScrollView(this);
        bodyScroll.addView(body);
        root.addView(bodyScroll, new LinearLayout.LayoutParams(-1, 0, 1));
        setContentView(root);

        if (screen.equals("dashboard")) showDashboard();
        if (screen.equals("produkCafe")) showProductManager(false);
        if (screen.equals("produkCarwash")) showProductManager(true);
        if (screen.equals("transaksi")) loadCatalog();
        if (screen.equals("report")) showReportScreen();
        if (screen.equals("stock")) showStockManagement();
    }

    private void addNavHeader(LinearLayout nav, String text) {
        TextView h = label(text.toUpperCase());
        h.setPadding(dp(12), dp(12), dp(12), dp(2));
        nav.addView(h);
    }

    private void addNav(LinearLayout nav, String screen, String text) {
        Button b = btn(text);
        b.setGravity(Gravity.LEFT | Gravity.CENTER_VERTICAL);
        b.setTextColor(screen.equals(activeScreen) ? Color.WHITE : INK);
        b.setBackgroundColor(screen.equals(activeScreen) ? BLUE : Color.TRANSPARENT);
        b.setOnClickListener(v -> showShell(screen));
        nav.addView(b, matchWrap());
    }

    private void clearBody(String title, String subtitle) {
        body.removeAllViews();
        body.addView(tv(title, 22, Typeface.BOLD));
        if (subtitle != null && !subtitle.isEmpty()) body.addView(small(subtitle));
    }

    private void showDashboard() {
        clearBody("Dashboard", "Ringkasan penjualan dan status operasional hari ini.");
        LinearLayout metrics = new LinearLayout(this);
        metrics.setOrientation(LinearLayout.VERTICAL);
        body.addView(metrics);
        api.get("/reports/daily", new ApiClient.Callback() {
            public void onSuccess(JSONObject data) {
                JSONObject s = data.optJSONObject("summary");
                runOnUiThread(() -> {
                    metrics.removeAllViews();
                    metric(metrics, "Total Penjualan", rupiah(s.optInt("totalPenjualan")));
                    metric(metrics, "Cafe", rupiah(s.optInt("cafe")));
                    metric(metrics, "Carwash", rupiah(s.optInt("carwash")));
                    metric(metrics, "Pengeluaran", rupiah(s.optInt("pengeluaran")));
                    metric(metrics, "Net", rupiah(s.optInt("net")));
                });
            }
            public void onError(String message) { toast(message); }
        });
    }

    private void metric(LinearLayout parent, String label, String value) {
        LinearLayout card = card();
        card.addView(small(label));
        card.addView(tv(value, 20, Typeface.BOLD));
        parent.addView(card, matchWrap());
    }

    private void loadCatalog() {
        api.get("/catalog", new ApiClient.Callback() {
            public void onSuccess(JSONObject data) {
                catalogItems = data.optJSONArray("items") == null ? new JSONArray() : data.optJSONArray("items");
                categories = data.optJSONArray("categories") == null ? new JSONArray() : data.optJSONArray("categories");
                runOnUiThread(() -> renderTransaksi());
            }
            public void onError(String message) { toast(message); }
        });
    }

    private void renderTransaksi() {
        clearBody("Transaksi Kasir", "Pilih produk, review cart, lalu Pay Now atau Open Bill.");
        EditText search = input("Search product...");
        body.addView(search);

        HorizontalScrollView catScroll = new HorizontalScrollView(this);
        LinearLayout catRow = new LinearLayout(this);
        catRow.setOrientation(LinearLayout.HORIZONTAL);
        catScroll.addView(catRow);
        addCategoryButton(catRow, "all", "All");
        addCategoryButton(catRow, "carwash", "Carwash");
        for (int i = 0; i < categories.length(); i++) {
            JSONObject c = categories.optJSONObject(i);
            if (c != null) addCategoryButton(catRow, String.valueOf(c.optInt("id")), c.optString("name"));
        }
        body.addView(catScroll);

        GridLayout grid = new GridLayout(this);
        grid.setColumnCount(2);
        body.addView(grid);
        Runnable draw = () -> drawProducts(grid, search.getText().toString().toLowerCase());
        search.addTextChangedListener(new android.text.TextWatcher() {
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {}
            public void onTextChanged(CharSequence s, int start, int before, int count) { draw.run(); }
            public void afterTextChanged(android.text.Editable s) {}
        });
        draw.run();
        addCartPanel();
    }

    private void drawProducts(GridLayout grid, String q) {
        grid.removeAllViews();
        for (int i = 0; i < catalogItems.length(); i++) {
            JSONObject item = catalogItems.optJSONObject(i);
            if (item == null) continue;
            String category = item.optString("categoryId");
            if (!activeCategory.equals("all") && !activeCategory.equals(category)) continue;
            if (!item.optString("name").toLowerCase().contains(q)) continue;
            LinearLayout product = card();
            product.setMinimumHeight(dp(112));
            product.addView(tv(item.optString("name"), 14, Typeface.BOLD));
            product.addView(small(item.optString("type").equals("carwash") ? "Carwash" : categoryName(item.optString("categoryId"))));
            product.addView(tv(priceLabel(item), 14, Typeface.BOLD));
            product.setOnClickListener(v -> selectCatalogItem(item));
            GridLayout.LayoutParams lp = new GridLayout.LayoutParams();
            lp.width = getResources().getDisplayMetrics().widthPixels / 2 - dp(22);
            lp.setMargins(dp(4), dp(4), dp(4), dp(4));
            grid.addView(product, lp);
        }
    }

    private String categoryName(String id) {
        for (int i = 0; i < categories.length(); i++) {
            JSONObject c = categories.optJSONObject(i);
            if (c != null && String.valueOf(c.optInt("id")).equals(id)) return c.optString("name");
        }
        return "Cafe";
    }

    private void addCategoryButton(LinearLayout row, String id, String text) {
        Button b = btn(text);
        b.setTextColor(id.equals(activeCategory) ? Color.WHITE : INK);
        b.setBackgroundColor(id.equals(activeCategory) ? BLUE : Color.LTGRAY);
        b.setOnClickListener(v -> { activeCategory = id; renderTransaksi(); });
        row.addView(b);
    }

    private String priceLabel(JSONObject item) {
        if (item.optBoolean("hasVariants")) return "Variant";
        return rupiah(item.optInt("price"));
    }

    private void selectCatalogItem(JSONObject item) {
        if (item.optBoolean("hasVariants")) {
            JSONArray variants = item.optJSONArray("variants");
            List<String> labels = new ArrayList<>();
            for (int i = 0; variants != null && i < variants.length(); i++) {
                JSONObject v = variants.optJSONObject(i);
                labels.add(v.optString("name") + " - " + rupiah(v.optInt("price")));
            }
            new AlertDialog.Builder(this).setTitle(item.optString("name")).setItems(labels.toArray(new String[0]), (d, which) -> {
                JSONObject variant = variants.optJSONObject(which);
                addCafeItem(item.optInt("id"), item.optString("name") + " (" + variant.optString("name") + ")", variant.optInt("price"));
            }).show();
        } else if (item.optString("type").equals("carwash")) {
            addCarwashDialog(item);
        } else {
            addCafeItem(item.optInt("id"), item.optString("name"), item.optInt("price"));
        }
    }

    private void addCafeItem(int id, String name, int price) {
        LinearLayout form = dialogForm();
        EditText qty = input("Qty"); qty.setText("1");
        EditText discount = input("Discount Rp"); discount.setText("0");
        EditText notes = input("Notes");
        form.addView(qty); form.addView(discount); form.addView(notes);
        new AlertDialog.Builder(this).setTitle(name).setView(form).setPositiveButton("Add", (d, w) -> {
            try {
                int finalPrice = Math.max(0, price - intValue(discount));
                JSONObject row = new JSONObject();
                row.put("type", "product"); row.put("productId", id); row.put("name", name);
                row.put("unitPrice", price); row.put("finalPrice", finalPrice); row.put("qty", Math.max(1, intValue(qty)));
                row.put("notes", notes.getText().toString());
                cart.put(row); renderTransaksi();
            } catch (Exception e) { toast(e.getMessage()); }
        }).setNegativeButton("Cancel", null).show();
    }

    private void addCarwashDialog(JSONObject item) {
        LinearLayout form = dialogForm();
        EditText nopol = input("No Polisi");
        EditText service = input("Pegawai");
        EditText ukuran = input("Ukuran"); ukuran.setText("Mobil Sedang");
        Spinner vacuum = new Spinner(this);
        vacuum.setAdapter(new ArrayAdapter<>(this, android.R.layout.simple_spinner_dropdown_item, new String[]{"no", "yes (+Rp 5.000)"}));
        form.addView(nopol); form.addView(service); form.addView(ukuran); form.addView(vacuum);
        new AlertDialog.Builder(this).setTitle(item.optString("name")).setView(form).setPositiveButton("Add", (d, w) -> {
            try {
                boolean withVacuum = vacuum.getSelectedItem().toString().startsWith("yes");
                int finalPrice = item.optInt("price") + (withVacuum ? 5000 : 0);
                JSONObject row = new JSONObject();
                row.put("type", "carwash"); row.put("productId", item.optInt("id")); row.put("name", item.optString("name"));
                row.put("unitPrice", finalPrice); row.put("finalPrice", finalPrice); row.put("qty", 1);
                row.put("nopol", nopol.getText().toString()); row.put("service", service.getText().toString()); row.put("ukuran", ukuran.getText().toString()); row.put("vacuum", withVacuum ? "yes" : "no");
                row.put("notes", "NoPol: " + nopol.getText() + ", Service: " + service.getText() + ", Ukuran: " + ukuran.getText() + ", Vacuum: " + (withVacuum ? "Ya" : "Tidak"));
                cart.put(row); renderTransaksi();
            } catch (Exception e) { toast(e.getMessage()); }
        }).setNegativeButton("Cancel", null).show();
    }

    private void addCartPanel() {
        LinearLayout panel = card();
        panel.addView(tv("Order Summary", 18, Typeface.BOLD));
        int total = 0;
        for (int i = 0; i < cart.length(); i++) {
            JSONObject item = cart.optJSONObject(i);
            int line = item.optInt("finalPrice") * item.optInt("qty");
            total += line;
            TextView row = tv(item.optString("name") + " x" + item.optInt("qty") + "  " + rupiah(line), 14, Typeface.NORMAL);
            row.setOnClickListener(v -> toast("Long press remove coming next build"));
            panel.addView(row);
            if (!item.optString("notes").isEmpty()) panel.addView(small(item.optString("notes")));
        }
        panel.addView(tv("Total: " + rupiah(total), 22, Typeface.BOLD));
        LinearLayout actions = new LinearLayout(this);
        actions.setOrientation(LinearLayout.HORIZONTAL);
        Button pay = coloredButton("Pay Now", GREEN);
        Button open = coloredButton("Open Bill", AMBER);
        Button clear = coloredButton("Clear", RED);
        actions.addView(pay, weightWrap());
        actions.addView(open, weightWrap());
        actions.addView(clear, weightWrap());
        panel.addView(actions);
        body.addView(panel, matchWrap());
        int finalTotal = total;
        pay.setOnClickListener(v -> checkout(false, finalTotal));
        open.setOnClickListener(v -> checkout(true, finalTotal));
        clear.setOnClickListener(v -> { cart = new JSONArray(); renderTransaksi(); });
    }

    private void checkout(boolean openBill, int total) {
        if (cart.length() == 0) { toast("Cart is empty"); return; }
        LinearLayout form = dialogForm();
        EditText table = input("Table Number");
        EditText paid = input("Paid Amount"); paid.setText(openBill ? "0" : String.valueOf(total));
        Spinner method = new Spinner(this);
        method.setAdapter(new ArrayAdapter<>(this, android.R.layout.simple_spinner_dropdown_item, new String[]{"cash", "qris", "credit_card", "debit"}));
        form.addView(table); form.addView(method); if (!openBill) form.addView(paid);
        new AlertDialog.Builder(this).setTitle(openBill ? "Open Bill" : "Payment").setView(form).setPositiveButton("Save", (d, w) -> {
            try {
                JSONObject body = new JSONObject();
                body.put("tableNumber", table.getText().toString());
                body.put("paymentMethod", method.getSelectedItem().toString());
                body.put("paidAmount", openBill ? 0 : intValue(paid));
                body.put("items", cart);
                api.post("/transactions", body, new ApiClient.Callback() {
                    public void onSuccess(JSONObject data) {
                        JSONObject transaction = data.optJSONObject("transaction");
                        cart = new JSONArray();
                        runOnUiThread(() -> {
                            renderTransaksi();
                            showPrintChooser(transaction);
                        });
                    }
                    public void onError(String message) { toast(message); }
                });
            } catch (Exception e) { toast(e.getMessage()); }
        }).setNegativeButton("Cancel", null).show();
    }

    private void showPrintChooser(JSONObject transaction) {
        if (transaction == null) return;
        String[] options = {"Print RawBT", "Print Bluetooth Printer", "Share/Android Print", "Skip"};
        new AlertDialog.Builder(this).setTitle("Print Receipt").setItems(options, (dialog, which) -> {
            if (which == 0) printRawBt(transaction);
            if (which == 1) chooseBluetoothPrinter(transaction);
            if (which == 2) shareReceipt(transaction);
        }).show();
    }

    private void printRawBt(JSONObject transaction) {
        try {
            String text = buildReceipt(transaction);
            String rawbt = "rawbt:base64," + android.util.Base64.encodeToString(text.getBytes("UTF-8"), android.util.Base64.NO_WRAP);
            startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(rawbt)));
        } catch (Exception e) { toast("RawBT not available: " + e.getMessage()); }
    }

    private void chooseBluetoothPrinter(JSONObject transaction) {
        if (!hasBluetoothPermission()) return;
        BluetoothAdapter adapter = BluetoothAdapter.getDefaultAdapter();
        if (adapter == null) { toast("Bluetooth not available"); return; }
        if (!adapter.isEnabled()) { startActivity(new Intent(BluetoothAdapter.ACTION_REQUEST_ENABLE)); return; }
        Set<BluetoothDevice> bonded = adapter.getBondedDevices();
        if (bonded == null || bonded.isEmpty()) { toast("Pair printer in Android Bluetooth settings first"); return; }
        List<BluetoothDevice> devices = new ArrayList<>(bonded);
        List<String> labels = new ArrayList<>();
        for (BluetoothDevice d : devices) labels.add(deviceLabel(d));
        new AlertDialog.Builder(this).setTitle("Choose Printer").setItems(labels.toArray(new String[0]), (dialog, which) -> printBluetooth(devices.get(which), transaction)).show();
    }

    private boolean hasBluetoothPermission() {
        if (Build.VERSION.SDK_INT >= 31 && checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(new String[]{Manifest.permission.BLUETOOTH_CONNECT}, 200);
            toast("Allow Bluetooth permission, then press print again");
            return false;
        }
        return true;
    }

    private String deviceLabel(BluetoothDevice device) {
        if (Build.VERSION.SDK_INT >= 31 && checkSelfPermission(Manifest.permission.BLUETOOTH_CONNECT) != PackageManager.PERMISSION_GRANTED) return "Bluetooth printer";
        String name = device.getName();
        return (name == null || name.isEmpty() ? "Printer" : name) + " • " + device.getAddress();
    }

    private void printBluetooth(BluetoothDevice device, JSONObject transaction) {
        new Thread(() -> {
            try {
                if (!hasBluetoothPermission()) return;
                BluetoothSocket socket = device.createRfcommSocketToServiceRecord(SPP_UUID);
                BluetoothAdapter.getDefaultAdapter().cancelDiscovery();
                socket.connect();
                OutputStream os = socket.getOutputStream();
                os.write(new byte[]{0x1B, 0x40});
                os.write(buildReceipt(transaction).getBytes("UTF-8"));
                os.write(new byte[]{0x0A, 0x0A, 0x0A, 0x1D, 0x56, 0x00});
                os.flush();
                os.close();
                socket.close();
                toast("Printed to " + deviceLabel(device));
            } catch (Exception e) { toast("Bluetooth print failed: " + e.getMessage()); }
        }).start();
    }

    private void shareReceipt(JSONObject transaction) {
        Intent send = new Intent(Intent.ACTION_SEND);
        send.setType("text/plain");
        send.putExtra(Intent.EXTRA_TEXT, buildReceipt(transaction));
        startActivity(Intent.createChooser(send, "Print receipt"));
    }

    private void showReportScreen() {
        clearBody("Report", "History transaksi dan ringkasan penjualan.");
        Button reload = coloredButton("Reload Report", BLUE);
        body.addView(reload, matchWrap());
        reload.setOnClickListener(v -> showReportScreen());
        api.get("/reports/daily", new ApiClient.Callback() {
            public void onSuccess(JSONObject data) {
                JSONObject s = data.optJSONObject("summary");
                runOnUiThread(() -> {
                    metric(body, "Total Penjualan", rupiah(s.optInt("totalPenjualan")));
                    metric(body, "Cash", rupiah(s.optInt("cash")));
                    metric(body, "QRIS", rupiah(s.optInt("qris")));
                    metric(body, "Card", rupiah(s.optInt("card")));
                    loadTransactionsIntoReport();
                });
            }
            public void onError(String message) { toast(message); }
        });
    }

    private void loadTransactionsIntoReport() {
        api.get("/transactions", new ApiClient.Callback() {
            public void onSuccess(JSONObject data) {
                transactions = data.optJSONArray("transactions") == null ? new JSONArray() : data.optJSONArray("transactions");
                runOnUiThread(() -> {
                    body.addView(tv("History Transaksi", 18, Typeface.BOLD));
                    for (int i = 0; i < transactions.length(); i++) {
                        JSONObject t = transactions.optJSONObject(i);
                        Button row = btn("#" + t.optInt("id") + " Table " + t.optString("tableNumber") + " • " + t.optString("status") + " • " + rupiah(t.optInt("totalAmount")));
                        row.setGravity(Gravity.LEFT | Gravity.CENTER_VERTICAL);
                        row.setOnClickListener(v -> showTransaction(t));
                        body.addView(row, matchWrap());
                    }
                });
            }
            public void onError(String message) { toast(message); }
        });
    }

    private void showTransaction(JSONObject t) {
        new AlertDialog.Builder(this).setTitle("Transaction #" + t.optInt("id")).setMessage(receiptPreview(t)).setPositiveButton("Print", (d, w) -> showPrintChooser(t)).setNegativeButton("Close", null).show();
    }

    private void showProductManager(boolean carwash) {
        clearBody(carwash ? "Produk Carwash" : "Produk Cafe", "Tambah dan cek data produk.");
        EditText name = input(carwash ? "Nama layanan carwash" : "Nama produk cafe");
        EditText price = input("Harga");
        Button save = coloredButton("Simpan", BLUE);
        body.addView(name); body.addView(price); body.addView(save, matchWrap());
        save.setOnClickListener(v -> {
            try {
                JSONObject payload = new JSONObject();
                payload.put("name", name.getText().toString());
                payload.put("price", intValue(price));
                if (!carwash) {
                    payload.put("categoryId", 1);
                    payload.put("hasVariants", false);
                    payload.put("variants", new JSONArray());
                }
                api.post(carwash ? "/carwash-products" : "/products", payload, new ApiClient.Callback() {
                    public void onSuccess(JSONObject data) { toast("Saved"); runOnUiThread(() -> showProductManager(carwash)); }
                    public void onError(String message) { toast(message); }
                });
            } catch (Exception e) { toast(e.getMessage()); }
        });
        loadProductList(carwash);
    }

    private void loadProductList(boolean carwash) {
        api.get(carwash ? "/carwash-products" : "/products", new ApiClient.Callback() {
            public void onSuccess(JSONObject data) {
                JSONArray list = data.optJSONArray("products");
                runOnUiThread(() -> {
                    body.addView(tv("Daftar Produk", 18, Typeface.BOLD));
                    for (int i = 0; list != null && i < list.length(); i++) {
                        JSONObject p = list.optJSONObject(i);
                        body.addView(tv(p.optString("name") + "  " + rupiah(p.optInt("price")), 14, Typeface.NORMAL));
                    }
                });
            }
            public void onError(String message) { toast(message); }
        });
    }

    private void showStockManagement() {
        clearBody("Stock Management", "Utility stock workflow from the web app.");
        LinearLayout form = card();
        EditText name = input("Item name");
        EditText qty = input("Stock quantity");
        Button save = coloredButton("Save Stock", BLUE);
        form.addView(name);
        form.addView(qty);
        form.addView(save, matchWrap());
        body.addView(form, matchWrap());
        save.setOnClickListener(v -> {
            try {
                JSONObject payload = new JSONObject();
                payload.put("name", name.getText().toString());
                payload.put("itemNumber", intValue(qty));
                api.post("/stock", payload, new ApiClient.Callback() {
                    public void onSuccess(JSONObject data) { toast("Stock saved"); runOnUiThread(() -> showStockManagement()); }
                    public void onError(String message) { toast(message); }
                });
            } catch (Exception e) { toast(e.getMessage()); }
        });
        api.get("/stock", new ApiClient.Callback() {
            public void onSuccess(JSONObject data) {
                JSONArray stock = data.optJSONArray("stock");
                runOnUiThread(() -> {
                    body.addView(tv("Current Stock", 18, Typeface.BOLD));
                    for (int i = 0; stock != null && i < stock.length(); i++) {
                        JSONObject item = stock.optJSONObject(i);
                        Button row = btn(item.optString("name") + " • " + item.optInt("itemNumber"));
                        row.setGravity(Gravity.LEFT | Gravity.CENTER_VERTICAL);
                        row.setOnClickListener(v -> editStockItem(item));
                        body.addView(row, matchWrap());
                    }
                    Button logout = coloredButton("Log Out", RED);
                    body.addView(logout, matchWrap());
                    logout.setOnClickListener(v -> { session.clear(); api.setToken(""); showLogin(); });
                });
            }
            public void onError(String message) { toast(message); }
        });
    }

    private void editStockItem(JSONObject item) {
        LinearLayout form = dialogForm();
        EditText name = input("Item name");
        name.setText(item.optString("name"));
        EditText qty = input("Stock quantity");
        qty.setText(String.valueOf(item.optInt("itemNumber")));
        form.addView(name);
        form.addView(qty);
        new AlertDialog.Builder(this).setTitle("Edit Stock").setView(form).setPositiveButton("Save", (d, w) -> {
            try {
                JSONObject payload = new JSONObject();
                payload.put("name", name.getText().toString());
                payload.put("itemNumber", intValue(qty));
                api.patch("/stock/" + item.optInt("id"), payload, new ApiClient.Callback() {
                    public void onSuccess(JSONObject data) { toast("Stock updated"); runOnUiThread(() -> showStockManagement()); }
                    public void onError(String message) { toast(message); }
                });
            } catch (Exception e) { toast(e.getMessage()); }
        }).setNegativeButton("Cancel", null).show();
    }

    private LinearLayout card() {
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setBackgroundColor(PANEL);
        card.setPadding(dp(10), dp(8), dp(10), dp(8));
        return card;
    }

    private LinearLayout dialogForm() {
        LinearLayout form = new LinearLayout(this);
        form.setOrientation(LinearLayout.VERTICAL);
        form.setPadding(dp(8), dp(4), dp(8), dp(4));
        return form;
    }

    private int intValue(EditText input) {
        try { return Integer.parseInt(input.getText().toString().replaceAll("[^0-9]", "")); }
        catch (Exception e) { return 0; }
    }

    private LinearLayout.LayoutParams matchWrap() {
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(-1, -2);
        lp.setMargins(0, dp(4), 0, dp(4));
        return lp;
    }

    private LinearLayout.LayoutParams weightWrap() {
        LinearLayout.LayoutParams lp = new LinearLayout.LayoutParams(0, -2, 1);
        lp.setMargins(dp(2), dp(2), dp(2), dp(2));
        return lp;
    }

    private String rupiah(int value) {
        return "Rp " + String.format("%,d", value).replace(',', '.');
    }

    private String receiptPreview(JSONObject t) {
        return buildReceipt(t).replace("\u001B@", "");
    }

    private String buildReceipt(JSONObject t) {
        StringBuilder b = new StringBuilder();
        b.append("Dejati Coffee Garden\n");
        b.append("IG: instagram.com/dejati.coffee\n");
        b.append("Wifi: dejati37\n");
        b.append("-----------------------------\n");
        b.append("Invoice #").append(t.optInt("id")).append("\n");
        b.append("Table: ").append(t.optString("tableNumber")).append("\n");
        b.append("-----------------------------\n");
        JSONArray items = t.optJSONArray("items");
        for (int i = 0; items != null && i < items.length(); i++) {
            JSONObject item = items.optJSONObject(i);
            b.append(item.optString("name")).append(" x").append(item.optInt("qty")).append(" ").append(rupiah(item.optInt("total"))).append("\n");
        }
        b.append("-----------------------------\n");
        b.append("Total: ").append(rupiah(t.optInt("totalAmount"))).append("\n");
        b.append("Bayar: ").append(rupiah(t.optInt("paidAmount"))).append("\n");
        b.append("Kembali: ").append(rupiah(t.optInt("changeAmount"))).append("\n");
        b.append("Metode: ").append(t.optString("paymentMethod")).append("\n");
        b.append("-----------------------------\n");
        b.append("Terima kasih atas kunjungannya!\n\n");
        return b.toString();
    }
}
