package com.globalenglish.app;

import static org.junit.Assert.assertFalse;
import static org.junit.Assert.assertTrue;

import org.junit.Test;

public class GlobalEnglishAndroidShellTest {
    @Test
    public void enlargedTextThresholdTracksAndroidFontScale() {
        assertFalse(GlobalEnglishAndroidShell.isEnlargedText(1.0f));
        assertFalse(GlobalEnglishAndroidShell.isEnlargedText(1.29f));
        assertTrue(GlobalEnglishAndroidShell.isEnlargedText(1.3f));
        assertTrue(GlobalEnglishAndroidShell.isEnlargedText(2.0f));
    }

    @Test
    public void timeoutReleasesPendingDispatchAndIgnoresLateCallback() {
        GlobalEnglishAndroidShell.BackDispatchGuard guard =
            new GlobalEnglishAndroidShell.BackDispatchGuard();

        int timedOutDispatch = guard.begin();
        assertTrue(timedOutDispatch > 0);
        assertTrue(guard.finish(timedOutDispatch));
        assertFalse(guard.finish(timedOutDispatch));

        int nextDispatch = guard.begin();
        assertTrue(nextDispatch > timedOutDispatch);
        assertTrue(guard.finish(nextDispatch));
    }
}
