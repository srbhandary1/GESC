package com.globalenglish.app;

import android.content.Context;
import android.content.SharedPreferences;

import androidx.annotation.NonNull;
import androidx.security.crypto.EncryptedSharedPreferences;
import androidx.security.crypto.MasterKey;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

import org.json.JSONObject;

import java.io.IOException;
import java.security.GeneralSecurityException;

/**
 * Reviewed native secure store for PR-014 (ADR-0012).
 * Uses AndroidX EncryptedSharedPreferences with a Keystore-backed master key.
 * Ordinary Capacitor Preferences must never hold these values.
 */
@CapacitorPlugin(name = "GlobalEnglishSecureCredentials")
public class GlobalEnglishSecureCredentialsPlugin extends Plugin {
    private static final String PREFS_NAME = "ge_secure_credentials";
    private static final String INSTALLATION_ID_KEY = "installation_ulid";
    private static final String REFRESH_TOKEN_KEY = "refresh_token";
    private static final String PENDING_AUTHORIZATION_KEY = "pending_authorization";

    private SharedPreferences securePreferences;

    @Override
    public void load() {
        try {
            this.securePreferences = createSecurePreferences(getContext());
        } catch (GeneralSecurityException | IOException error) {
            throw new IllegalStateException("Unable to open EncryptedSharedPreferences", error);
        }
    }

    @PluginMethod
    public void get(PluginCall call) {
        String key = call.getString("key");
        if (!isAllowedKey(key)) {
            call.reject("key is required");
            return;
        }

        JSObject result = new JSObject();
        if (!securePreferences.contains(key)) {
            result.put("value", JSONObject.NULL);
        } else {
            result.put("value", securePreferences.getString(key, null));
        }
        call.resolve(result);
    }

    @PluginMethod
    public void set(PluginCall call) {
        String key = call.getString("key");
        String value = call.getString("value");
        if (!isAllowedKey(key) || value == null) {
            call.reject("key and value are required");
            return;
        }

        if (!securePreferences.edit().putString(key, value).commit()) {
            call.reject("secure credential write failed");
            return;
        }

        call.resolve();
    }

    @PluginMethod
    public void remove(PluginCall call) {
        String key = call.getString("key");
        if (!isAllowedKey(key)) {
            call.reject("key is required");
            return;
        }

        if (!securePreferences.edit().remove(key).commit()) {
            call.reject("secure credential removal failed");
            return;
        }

        call.resolve();
    }

    /** Atomically reads and removes single-consumption PKCE pending state. */
    @PluginMethod
    public synchronized void take(PluginCall call) {
        String key = call.getString("key");
        if (!PENDING_AUTHORIZATION_KEY.equals(key)) {
            call.reject("pending authorization key is required");
            return;
        }

        String value = securePreferences.getString(key, null);
        if (!securePreferences.edit().remove(key).commit()) {
            call.reject("secure credential consumption failed");
            return;
        }

        JSObject result = new JSObject();
        result.put("value", value == null ? JSONObject.NULL : value);
        call.resolve(result);
    }

    @PluginMethod
    public void clearAuthentication(PluginCall call) {
        // Retain installation ULID for installation binding across local logout.
        boolean cleared = securePreferences.edit()
            .remove(REFRESH_TOKEN_KEY)
            .remove(PENDING_AUTHORIZATION_KEY)
            .commit();
        if (!cleared) {
            call.reject("secure authentication clear failed");
            return;
        }

        call.resolve();
    }

    private static boolean isAllowedKey(String key) {
        return INSTALLATION_ID_KEY.equals(key)
            || REFRESH_TOKEN_KEY.equals(key)
            || PENDING_AUTHORIZATION_KEY.equals(key);
    }

    @NonNull
    private static SharedPreferences createSecurePreferences(Context context)
        throws GeneralSecurityException, IOException {
        MasterKey masterKey = new MasterKey.Builder(context)
            .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
            .build();

        return EncryptedSharedPreferences.create(
            context,
            PREFS_NAME,
            masterKey,
            EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
            EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
        );
    }
}
