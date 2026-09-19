package com.globalenglish.demo;

import android.os.Bundle;
import android.webkit.WebView;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        setTheme(R.style.AppTheme_NoActionBar);
        registerPlugin(GlobalEnglishMicrophonePermissionPlugin.class);
        GlobalEnglishAndroidShell.enableEdgeToEdge(this);
        super.onCreate(savedInstanceState);
        attachShellIfReady();
    }

    @Override
    public void onStart() {
        super.onStart();
        attachShellIfReady();
    }

    @Override
    public void onResume() {
        super.onResume();
        attachShellIfReady();
    }

    private void attachShellIfReady() {
        if (bridge == null) {
            return;
        }

        WebView webView = bridge.getWebView();
        if (webView == null) {
            return;
        }

        if (!(webView.getWebChromeClient() instanceof GlobalEnglishWebChromeClient)) {
            webView.setWebChromeClient(new GlobalEnglishWebChromeClient(bridge));
        }

        GlobalEnglishAndroidShell.install(this, webView);
    }
}
