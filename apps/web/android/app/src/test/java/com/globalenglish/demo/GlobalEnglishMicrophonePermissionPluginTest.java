package com.globalenglish.demo;

import static org.junit.Assert.assertEquals;

import org.junit.Test;

public class GlobalEnglishMicrophonePermissionPluginTest {
    @Test
    public void exposesGranted() {
        assertEquals(
            "granted",
            GlobalEnglishMicrophonePermissionPlugin.exposedStatus(true, false)
        );
    }

    @Test
    public void exposesDeniedOnceWhenRationaleIsAvailable() {
        assertEquals(
            "denied_once",
            GlobalEnglishMicrophonePermissionPlugin.exposedStatus(false, true)
        );
    }

    @Test
    public void exposesDeniedPermanently() {
        assertEquals(
            "denied_permanently",
            GlobalEnglishMicrophonePermissionPlugin.exposedStatus(false, false)
        );
    }
}
