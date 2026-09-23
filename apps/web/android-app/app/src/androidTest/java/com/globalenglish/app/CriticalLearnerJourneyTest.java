package com.globalenglish.app;

import static androidx.test.espresso.Espresso.onView;
import static androidx.test.espresso.assertion.ViewAssertions.matches;
import static androidx.test.espresso.matcher.ViewMatchers.isAssignableFrom;
import static androidx.test.espresso.matcher.ViewMatchers.isDisplayed;
import static org.junit.Assert.assertEquals;
import static org.junit.Assert.assertTrue;

import android.os.SystemClock;
import android.webkit.WebView;

import androidx.test.ext.junit.rules.ActivityScenarioRule;
import androidx.test.ext.junit.runners.AndroidJUnit4;
import androidx.test.filters.LargeTest;
import androidx.test.platform.app.InstrumentationRegistry;
import androidx.test.uiautomator.By;
import androidx.test.uiautomator.UiDevice;
import androidx.test.uiautomator.Until;

import org.junit.Before;
import org.junit.Rule;
import org.junit.Test;
import org.junit.runner.RunWith;

import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicReference;

/**
 * Critical Android learner smoke journeys. Run against the documented seeded
 * UX-10A device profile; failures are deliberate when required state is absent.
 */
@RunWith(AndroidJUnit4.class)
@LargeTest
public class CriticalLearnerJourneyTest {
    private static final long TIMEOUT_MS = 15_000L;

    @Rule
    public ActivityScenarioRule<MainActivity> activityRule =
        new ActivityScenarioRule<>(MainActivity.class);

    private UiDevice device;

    @Before
    public void waitForBundledWebApp() {
        device = UiDevice.getInstance(InstrumentationRegistry.getInstrumentation());
        assertEquals(
            "com.globalenglish.app",
            InstrumentationRegistry.getInstrumentation().getTargetContext().getPackageName()
        );
        assertTrue(device.wait(Until.hasObject(By.pkg("com.globalenglish.app").depth(0)), TIMEOUT_MS));
        onView(isAssignableFrom(WebView.class)).check(matches(isDisplayed()));
        assertDom("document.querySelector('main#main-content')!==null", "bundled web app");
    }

    @Test
    public void firstUseJourneyRendersARecoverableStage() {
        setHash("home");
        assertDom(
            "document.querySelector('.ge-onboarding-screen,.learner-home')!==null",
            "seeded first-use or safely resumed Home surface"
        );
    }

    @Test
    public void lessonAndSystemBackReturnToTheLevelList() {
        setHash("learn");
        assertDom("document.querySelector('.level-path')!==null", "level list");
        click(".level-path__item");
        assertDom("document.querySelector('.level-session')!==null", "lesson session");
        device.pressBack();
        assertDom("document.querySelector('.level-path__list')!==null", "level list after Back");
    }

