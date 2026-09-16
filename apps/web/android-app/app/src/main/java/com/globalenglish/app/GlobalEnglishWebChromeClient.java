package com.globalenglish.app;

import android.Manifest;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.webkit.PermissionRequest;

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
