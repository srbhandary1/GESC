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
        if (key == null || key.isEmpty()) {
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
        if (key == null || key.isEmpty() || value == null) {
            call.reject("key and value are required");
            return;
        }

        securePreferences.edit().putString(key, value).apply();
        call.resolve();
    }

    @PluginMethod
    public void remove(PluginCall call) {
        String key = call.getString("key");
        if (key == null || key.isEmpty()) {
            call.reject("key is required");
            return;
        }

        securePreferences.edit().remove(key).apply();
        call.resolve();
    }

    @PluginMethod
    public void clearAuthentication(PluginCall call) {
        // Retain installation ULID for installation binding across local logout.
        securePreferences.edit()
            .remove(REFRESH_TOKEN_KEY)
            .remove(PENDING_AUTHORIZATION_KEY)
            .apply();
        call.resolve();
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
