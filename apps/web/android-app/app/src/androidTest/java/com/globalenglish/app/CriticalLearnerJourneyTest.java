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
        evaluate("window.location.hash='#" + hash + "';true");
        SystemClock.sleep(350L);
    }

    private void click(String selector) {
        assertDom(
            "(()=>{const e=document.querySelector('" + selector + "');if(!e)return false;e.click();return true;})()",
            "control " + selector
        );
        SystemClock.sleep(350L);
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