    @Test
    public void quizSelectionSurvivesBackgroundAndResume() {
        openSeededQuestion();
        click(".level-session__answer-list input[type=radio]");
        assertDom(
            "document.querySelector('.level-session__answer-list input:checked')!==null",
            "selected quiz option"
        );
        device.pressHome();
        device.waitForIdle();
        android.content.Intent launch = InstrumentationRegistry.getInstrumentation()
            .getContext()
            .getPackageManager()
            .getLaunchIntentForPackage("com.globalenglish.app");
        assertTrue("Production launch intent exists", launch != null);
        launch.addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK);
        InstrumentationRegistry.getInstrumentation().getContext().startActivity(launch);
        assertTrue(device.wait(Until.hasObject(By.pkg("com.globalenglish.app").depth(0)), TIMEOUT_MS));
        assertDom(
            "document.querySelector('.level-session__answer-list input:checked')!==null",
            "restored quiz option"
        );
    }

    @Test
    public void speakPermissionDenialKeepsTypedLearningAvailable() throws Exception {
        device.executeShellCommand("pm revoke com.globalenglish.app android.permission.RECORD_AUDIO");
        setHash("speak");
        assertDom(
            "document.querySelector('.learner-recording,.conversation-journey')!==null",
            "Speak surface"
        );
        assertDom(
            "document.body.textContent.includes('Type')||document.querySelector('textarea,input[type=text]')!==null",
            "typed fallback"
        );
    }

    @Test
    public void contextualPlansCloseBackToTheOriginatingLevel() {
        setHash("learn");
        click("[id^=level-upgrade-]");
        assertDom("document.querySelector('#subscription-panel')!==null", "plans panel");
        click("#subscription-panel .subscription-panel__continue-free");
        assertDom("location.hash==='#learn'&&document.querySelector('.level-path')!==null", "Learn resume");
    }

    @Test
    public void backFromSecondQuizQuestionRestoresThePriorQuestionAndFocus() {
        openSeededQuestion();
        click(".level-session__answer-list input[type=radio]");
        click(".level-session__assessment-actions .level-session__primary");
        assertDom(
            "document.querySelector('.level-session__question-progress')?.textContent.includes('2')===true",
            "second quiz question"
        );
        device.pressBack();
        assertDom(
            "document.querySelector('.level-session__question-progress')?.textContent.includes('1')===true&&document.activeElement?.id.includes('question-0')===true",
            "restored first question and focus"
        );
    }

    @Test
    public void predictiveGestureBackWalksQuestionSessionAndLearnHistory() {
        assertDom(
            "(()=>{"
                + "const k='__globalEnglishLearnerNavigation';"
                + "const learn={destination:'learn',index:0,previous:null,restoreFocusId:null,version:1};"
                + "const session={destination:'learn',levelNumber:1,partId:'practice',index:1,previous:{destination:'learn'},restoreFocusId:null,version:1};"
                + "const q0={destination:'learn',levelNumber:1,partId:'practice',assessment:{kind:'quick-check',phase:'question',questionIndex:0,totalQuestions:2},index:2,previous:{destination:'learn',levelNumber:1,partId:'practice'},restoreFocusId:null,version:1};"
                + "const q1={destination:'learn',levelNumber:1,partId:'practice',assessment:{kind:'quick-check',phase:'question',questionIndex:1,totalQuestions:2},index:3,previous:{destination:'learn',levelNumber:1,partId:'practice',assessment:{kind:'quick-check',phase:'question',questionIndex:0,totalQuestions:2}},restoreFocusId:null,version:1};"
                + "history.replaceState({[k]:learn},'','#learn');"
                + "history.pushState({[k]:session},'','#learn/levels/1/practice');"
                + "history.pushState({[k]:q0},'','#learn/levels/1/practice/quick-check/question/0/2');"
                + "history.pushState({[k]:q1},'','#learn/levels/1/practice/quick-check/question/1/2');"
                + "dispatchEvent(new PopStateEvent('popstate',{state:history.state}));"
                + "return true;})()",
            "synthetic allowlisted question history"
        );
        assertDom(
            "window.__globalEnglishStartAndroidBackPreview?.()===true",
            "prior-question predictive preview"
        );
        assertDom(
            "document.querySelector('.ge-android-back-preview')!==null",
            "visible predictive preview"
        );
        assertDom(
            "(()=>{window.__globalEnglishCancelAndroidBackPreview?.();return true;})()",
            "cancelled predictive preview"
        );

        assertDom(
            "(()=>{window.__uxrem02RecordingBack=(event)=>event.preventDefault();"
                + "addEventListener('ge:android-back-request',window.__uxrem02RecordingBack);"
                + "return true;})()",
            "recording Back interception"
        );
        gestureBack();
        assertDom(
            "location.hash==='#learn/levels/1/practice/quick-check/question/1/2'",
            "recording Back consumed before navigation"
        );
        assertDom(
            "(()=>{removeEventListener('ge:android-back-request',window.__uxrem02RecordingBack);"
                + "delete window.__uxrem02RecordingBack;return true;})()",
            "recording Back interception removed"
        );

        gestureBack();
        assertDom(
            "location.hash==='#learn/levels/1/practice/quick-check/question/0/2'",
            "prior question after gesture Back"
        );
        gestureBack();
        assertDom(
            "location.hash==='#learn/levels/1/practice'",
            "session after gesture Back"
        );
        gestureBack();
        assertDom("location.hash==='#learn'", "Learn after gesture Back");
    }

    @Test
    public void microphonePermissionBranchesRecoverThroughAppSettings() throws Exception {
        requestMicrophonePermission();
        denyMicrophonePermission();
        assertDom(
            "window.__uxrem02MicrophonePermission==='denied_once'",
            "denied-once microphone branch"
        );

        requestMicrophonePermission();
        denyMicrophonePermission();
        assertDom(
            "window.__uxrem02MicrophonePermission==='denied_permanently'",
            "permanently-denied microphone branch"
        );

        device.executeShellCommand(
            "am start -a android.settings.APPLICATION_DETAILS_SETTINGS -d package:com.globalenglish.app"
        );
        assertTrue(
            device.wait(
                Until.hasObject(By.pkg("com.android.settings").depth(0)),
                TIMEOUT_MS
            )
        );
        device.executeShellCommand("pm grant com.globalenglish.app android.permission.RECORD_AUDIO");
        device.pressBack();
        assertTrue(device.wait(Until.hasObject(By.pkg("com.globalenglish.app").depth(0)), TIMEOUT_MS));
        requestMicrophonePermission();
        assertDom(
            "window.__uxrem02MicrophonePermission==='granted'",
            "granted microphone branch after App Settings recovery"
        );
    }

    private void openSeededQuestion() {
        setHash("learn");
        click(".level-path__item");
        click("[data-level-part] .level-session__tab--practice");
        assertDom(
            "document.querySelectorAll('.level-session__answer-list label').length>1",
            "seeded multi-item quick check"
        );
    }

    private void setHash(String hash) {
        assertDom(
            "(()=>{window.location.hash='#" + hash + "';return true;})()",
            "route #" + hash
        );
        assertDom("window.location.hash==='#" + hash + "'", "active route #" + hash);
        SystemClock.sleep(350L);
    }

    private void click(String selector) {
        assertDom(
            "(()=>{const e=document.querySelector('" + selector + "');if(!e)return false;e.click();return true;})()",
            "control " + selector
        );
        SystemClock.sleep(350L);
    }

    private void gestureBack() {
        int middle = device.getDisplayHeight() / 2;
        assertTrue(
            "Edge gesture completed",
            device.swipe(1, middle, device.getDisplayWidth() * 2 / 3, middle, 40)
        );
        SystemClock.sleep(700L);
    }

    private void requestMicrophonePermission() {
        assertDom(
            "(()=>{window.__uxrem02MicrophonePermission='pending';"
                + "window.Capacitor.Plugins.GlobalEnglishMicrophonePermission"
                + ".requestAfterSpeakingAction()"
                + ".then(({status})=>{window.__uxrem02MicrophonePermission=status;})"
                + ".catch(()=>{window.__uxrem02MicrophonePermission='error';});"
                + "return true;})()",
            "microphone permission request"
        );
    }

    private void denyMicrophonePermission() {
        androidx.test.uiautomator.UiObject2 deny = device.wait(
            Until.findObject(By.res("com.android.permissioncontroller:id/permission_deny_button")),
            1_000L
        );
        if (deny == null) {
            deny = device.wait(
                Until.findObject(By.res(
                    "com.android.permissioncontroller:id/permission_deny_and_dont_ask_again_button"
                )),
                TIMEOUT_MS
            );
        }
        assertTrue("Android microphone deny control exists", deny != null);
        deny.click();
        device.waitForIdle();
    }

    private void assertDom(String expression, String description) {
        long deadline = SystemClock.uptimeMillis() + TIMEOUT_MS;
        do {
            if ("true".equals(evaluate(expression))) {
                return;
            }
            SystemClock.sleep(200L);
        } while (SystemClock.uptimeMillis() < deadline);
        assertTrue("Expected " + description, false);
    }

    private String evaluate(String expression) {
        CountDownLatch latch = new CountDownLatch(1);
        AtomicReference<String> result = new AtomicReference<>("false");
        activityRule.getScenario().onActivity(activity ->
            activity.getBridge().getWebView().evaluateJavascript(
                "(function(){try{return (" + expression + ")===true;}catch(e){return false;}})()",
                value -> {
                    result.set(value);
                    latch.countDown();
                }
            )
        );
        try {
            assertTrue("WebView JavaScript timed out", latch.await(TIMEOUT_MS, TimeUnit.MILLISECONDS));
        } catch (InterruptedException exception) {
            Thread.currentThread().interrupt();
            throw new AssertionError(exception);
        }
        return result.get();
    }
}
