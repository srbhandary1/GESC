package com.globalenglish.app;

import static org.junit.Assert.assertFalse;
import static org.junit.Assert.assertTrue;

import android.webkit.PermissionRequest;

import org.junit.Test;

public class GlobalEnglishWebChromeClientTest {
    @Test
    public void acceptsOnlyTheBundledHttpsOrigin() {
        assertTrue(GlobalEnglishWebChromeClient.isBundledOrigin("https", "localhost", -1));
        assertTrue(GlobalEnglishWebChromeClient.isBundledOrigin("https", "localhost", 443));
        assertFalse(GlobalEnglishWebChromeClient.isBundledOrigin("http", "localhost", -1));
        assertFalse(GlobalEnglishWebChromeClient.isBundledOrigin("https", "example.test", -1));
        assertFalse(GlobalEnglishWebChromeClient.isBundledOrigin("https", "localhost", 8443));
    }

    @Test
    public void acceptsOnlyOneAudioCaptureResource() {
        assertTrue(GlobalEnglishWebChromeClient.requestsOnlyAudioCapture(
            new String[] {PermissionRequest.RESOURCE_AUDIO_CAPTURE}
        ));
        assertFalse(GlobalEnglishWebChromeClient.requestsOnlyAudioCapture(null));
        assertFalse(GlobalEnglishWebChromeClient.requestsOnlyAudioCapture(new String[] {}));
        assertFalse(GlobalEnglishWebChromeClient.requestsOnlyAudioCapture(
            new String[] {PermissionRequest.RESOURCE_VIDEO_CAPTURE}
        ));
        assertFalse(GlobalEnglishWebChromeClient.requestsOnlyAudioCapture(
            new String[] {
                PermissionRequest.RESOURCE_AUDIO_CAPTURE,
                PermissionRequest.RESOURCE_VIDEO_CAPTURE
            }
        ));
    }

    @Test
    public void keepsHttpAndHttpsInsideChildPaymentWindow() {
        assertTrue(GlobalEnglishWebChromeClient.isAllowedPopupHttpScheme("https"));
        assertTrue(GlobalEnglishWebChromeClient.isAllowedPopupHttpScheme("HTTPS"));
        assertTrue(GlobalEnglishWebChromeClient.isAllowedPopupHttpScheme("http"));
        assertFalse(GlobalEnglishWebChromeClient.isAllowedPopupHttpScheme("upi"));
        assertFalse(GlobalEnglishWebChromeClient.isAllowedPopupHttpScheme("intent"));
        assertFalse(GlobalEnglishWebChromeClient.isAllowedPopupHttpScheme(null));
    }
}
