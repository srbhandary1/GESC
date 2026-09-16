package com.globalenglish.demo;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        registerPlugin(GlobalEnglishMicrophonePermissionPlugin.class);
        GlobalEnglishAndroidShell.enableEdgeToEdge(this);
        super.onCreate(savedInstanceState);
        bridge.getWebView().setWebChromeClient(new GlobalEnglishWebChromeClient(bridge));
        GlobalEnglishAndroidShell.install(this, bridge.getWebView());
    }
}
