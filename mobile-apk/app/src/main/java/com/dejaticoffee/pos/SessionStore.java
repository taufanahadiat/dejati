package com.dejaticoffee.pos;

import android.content.Context;
import android.content.SharedPreferences;

public class SessionStore {
    private final SharedPreferences prefs;

    public SessionStore(Context context) {
        prefs = context.getSharedPreferences("dejati_pos", Context.MODE_PRIVATE);
    }

    public void save(String token, String name, String role, String apiBaseUrl) {
        prefs.edit()
            .putString("token", token)
            .putString("name", name)
            .putString("role", role)
            .putString("apiBaseUrl", apiBaseUrl)
            .apply();
    }

    public String token() { return prefs.getString("token", ""); }
    public String name() { return prefs.getString("name", ""); }
    public String role() { return prefs.getString("role", ""); }
    public String apiBaseUrl(String fallback) { return prefs.getString("apiBaseUrl", fallback); }

    public void clear() {
        prefs.edit().clear().apply();
    }
}
