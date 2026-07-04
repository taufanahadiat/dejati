package com.dejaticoffee.pos;

import org.json.JSONObject;
import java.io.BufferedReader;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;

public class ApiClient {
    public interface Callback {
        void onSuccess(JSONObject data);
        void onError(String message);
    }

    private String baseUrl;
    private String token;

    public ApiClient(String baseUrl) {
        this.baseUrl = baseUrl.replaceAll("/$", "");
    }

    public void setBaseUrl(String baseUrl) {
        this.baseUrl = baseUrl.replaceAll("/$", "");
    }

    public String getBaseUrl() {
        return baseUrl;
    }

    public void setToken(String token) {
        this.token = token;
    }

    public void get(String path, Callback callback) {
        request("GET", path, null, callback);
    }

    public void post(String path, JSONObject body, Callback callback) {
        request("POST", path, body, callback);
    }

    public void patch(String path, JSONObject body, Callback callback) {
        request("PATCH", path, body, callback);
    }

    private void request(String method, String path, JSONObject body, Callback callback) {
        new Thread(() -> {
            HttpURLConnection connection = null;
            try {
                URL url = new URL(baseUrl + (path.startsWith("/") ? path : "/" + path));
                connection = (HttpURLConnection) url.openConnection();
                connection.setRequestMethod(method);
                connection.setConnectTimeout(15000);
                connection.setReadTimeout(15000);
                connection.setRequestProperty("Accept", "application/json");
                connection.setRequestProperty("Content-Type", "application/json");
                if (token != null && !token.isEmpty()) {
                    connection.setRequestProperty("Authorization", "Bearer " + token);
                }
                if (body != null) {
                    connection.setDoOutput(true);
                    OutputStream os = connection.getOutputStream();
                    os.write(body.toString().getBytes("UTF-8"));
                    os.close();
                }
                int status = connection.getResponseCode();
                InputStream stream = status >= 200 && status < 300 ? connection.getInputStream() : connection.getErrorStream();
                String text = read(stream);
                JSONObject json = text.isEmpty() ? new JSONObject() : new JSONObject(text);
                if (status >= 200 && status < 300) {
                    callback.onSuccess(json);
                } else {
                    callback.onError(json.optString("error", "HTTP " + status));
                }
            } catch (Exception e) {
                callback.onError(e.getMessage() == null ? "Network error" : e.getMessage());
            } finally {
                if (connection != null) connection.disconnect();
            }
        }).start();
    }

    private String read(InputStream stream) throws Exception {
        if (stream == null) return "";
        BufferedReader reader = new BufferedReader(new InputStreamReader(stream, "UTF-8"));
        StringBuilder builder = new StringBuilder();
        String line;
        while ((line = reader.readLine()) != null) builder.append(line);
        return builder.toString();
    }
}
