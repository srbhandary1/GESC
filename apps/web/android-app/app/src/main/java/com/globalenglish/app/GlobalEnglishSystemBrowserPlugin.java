package com.globalenglish.app;

import android.content.Intent;
import android.net.Uri;

import androidx.browser.customtabs.CustomTabsIntent;

import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

/**
 * Opens origin-pinned Chrome Custom Tabs for Passport PKCE and first-party
 * Google/Facebook authorization (ADR-0002 / ADR-0034). Must not present an
 * embedded IdP form inside the WebView. Password sign-in stays in-app.
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
        String path = uri.getPath();
        Activity activity = getActivity();
        if (
            activity == null
                || BuildConfig.MOBILE_API_ORIGIN.isEmpty()
                || !"https".equals(uri.getScheme())
                || !uri.getScheme().equals(configuredOrigin.getScheme())
                || uri.getAuthority() == null
                || !uri.getAuthority().equals(configuredOrigin.getAuthority())
                || uri.getUserInfo() != null
                || !isAllowedAuthorizationPath(path)
                || uri.getFragment() != null
        ) {
            call.reject("authorization url is not allowed");
            return;
        }

        try {
            CustomTabsIntent customTabsIntent = new CustomTabsIntent.Builder().build();
            customTabsIntent.intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            customTabsIntent.launchUrl(getContext(), uri);
            call.resolve();
        } catch (Exception customTabsError) {
            Intent fallback = new Intent(Intent.ACTION_VIEW, uri);
            fallback.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            getContext().startActivity(fallback);
            call.resolve();
        }
    }

    private static boolean isAllowedAuthorizationPath(String path) {
        return "/oauth/authorize".equals(path)
            || "/oauth/social/google/authorize".equals(path)
            || "/oauth/social/facebook/authorize".equals(path);
    }
}
