package com.globalenglish.app;

import android.Manifest;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.webkit.WebView;

import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import com.getcapacitor.BridgeActivity;

import org.json.JSONObject;

/**
 * Production Capacitor activity for com.globalenglish.app.
 * Registers project-owned secure-credential and system-browser plugins and
 * forwards OAuth deep-link returns into the WebView bridge.
 */
public class MainActivity extends BridgeActivity {
    private static final int REQUEST_RECORD_AUDIO = 7101;
    private static final long[] OAUTH_DISPATCH_DELAYS_MS = new long[] {0L, 500L, 1500L};

    private String pendingOauthCallbackUrl;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(GlobalEnglishSecureCredentialsPlugin.class);
        registerPlugin(GlobalEnglishSystemBrowserPlugin.class);
        super.onCreate(savedInstanceState);
        requestMicrophonePermission();
        handleOauthIntent(getIntent());
    }

    @Override
    public void onResume() {
        super.onResume();
        // Bridge/WebView or the JS PKCE listener may not be ready on first intent.
        schedulePendingOauthCallbackDelivery();
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleOauthIntent(intent);
    }

    private void requestMicrophonePermission() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) {
            return;
        }

        if (ContextCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO)
                != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(
                    this,
                    new String[] {Manifest.permission.RECORD_AUDIO},
                    REQUEST_RECORD_AUDIO);
        }
    }

    private void handleOauthIntent(Intent intent) {
        if (intent == null) {
            return;
        }

        Uri data = intent.getData();
        if (data == null) {
            return;
        }

        if (
            !"com.globalenglish.app".equals(data.getScheme())
                || data.getAuthority() != null
                || !"/oauth/callback".equals(data.getPath())
                || data.getFragment() != null
        ) {
            return;
        }

        String url = data.toString();
        pendingOauthCallbackUrl = url;
        schedulePendingOauthCallbackDelivery();
    }

    private void schedulePendingOauthCallbackDelivery() {
        final String url = pendingOauthCallbackUrl;
        if (url == null) {
            return;
        }

        final WebView webView = this.bridge != null ? this.bridge.getWebView() : null;
        if (webView == null) {
            return;
        }

        final String safeUrl = JSONObject.quote(url);
        for (long delayMs : OAUTH_DISPATCH_DELAYS_MS) {
            final long scheduledDelay = delayMs;
            Runnable dispatch = () -> {
                webView.evaluateJavascript(
                    "(function(){try{window.__globalEnglishPendingOauthCallback="
                        + safeUrl
                        + ";window.dispatchEvent(new CustomEvent('ge:oauth-callback',{detail:"
                        + safeUrl
                        + "}));}catch(e){}})();",
                    null
                );
                if (
                    scheduledDelay == OAUTH_DISPATCH_DELAYS_MS[OAUTH_DISPATCH_DELAYS_MS.length - 1]
                        && url.equals(pendingOauthCallbackUrl)
                ) {
                    pendingOauthCallbackUrl = null;
                }
            };

            if (scheduledDelay <= 0L) {
                webView.post(dispatch);
            } else {
                webView.postDelayed(dispatch, scheduledDelay);
            }
        }
    }
}
