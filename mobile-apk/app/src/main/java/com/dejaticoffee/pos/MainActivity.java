package com.dejaticoffee.pos;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.Intent;
import android.graphics.Typeface;
import android.net.Uri;
import android.os.Bundle;
import android.view.Gravity;
import android.view.View;
import android.widget.Button;
import android.widget.EditText;
import android.widget.HorizontalScrollView;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.Spinner;
import android.widget.ArrayAdapter;
import android.widget.TextView;
import android.widget.Toast;

import org.json.JSONArray;
import org.json.JSONObject;

import java.util.ArrayList;
import java.util.List;

public class MainActivity extends Activity {
    private ApiClient api;
    private SessionStore session;
    private LinearLayout root;
    private JSONArray catalogItems = new JSONArray();
    private JSONArray categories = new JSONArray();
    private JSONArray cart = new JSONArray();
    private JSONArray transactions = new JSONArray();
    private String activeCategory = "all";

    @Override
    protected void onCreate(Bundle bundle) {
        super.onCreate(bundle);
        session = new SessionStore(this);
        api = new ApiClient(session.apiBaseUrl(BuildConfig.API_BASE_URL));
        api.setToken(session.token());
        if (session.token().isEmpty()) showLogin(); else showShell("pos");
    }

    private TextView tv(String text, int sp, int style) {
        TextView view = new TextView(this);
        view.setText(text);
        view.setTextSize(sp);
        view.setTypeface(Typeface.DEFAULT, style);
        view.setTextColor(0xff1d2433);
        view.setPadding(12, 8, 12, 8);
        return view;
    }

    private Button btn(String text) {
        Button button = new Button(this);
        button.setText(text);
        button.setAllCaps(false);
        return button;
    }

    private EditText input(String hint) {
        EditText edit = new EditText(this);
        edit.setHint(hint);
        edit.setSingleLine(true);
        return edit;
    }

    private void setRoot(View view) {
        setContentView(view);
    }

    private void toast(String text) {
        runOnUiThread(() -> Toast.makeText(this, text, Toast.LENGTH_SHORT).show());
    }

