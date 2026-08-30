package com.globalenglish.app;

import android.content.Intent;
import android.net.Uri;
import android.app.Activity;

import androidx.browser.customtabs.CustomTabsIntent;

import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/**
 * Opens the system browser / Chrome Custom Tabs for Passport PKCE (ADR-0002).
 * Must not present an embedded credential form inside the WebView.
 */
@CapacitorPlugin(name = "GlobalEnglishSystemBrowser")
public class GlobalEnglishSystemBrowserPlugin extends Plugin {
    @PluginMethod
    public void open(PluginCall call) {
        String url = call.getString("url");
        if (url == null || url.isEmpty()) {
            call.reject("url is required");
            return;
        }

        Uri uri = Uri.parse(url);
        Uri configuredOrigin = Uri.parse(BuildConfig.MOBILE_API_ORIGIN);
        Activity activity = getActivity();
        if (
            activity == null
                || BuildConfig.MOBILE_API_ORIGIN.isEmpty()
                || !"https".equals(uri.getScheme())
                || !uri.getScheme().equals(configuredOrigin.getScheme())
                || uri.getAuthority() == null
                || !uri.getAuthority().equals(configuredOrigin.getAuthority())
                || uri.getUserInfo() != null
                || !"/oauth/authorize".equals(uri.getPath())
                || uri.getFragment() != null
        ) {
            call.reject("authorization url is not allowed");
            return;
        }

        try {
            CustomTabsIntent customTabsIntent = new CustomTabsIntent.Builder().build();
            customTabsIntent.launchUrl(activity, uri);
            call.resolve();
        } catch (Exception customTabsError) {
            Intent fallback = new Intent(Intent.ACTION_VIEW, uri);
            activity.startActivity(fallback);
            call.resolve();
        }
    }
}
