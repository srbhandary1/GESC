package com.globalenglish.app;

import android.Manifest;
import android.app.Activity;
import android.app.Dialog;
import android.content.ActivityNotFoundException;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Message;
import android.view.ViewGroup;
import android.webkit.PermissionRequest;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;

import androidx.core.content.ContextCompat;

import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeWebChromeClient;

/** Grants bundled-origin audio capture only after the contextual runtime grant. */
public final class GlobalEnglishWebChromeClient extends BridgeWebChromeClient {
    private final Bridge bridge;

    public GlobalEnglishWebChromeClient(Bridge bridge) {
        super(bridge);
        this.bridge = bridge;
    }

    @Override
    public boolean onCreateWindow(
        WebView view,
        boolean isDialog,
        boolean isUserGesture,
        Message resultMsg
    ) {
        if (resultMsg == null || !(resultMsg.obj instanceof WebView.WebViewTransport)) {
            return false;
        }

        Activity activity = bridge.getActivity();
        if (activity == null || activity.isFinishing()) {
            return false;
        }

        final Dialog popupDialog = new Dialog(
            activity,
            android.R.style.Theme_DeviceDefault_NoActionBar
        );
        final WebView childWebView = new WebView(activity);
        WebSettings settings = childWebView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setJavaScriptCanOpenWindowsAutomatically(true);
        settings.setSupportMultipleWindows(false);

        childWebView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onCloseWindow(WebView window) {
                popupDialog.dismiss();
            }
        });

        childWebView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(
                WebView child,
                WebResourceRequest request
            ) {
                Uri uri = request != null ? request.getUrl() : null;
                if (uri == null) {
                    return false;
                }
                if (isAllowedPopupHttpScheme(uri.getScheme())) {
                    return false;
                }
                try {
                    activity.startActivity(new Intent(Intent.ACTION_VIEW, uri));
                } catch (ActivityNotFoundException ignored) {
                    // Ignore unsupported external schemes inside payment popup.
                }
                return true;
            }
        });

        popupDialog.setContentView(childWebView);
        popupDialog.setCancelable(true);
        popupDialog.setOnDismissListener(dialog -> {
            childWebView.stopLoading();
            childWebView.destroy();
        });
        popupDialog.show();
        if (popupDialog.getWindow() != null) {
            popupDialog.getWindow().setLayout(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
            );
        }

        WebView.WebViewTransport transport = (WebView.WebViewTransport) resultMsg.obj;
        transport.setWebView(childWebView);
        resultMsg.sendToTarget();
        return true;
    }

    @Override
    public void onPermissionRequest(PermissionRequest request) {
        if (
            isBundledOrigin(request.getOrigin())
                && requestsOnlyAudioCapture(request.getResources())
                && ContextCompat.checkSelfPermission(
                    getContext(),
                    Manifest.permission.RECORD_AUDIO
                ) == PackageManager.PERMISSION_GRANTED
        ) {
            request.grant(new String[] {PermissionRequest.RESOURCE_AUDIO_CAPTURE});
            return;
        }

        request.deny();
    }

    private android.content.Context getContext() {
        return bridge.getContext();
    }

    static boolean isAllowedPopupHttpScheme(String scheme) {
        return "https".equalsIgnoreCase(scheme) || "http".equalsIgnoreCase(scheme);
    }

    static boolean isBundledOrigin(Uri origin) {
        return origin != null
            && isBundledOrigin(origin.getScheme(), origin.getHost(), origin.getPort());
    }

    static boolean isBundledOrigin(String scheme, String host, int port) {
        return "https".equalsIgnoreCase(scheme)
            && "localhost".equalsIgnoreCase(host)
            && (port == -1 || port == 443);
    }

    static boolean requestsOnlyAudioCapture(String[] resources) {
        return resources != null
            && resources.length == 1
            && PermissionRequest.RESOURCE_AUDIO_CAPTURE.equals(resources[0]);
    }
}
