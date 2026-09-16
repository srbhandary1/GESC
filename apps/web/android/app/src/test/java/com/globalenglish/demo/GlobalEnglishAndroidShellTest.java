package com.globalenglish.demo;

import static org.junit.Assert.assertFalse;
import static org.junit.Assert.assertTrue;

import org.junit.Test;

public class GlobalEnglishAndroidShellTest {
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
