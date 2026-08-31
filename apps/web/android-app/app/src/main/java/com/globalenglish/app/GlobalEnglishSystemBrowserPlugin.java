package com.globalenglish.app;

import android.content.Intent;
import android.net.Uri;
import android.app.Activity;

import androidx.browser.customtabs.CustomTabsIntent;

import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;

import java.util.Arrays;
import java.util.HashSet;
import java.util.Set;
import java.util.regex.Pattern;

/**
 * Opens the system browser / Chrome Custom Tabs for Passport PKCE (ADR-0002).
 * Must not present an embedded credential form inside the WebView.
 *
 * Allowed origins match apps/web/src/auth/androidPkceEnvironment.ts: HTTPS
 * anywhere, HTTP only for emulator/loopback hosts, or invite-only static IPv4
 * HTTP on port 8080 (D-0257). The authorize URL must stay on that origin.
 */
@CapacitorPlugin(name = "GlobalEnglishSystemBrowser")
public class GlobalEnglishSystemBrowserPlugin extends Plugin {
    private static final Set<String> LOCAL_HTTP_HOSTS = new HashSet<>(
        Arrays.asList("10.0.2.2", "127.0.0.1", "localhost")
    );
    private static final Pattern IPV4_HOST = Pattern.compile(
        "^(?:25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)(?:\\.(?:25[0-5]|2[0-4]\\d|1\\d\\d|[1-9]?\\d)){3}$"
    );

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
                || !isAllowedAuthorizationUrl(uri, configuredOrigin)
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

    static boolean isAllowedAuthorizationUrl(Uri uri, Uri configuredOrigin) {
        if (
            uri == null
                || configuredOrigin == null
                || !isAllowedMobileApiOrigin(configuredOrigin)
        ) {
            return false;
        }

        return uri.getScheme() != null
            && uri.getScheme().equals(configuredOrigin.getScheme())
            && uri.getAuthority() != null
            && uri.getAuthority().equals(configuredOrigin.getAuthority())
            && uri.getUserInfo() == null
            && "/oauth/authorize".equals(uri.getPath())
            && uri.getFragment() == null;
    }

    static boolean isAllowedMobileApiOrigin(Uri origin) {
        if (origin == null || origin.getUserInfo() != null) {
            return false;
        }

        String scheme = origin.getScheme();
        String host = origin.getHost();
        if (scheme == null || host == null) {
            return false;
        }

        if ("https".equals(scheme)) {
            return true;
        }

        if (!"http".equals(scheme)) {
            return false;
        }

        if (LOCAL_HTTP_HOSTS.contains(host)) {
            return true;
        }

        int port = origin.getPort();
        if (port == -1) {
            port = 80;
        }

        return port == 8080 && IPV4_HOST.matcher(host).matches();
    }
}