    private void showLogin() {
        ScrollView scroll = new ScrollView(this);
        LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL);
        box.setPadding(36, 60, 36, 36);
        scroll.addView(box);
        box.addView(tv("Dejati POS", 28, Typeface.BOLD));
        box.addView(tv("Mobile cashier for Cafe Garden & Carwash", 14, Typeface.NORMAL));
        EditText apiUrl = input("API URL");
        apiUrl.setText(api.getBaseUrl());
        EditText username = input("Username");
        username.setText("admin");
        EditText password = input("Password");
        password.setText("admin123");
        password.setInputType(0x00000081);
        Button login = btn("Log In");
        box.addView(apiUrl);
        box.addView(username);
        box.addView(password);
        box.addView(login);
        login.setOnClickListener(v -> {
            api.setBaseUrl(apiUrl.getText().toString());
            JSONObject body = new JSONObject();
            try {
                body.put("username", username.getText().toString());
                body.put("password", password.getText().toString());
            } catch (Exception ignored) {}
            api.post("/auth/login", body, new ApiClient.Callback() {
                public void onSuccess(JSONObject data) {
                    try {
                        String token = data.getString("token");
                        JSONObject user = data.getJSONObject("user");
                        api.setToken(token);
                        session.save(token, user.optString("name"), user.optString("role"), api.getBaseUrl());
                        runOnUiThread(() -> showShell("pos"));
                    } catch (Exception e) { toast(e.getMessage()); }
                }
                public void onError(String message) { toast(message); }
            });
        });
        setRoot(scroll);
    }

    private void showShell(String tab) {
        root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setBackgroundColor(0xfff4f7fb);
        LinearLayout header = new LinearLayout(this);
        header.setOrientation(LinearLayout.VERTICAL);
        header.setPadding(12, 12, 12, 4);
        header.addView(tv("Dejati POS", 22, Typeface.BOLD));
        header.addView(tv(session.name() + " • " + session.role(), 13, Typeface.NORMAL));
        root.addView(header);

        HorizontalScrollView navScroll = new HorizontalScrollView(this);
        LinearLayout nav = new LinearLayout(this);
        nav.setOrientation(LinearLayout.HORIZONTAL);
        navScroll.addView(nav);
        String[] tabs = {"pos", "history", "reports", "products", "settings"};
        for (String item : tabs) {
            Button b = btn(label(item));
            b.setOnClickListener(v -> showShell(item));
            nav.addView(b);
        }
        root.addView(navScroll);
        setRoot(root);
        if (tab.equals("pos")) loadCatalog();
        if (tab.equals("history")) loadTransactions();
        if (tab.equals("reports")) showReports();
        if (tab.equals("products")) showProductManager();
        if (tab.equals("settings")) showSettings();
    }

    private String label(String tab) {
        if (tab.equals("pos")) return "Kasir";
        if (tab.equals("history")) return "Transaksi";
        if (tab.equals("reports")) return "Reports";
        if (tab.equals("products")) return "Produk";
        return "Settings";
    }

    private void loadCatalog() {
        api.get("/catalog", new ApiClient.Callback() {
            public void onSuccess(JSONObject data) {
                catalogItems = data.optJSONArray("items") == null ? new JSONArray() : data.optJSONArray("items");
                categories = data.optJSONArray("categories") == null ? new JSONArray() : data.optJSONArray("categories");
                runOnUiThread(() -> renderPos());
            }
            public void onError(String message) { toast(message); }
        });
    }

    private void renderPos() {
        LinearLayout content = content();
        EditText search = input("Search menu");
        content.addView(search);
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
        content.addView(catScroll);
        LinearLayout list = new LinearLayout(this);
        list.setOrientation(LinearLayout.VERTICAL);
        content.addView(list);
        Runnable draw = () -> {
            list.removeAllViews();
            String q = search.getText().toString().toLowerCase();
            for (int i = 0; i < catalogItems.length(); i++) {
                JSONObject item = catalogItems.optJSONObject(i);
                if (item == null) continue;
                String category = item.optString("categoryId");
                if (!activeCategory.equals("all") && !activeCategory.equals(category)) continue;
                if (!item.optString("name").toLowerCase().contains(q)) continue;
                Button p = btn(item.optString("name") + "\n" + priceLabel(item));
                p.setGravity(Gravity.LEFT | Gravity.CENTER_VERTICAL);
                p.setOnClickListener(v -> selectCatalogItem(item));
                list.addView(p);
            }
        };
        search.addTextChangedListener(new android.text.TextWatcher() {
            public void beforeTextChanged(CharSequence s, int start, int count, int after) {}
            public void onTextChanged(CharSequence s, int start, int before, int count) { draw.run(); }
            public void afterTextChanged(android.text.Editable s) {}
        });
        draw.run();
        addCart(content);
    }

    private void addCategoryButton(LinearLayout row, String id, String text) {
        Button b = btn(text);
        b.setOnClickListener(v -> { activeCategory = id; renderPos(); });
        row.addView(b);
    }

    private String priceLabel(JSONObject item) {
        if (item.optBoolean("hasVariants")) return "Variants";
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
        LinearLayout form = new LinearLayout(this);
        form.setOrientation(LinearLayout.VERTICAL);
        EditText qty = input("Qty"); qty.setText("1");
        EditText notes = input("Notes");
        form.addView(qty); form.addView(notes);
        new AlertDialog.Builder(this).setTitle(name).setView(form).setPositiveButton("Add", (d, w) -> {
            try {
                JSONObject row = new JSONObject();
                row.put("type", "product"); row.put("productId", id); row.put("name", name);
                row.put("unitPrice", price); row.put("finalPrice", price); row.put("qty", Math.max(1, Integer.parseInt(qty.getText().toString())));
                row.put("notes", notes.getText().toString());
                cart.put(row); renderPos();
            } catch (Exception e) { toast(e.getMessage()); }
        }).setNegativeButton("Cancel", null).show();
    }

    private void addCarwashDialog(JSONObject item) {
        LinearLayout form = new LinearLayout(this);
        form.setOrientation(LinearLayout.VERTICAL);
        EditText nopol = input("No Polisi");
        EditText service = input("Pegawai");
        EditText ukuran = input("Ukuran"); ukuran.setText("Mobil Sedang");
        Spinner vacuum = new Spinner(this);
        vacuum.setAdapter(new ArrayAdapter<>(this, android.R.layout.simple_spinner_dropdown_item, new String[]{"no", "yes"}));
        form.addView(nopol); form.addView(service); form.addView(ukuran); form.addView(vacuum);
        new AlertDialog.Builder(this).setTitle(item.optString("name")).setView(form).setPositiveButton("Add", (d, w) -> {
            try {
                int finalPrice = item.optInt("price") + (vacuum.getSelectedItem().toString().equals("yes") ? 5000 : 0);
                JSONObject row = new JSONObject();
                row.put("type", "carwash"); row.put("productId", item.optInt("id")); row.put("name", item.optString("name"));
                row.put("unitPrice", finalPrice); row.put("finalPrice", finalPrice); row.put("qty", 1);
                row.put("nopol", nopol.getText().toString()); row.put("service", service.getText().toString()); row.put("ukuran", ukuran.getText().toString()); row.put("vacuum", vacuum.getSelectedItem().toString());
                row.put("notes", "NoPol: " + nopol.getText() + ", Service: " + service.getText() + ", Vacuum: " + vacuum.getSelectedItem());
                cart.put(row); renderPos();
            } catch (Exception e) { toast(e.getMessage()); }
        }).setNegativeButton("Cancel", null).show();
    }

    private void addCart(LinearLayout content) {
        content.addView(tv("Cart", 20, Typeface.BOLD));
        int total = 0;
        for (int i = 0; i < cart.length(); i++) {
            JSONObject item = cart.optJSONObject(i);
            int line = item.optInt("finalPrice") * item.optInt("qty");
            total += line;
            content.addView(tv(item.optString("name") + " x" + item.optInt("qty") + "  " + rupiah(line), 15, Typeface.NORMAL));
        }
        content.addView(tv("Total: " + rupiah(total), 22, Typeface.BOLD));
        LinearLayout actions = new LinearLayout(this);
        actions.setOrientation(LinearLayout.HORIZONTAL);
        Button pay = btn("Pay Now");
        Button open = btn("Open Bill");
        Button clear = btn("Clear");
        actions.addView(pay); actions.addView(open); actions.addView(clear);
        content.addView(actions);
        int finalTotal = total;
        pay.setOnClickListener(v -> checkout(false, finalTotal));
        open.setOnClickListener(v -> checkout(true, finalTotal));
        clear.setOnClickListener(v -> { cart = new JSONArray(); renderPos(); });
    }

    private void checkout(boolean openBill, int total) {
        if (cart.length() == 0) { toast("Cart is empty"); return; }
        LinearLayout form = new LinearLayout(this);
        form.setOrientation(LinearLayout.VERTICAL);
        EditText table = input("Table Number");
        EditText paid = input("Paid Amount"); paid.setText(openBill ? "0" : String.valueOf(total));
        Spinner method = new Spinner(this);
        method.setAdapter(new ArrayAdapter<>(this, android.R.layout.simple_spinner_dropdown_item, new String[]{"cash", "qris", "credit_card", "debit"}));
        form.addView(table); form.addView(method); form.addView(paid);
        new AlertDialog.Builder(this).setTitle(openBill ? "Open Bill" : "Payment").setView(form).setPositiveButton("Save", (d, w) -> {
            try {
                JSONObject body = new JSONObject();
                body.put("tableNumber", table.getText().toString());
                body.put("paymentMethod", method.getSelectedItem().toString());
                body.put("paidAmount", Integer.parseInt(paid.getText().toString()));
                body.put("items", cart);
                api.post("/transactions", body, new ApiClient.Callback() {
                    public void onSuccess(JSONObject data) {
                        cart = new JSONArray();
                        toast("Transaction saved");
                        printReceipt(data.optJSONObject("transaction"));
                        runOnUiThread(() -> showShell("pos"));
                    }
                    public void onError(String message) { toast(message); }
                });
            } catch (Exception e) { toast(e.getMessage()); }
        }).setNegativeButton("Cancel", null).show();
    }

    private void loadTransactions() {
        api.get("/transactions", new ApiClient.Callback() {
            public void onSuccess(JSONObject data) {
                transactions = data.optJSONArray("transactions") == null ? new JSONArray() : data.optJSONArray("transactions");
                runOnUiThread(() -> renderTransactions());
            }
            public void onError(String message) { toast(message); }
        });
    }

    private void renderTransactions() {
        LinearLayout content = content();
        for (int i = 0; i < transactions.length(); i++) {
            JSONObject t = transactions.optJSONObject(i);
            Button row = btn("#" + t.optInt("id") + " Table " + t.optString("tableNumber") + " • " + t.optString("status") + " • " + rupiah(t.optInt("totalAmount")));
            row.setGravity(Gravity.LEFT | Gravity.CENTER_VERTICAL);
            row.setOnClickListener(v -> showTransaction(t));
            content.addView(row);
        }
    }

    private void showTransaction(JSONObject t) {
        new AlertDialog.Builder(this).setTitle("Transaction #" + t.optInt("id")).setMessage(t.toString()).setPositiveButton("Print", (d, w) -> printReceipt(t)).setNegativeButton("Close", null).show();
    }

    private void showReports() {
        LinearLayout content = content();
        content.addView(tv("Daily Report", 22, Typeface.BOLD));
        api.get("/reports/daily", new ApiClient.Callback() {
            public void onSuccess(JSONObject data) {
                JSONObject s = data.optJSONObject("summary");
                runOnUiThread(() -> {
                    content.addView(tv("Total: " + rupiah(s.optInt("totalPenjualan")), 18, Typeface.BOLD));
                    content.addView(tv("Cash: " + rupiah(s.optInt("cash")), 16, Typeface.NORMAL));
                    content.addView(tv("QRIS: " + rupiah(s.optInt("qris")), 16, Typeface.NORMAL));
                    content.addView(tv("Card: " + rupiah(s.optInt("card")), 16, Typeface.NORMAL));
                    content.addView(tv("Cafe: " + rupiah(s.optInt("cafe")), 16, Typeface.NORMAL));
                    content.addView(tv("Carwash: " + rupiah(s.optInt("carwash")), 16, Typeface.NORMAL));
                    content.addView(tv("Net: " + rupiah(s.optInt("net")), 18, Typeface.BOLD));
                });
            }
            public void onError(String message) { toast(message); }
        });
    }

    private void showProductManager() {
        LinearLayout content = content();
        EditText name = input("Product name");
        EditText price = input("Price");
        Button save = btn("Save Product");
        content.addView(name); content.addView(price); content.addView(save);
        save.setOnClickListener(v -> {
            try {
                JSONObject body = new JSONObject();
                body.put("name", name.getText().toString()); body.put("price", Integer.parseInt(price.getText().toString())); body.put("categoryId", 1); body.put("hasVariants", false); body.put("variants", new JSONArray());
                api.post("/products", body, new ApiClient.Callback() {
                    public void onSuccess(JSONObject data) { toast("Product saved"); }
                    public void onError(String message) { toast(message); }
                });
            } catch (Exception e) { toast(e.getMessage()); }
        });
    }

    private void showSettings() {
        LinearLayout content = content();
        content.addView(tv("API: " + api.getBaseUrl(), 16, Typeface.NORMAL));
        Button logout = btn("Log Out");
        content.addView(logout);
        logout.setOnClickListener(v -> { session.clear(); api.setToken(""); showLogin(); });
    }

    private LinearLayout content() {
        while (root.getChildCount() > 2) root.removeViewAt(2);
        ScrollView scroll = new ScrollView(this);
        LinearLayout content = new LinearLayout(this);
        content.setOrientation(LinearLayout.VERTICAL);
        content.setPadding(16, 12, 16, 24);
        scroll.addView(content);
        root.addView(scroll, new LinearLayout.LayoutParams(-1, 0, 1));
        return content;
    }

    private String rupiah(int value) {
        return "Rp " + String.format("%,d", value).replace(',', '.');
    }

    private void printReceipt(JSONObject transaction) {
        if (transaction == null) return;
        String text = buildReceipt(transaction);
        Intent send = new Intent(Intent.ACTION_SEND);
        send.setType("text/plain");
        send.putExtra(Intent.EXTRA_TEXT, text);
        startActivity(Intent.createChooser(send, "Print receipt"));
        try {
            String rawbt = "rawbt:base64," + android.util.Base64.encodeToString(text.getBytes("UTF-8"), android.util.Base64.NO_WRAP);
            Intent rawIntent = new Intent(Intent.ACTION_VIEW, Uri.parse(rawbt));
            startActivity(rawIntent);
        } catch (Exception ignored) {}
    }

    private String buildReceipt(JSONObject t) {
        StringBuilder b = new StringBuilder();
        b.append("Dejati Coffee Garden\n");
        b.append("Table: ").append(t.optString("tableNumber")).append("\n");
        b.append("-----------------------------\n");
        JSONArray items = t.optJSONArray("items");
        for (int i = 0; items != null && i < items.length(); i++) {
            JSONObject item = items.optJSONObject(i);
            b.append(item.optString("name")).append(" x").append(item.optInt("qty")).append(" ").append(rupiah(item.optInt("total"))).append("\n");
        }
        b.append("-----------------------------\n");
        b.append("Total: ").append(rupiah(t.optInt("totalAmount"))).append("\n");
        b.append("Paid: ").append(rupiah(t.optInt("paidAmount"))).append("\n");
        b.append("Change: ").append(rupiah(t.optInt("changeAmount"))).append("\n");
        b.append("Terima kasih!\n");
        return b.toString();
    }
}
