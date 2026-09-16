package com.globalenglish.demo;

import android.webkit.WebView;

import androidx.activity.BackEventCompat;
import androidx.activity.EdgeToEdge;
import androidx.activity.OnBackPressedCallback;
import androidx.annotation.NonNull;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;

import com.getcapacitor.BridgeActivity;

/** Android-owned edge-to-edge, IME and predictive-Back integration. */
final class GlobalEnglishAndroidShell {
    static final long BACK_DISPATCH_TIMEOUT_MS = 1500L;

    private GlobalEnglishAndroidShell() {}

    static void enableEdgeToEdge(BridgeActivity activity) {
        EdgeToEdge.enable(activity);
    }

    static void install(BridgeActivity activity, WebView webView) {
        installBack(activity, webView);
        installInsets(webView);
    }

    private static void installBack(BridgeActivity activity, WebView webView) {
        OnBackPressedCallback callback = new OnBackPressedCallback(true) {
            private final BackDispatchGuard dispatchGuard = new BackDispatchGuard();

            @Override
            public void handleOnBackStarted(@NonNull BackEventCompat backEvent) {
                evaluate(webView, "window.__globalEnglishStartAndroidBackPreview?.()");
            }

            @Override
            public void handleOnBackProgressed(@NonNull BackEventCompat backEvent) {
                evaluate(
                    webView,
                    "window.__globalEnglishProgressAndroidBackPreview?.("
                        + backEvent.getProgress()
                        + ")"
                );
            }

            @Override
            public void handleOnBackCancelled() {
                evaluate(webView, "window.__globalEnglishCancelAndroidBackPreview?.()");
            }

            @Override
            public void handleOnBackPressed() {
                int dispatchToken = dispatchGuard.begin();
                if (dispatchToken < 0) {
                    return;
                }
                Runnable timeout = () -> {
                    if (!dispatchGuard.finish(dispatchToken)) {
                        return;
                    }
                    evaluate(webView, "window.__globalEnglishCancelAndroidBackPreview?.()");
                    fallThroughToDispatcher();
                };
                webView.postDelayed(timeout, BACK_DISPATCH_TIMEOUT_MS);
                webView.evaluateJavascript(
                    "(function(){try{return window.__globalEnglishHandleAndroidBack?.()===true;}catch(e){return false;}})()",
                    handled -> {
                        if (!dispatchGuard.finish(dispatchToken)) {
                            return;
                        }
                        webView.removeCallbacks(timeout);
                        evaluate(webView, "window.__globalEnglishCancelAndroidBackPreview?.()");
                        if ("true".equals(handled)) {
                            return;
                        }
                        fallThroughToDispatcher();
                    }
                );
            }

            private void fallThroughToDispatcher() {
                setEnabled(false);
                activity.getOnBackPressedDispatcher().onBackPressed();
                setEnabled(true);
            }
        };

        activity.getOnBackPressedDispatcher().addCallback(activity, callback);
    }

    private static void installInsets(WebView webView) {
        ViewCompat.setOnApplyWindowInsetsListener(webView, (view, windowInsets) -> {
            Insets safe = windowInsets.getInsets(
                WindowInsetsCompat.Type.systemBars()
                    | WindowInsetsCompat.Type.displayCutout()
            );
            Insets ime = windowInsets.getInsets(WindowInsetsCompat.Type.ime());
            float density = view.getResources().getDisplayMetrics().density;
            String script = "(function(){var s=document.documentElement.style;"
                + cssProperty("--ge-native-safe-area-top", safe.top, density)
                + cssProperty("--ge-native-safe-area-right", safe.right, density)
                + cssProperty("--ge-native-safe-area-bottom", safe.bottom, density)
                + cssProperty("--ge-native-safe-area-left", safe.left, density)
                + cssProperty(
                    "--ge-native-ime-inset-bottom",
                    windowInsets.isVisible(WindowInsetsCompat.Type.ime()) ? ime.bottom : 0,
                    density
                )
                + "})();";
            webView.evaluateJavascript(script, null);
            return windowInsets;
        });
        ViewCompat.requestApplyInsets(webView);
    }

    private static String cssProperty(String name, int pixels, float density) {
        float cssPixels = density <= 0f ? pixels : pixels / density;
        return "s.setProperty('" + name + "','" + cssPixels + "px');";
    }

    private static void evaluate(WebView webView, String expression) {
        webView.evaluateJavascript(
            "(function(){try{" + expression + ";}catch(e){}})()",
            null
        );
    }

    static final class BackDispatchGuard {
        private int generation = 0;
        private boolean pending = false;

        synchronized int begin() {
            if (pending) {
                return -1;
            }
            pending = true;
            generation += 1;
            return generation;
        }

        synchronized boolean finish(int dispatchToken) {
            if (!pending || generation != dispatchToken) {
                return false;
            }
            pending = false;
            return true;
        }
    }
}
