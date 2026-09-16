package com.globalenglish.demo;

import android.os.Bundle;
import android.webkit.WebView;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(GlobalEnglishMicrophonePermissionPlugin.class);
        GlobalEnglishAndroidShell.enableEdgeToEdge(this);
        super.onCreate(savedInstanceState);
        if (bridge != null && bridge.getWebView() != null) {
            WebView webView = bridge.getWebView();
            webView.setWebChromeClient(new GlobalEnglishWebChromeClient(bridge));
            GlobalEnglishAndroidShell.install(this, webView);
        }
    }
}
