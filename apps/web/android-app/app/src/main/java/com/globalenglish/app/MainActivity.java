package com.globalenglish.app;

import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.webkit.WebView;

import com.getcapacitor.BridgeActivity;

/**
 * Production Capacitor activity for com.globalenglish.app.
 * Registers project-owned secure-credential and system-browser plugins and
 * forwards OAuth deep-link returns into the WebView bridge.
 */
public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(GlobalEnglishSecureCredentialsPlugin.class);
        registerPlugin(GlobalEnglishSystemBrowserPlugin.class);
        super.onCreate(savedInstanceState);
        handleOauthIntent(getIntent());
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        handleOauthIntent(intent);
    }

    private void handleOauthIntent(Intent intent) {
        if (intent == null) {
            return;
        }

        Uri data = intent.getData();
        if (data == null) {
            return;
        }

        String url = data.toString();
        if (!url.startsWith("com.globalenglish.app:/oauth/callback")) {
            return;
        }

        // Deliver to the shared TypeScript PKCE adapter without storing the URL.
        final String safeUrl = url.replace("\\", "\\\\").replace("'", "\\'");
        final WebView webView = this.bridge != null ? this.bridge.getWebView() : null;
        if (webView == null) {
            return;
        }

        webView.post(() -> webView.evaluateJavascript(
            "(function(){try{window.dispatchEvent(new CustomEvent('ge:oauth-callback',{detail:'"
                + safeUrl
                + "'}));}catch(e){}})();",
            null
        ));
    }
}
