package com.globalenglish.app;

import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyProperties;
import com.getcapacitor.*;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.io.File;
import java.security.KeyStore;
import java.util.concurrent.Executors;
import java.util.concurrent.ExecutorService;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;

@CapacitorPlugin(name = "GlobalEnglishConversationHistory")
public class GlobalEnglishConversationHistoryPlugin extends Plugin {
    private final ExecutorService worker = Executors.newSingleThreadExecutor();
    private EncryptedConversationStore store;
    private String activePartition;
    private long generation;
    @Override public void load() {
        store = new EncryptedConversationStore(new File(getContext().getNoBackupFilesDir(), "conversation-history"), new EncryptedConversationStore.Keys() {
            private KeyStore keyStore() throws Exception { KeyStore keys = KeyStore.getInstance("AndroidKeyStore"); keys.load(null); return keys; }
            public SecretKey get(String partition) throws Exception {
                String alias = "ge.conversation-history." + partition;
                KeyStore keys = keyStore();
                if (!keys.containsAlias(alias)) {
                    KeyGenerator generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore");
                    generator.init(new KeyGenParameterSpec.Builder(alias, KeyProperties.PURPOSE_ENCRYPT | KeyProperties.PURPOSE_DECRYPT)
                        .setBlockModes(KeyProperties.BLOCK_MODE_GCM).setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE).setKeySize(256).build());
                    generator.generateKey();
                }
                return (SecretKey) keys.getKey(alias, null);
            }
            public void delete(String partition) throws Exception { keyStore().deleteEntry("ge.conversation-history." + partition); }
        });
    }
    @PluginMethod public synchronized void activate(PluginCall call) {
        try { activePartition = EncryptedConversationStore.partition(call.getString("binding")); generation++; call.resolve(); }
        catch (Exception failure) { activePartition = null; generation++; call.reject("history_unavailable"); }
    }
    @PluginMethod public synchronized void deactivate(PluginCall call) { activePartition = null; generation++; call.resolve(); }
    private interface Operation { JSObject run(String partition) throws Exception; }
    private synchronized void execute(PluginCall call, Operation operation) {
        final String partition = activePartition; final long expected = generation;
        if (partition == null) { call.reject("history_locked"); return; }
        worker.execute(() -> {
            synchronized (this) {
                if (expected != generation || !partition.equals(activePartition)) { call.reject("history_locked"); return; }
                try { call.resolve(operation.run(partition)); }
                catch (Exception failure) { call.reject("history_unavailable"); } // Never log contents or exception messages.
            }
        });
    }
    @PluginMethod public void status(PluginCall call) { execute(call, partition -> new JSObject().put("enabled",store.enabled(partition))); }
    @PluginMethod public void setEnabled(PluginCall call) { execute(call, partition -> { store.enable(partition,Boolean.TRUE.equals(call.getBoolean("enabled"))); return new JSObject(); }); }
    @PluginMethod public void put(PluginCall call) { execute(call, partition -> { store.put(partition,call.getString("id"),call.getString("metadata"),call.getString("body")); return new JSObject(); }); }
    @PluginMethod public void read(PluginCall call) { execute(call, partition -> new JSObject().put("body",store.read(partition,call.getString("id")))); }
    @PluginMethod public void list(PluginCall call) { execute(call, partition -> { JSArray items = new JSArray(); store.list(partition).forEach((id,metadata) -> items.put(new JSObject().put("id",id).put("metadata",metadata))); return new JSObject().put("items",items); }); }
    @PluginMethod public void remove(PluginCall call) { execute(call, partition -> { store.remove(partition,call.getString("id")); return new JSObject(); }); }
    @PluginMethod public void clear(PluginCall call) { execute(call, partition -> { store.clear(partition); return new JSObject(); }); }
    @Override protected void handleOnDestroy() { synchronized(this) { activePartition = null; generation++; } worker.shutdown(); }
}
