package com.globalenglish.demo;

import android.Manifest;
import android.app.Activity;
import android.content.pm.PackageManager;

import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import com.getcapacitor.JSObject;
import com.getcapacitor.PermissionState;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;

/** Contextual Android microphone permission boundary for the demo wrapper. */
@CapacitorPlugin(
    name = "GlobalEnglishMicrophonePermission",
    permissions = {
        @Permission(alias = "microphone", strings = {Manifest.permission.RECORD_AUDIO})
    }
)
public class GlobalEnglishMicrophonePermissionPlugin extends Plugin {
    @PluginMethod
    public void requestAfterSpeakingAction(PluginCall call) {
        if (getPermissionState("microphone") == PermissionState.GRANTED) {
            resolve(call, "granted");
            return;
        }

        requestPermissionForAlias(
            "microphone",
            call,
            "microphonePermissionCallback"
        );
    }

    @PermissionCallback
    private void microphonePermissionCallback(PluginCall call) {
        Activity activity = getActivity();
        if (activity == null) {
            call.reject("microphone permission activity is unavailable");
            return;
        }

        boolean granted = ContextCompat.checkSelfPermission(
            activity,
            Manifest.permission.RECORD_AUDIO
        ) == PackageManager.PERMISSION_GRANTED;
        boolean shouldShowRationale = !granted
            && ActivityCompat.shouldShowRequestPermissionRationale(
                activity,
                Manifest.permission.RECORD_AUDIO
            );
        resolve(call, exposedStatus(granted, shouldShowRationale));
    }

    static String exposedStatus(boolean granted, boolean shouldShowRationale) {
        if (granted) {
            return "granted";
        }

        return shouldShowRationale ? "denied_once" : "denied_permanently";
    }

    private static void resolve(PluginCall call, String status) {
        JSObject result = new JSObject();
        result.put("status", status);
        call.resolve(result);
    }
}
