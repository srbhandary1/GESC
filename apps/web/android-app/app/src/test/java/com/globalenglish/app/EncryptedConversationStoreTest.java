package com.globalenglish.app;

import org.junit.*;
import org.junit.rules.TemporaryFolder;
import static org.junit.Assert.*;
import java.io.*;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.util.*;
import javax.crypto.*;

public class EncryptedConversationStoreTest {
    @Rule public TemporaryFolder temporary = new TemporaryFolder();
    private final Map<String, SecretKey> keys = new HashMap<>();
    private File root;
    private EncryptedConversationStore store;
    private String first, second;
    @Before public void setup() throws Exception {
        root = temporary.newFolder();
        store = new EncryptedConversationStore(root, new EncryptedConversationStore.Keys() {
            public SecretKey get(String partition) throws Exception {
                if (!keys.containsKey(partition)) { KeyGenerator generator = KeyGenerator.getInstance("AES"); generator.init(256); keys.put(partition,generator.generateKey()); }
                return keys.get(partition);
            }
            public void delete(String partition) { keys.remove(partition); }
        });
        first = EncryptedConversationStore.partition("user:org:member");
        second = EncryptedConversationStore.partition("user:otherorg:othermember");
    }
    @Test public void optInEncryptedReadAndIdempotency() throws Exception {
        assertFalse(store.enabled(first));
        try { store.put(first,"turn","private title","private audio"); fail(); } catch(IOException expected) { }
        store.enable(first,true); store.put(first,"turn","private title","private audio");
        store.put(first,"turn","overwritten","overwritten");
        assertEquals("private audio",store.read(first,"turn"));
        assertEquals("private title",store.list(first).get("turn"));
        String onDisk = new String(Files.readAllBytes(new File(new File(root,first),"turn.gec").toPath()),StandardCharsets.UTF_8);
        assertFalse(onDisk.contains("private"));
        store.enable(first,false); assertEquals("private audio",store.read(first,"turn"));
        try { store.put(first,"another","title","audio"); fail(); } catch(IOException expected) { }
    }
    @Test public void copiedCiphertextCannotCrossAnAccountOrRecord() throws Exception {
        store.enable(first,true); store.put(first,"turn","metadata","audio"); store.enable(second,true);
        assertTrue(store.list(second).isEmpty());
        File source = new File(new File(root,first),"turn.gec");
        Files.copy(source.toPath(),new File(new File(root,second),"turn.gec").toPath());
        try { store.read(second,"turn"); fail(); } catch(javax.crypto.AEADBadTagException expected) { }
        Files.copy(source.toPath(),new File(new File(root,first),"other.gec").toPath());
        try { store.read(first,"other"); fail(); } catch(javax.crypto.AEADBadTagException expected) { }
    }
    @Test public void deletionIsScopedAndAllDeletionDestroysTheKey() throws Exception {
        store.enable(first,true); store.enable(second,true); store.put(first,"turn","title","audio"); store.put(second,"turn","other","other audio");
        store.remove(first,"turn"); assertTrue(store.list(first).isEmpty()); assertEquals("other audio",store.read(second,"turn"));
        store.put(first,"next","title","audio"); store.clear(first); assertFalse(keys.containsKey(first)); assertFalse(store.enabled(first)); assertTrue(store.list(first).isEmpty()); assertEquals("other audio",store.read(second,"turn"));
    }
    @Test public void corruptionAndPathTraversalFailClosed() throws Exception {
        store.enable(first,true); store.put(first,"turn","title","audio");
        File file = new File(new File(root,first),"turn.gec"); byte[] data = Files.readAllBytes(file.toPath()); data[data.length-1] ^= 1; Files.write(file.toPath(),data);
        try { store.read(first,"turn"); fail(); } catch(javax.crypto.AEADBadTagException expected) { }
        try { store.read(first,"../turn"); fail(); } catch(IOException expected) { }
        try { EncryptedConversationStore.partition("../outside"); fail(); } catch(IOException expected) { }
    }
    @Test public void oversizedWritesPreserveExistingHistory() throws Exception {
        store.enable(first,true); store.put(first,"turn","title","audio");
        char[] huge = new char[EncryptedConversationStore.MAX_BODY+1]; Arrays.fill(huge,'x');
        try { store.put(first,"huge","title",new String(huge)); fail(); } catch(IOException expected) { }
        assertEquals("audio",store.read(first,"turn")); assertEquals(1,store.list(first).size());
    }
    @Test public void freshCiphertextDiffersEvenForIdenticalBodies() throws Exception {
        store.enable(first,true); store.put(first,"a","title","audio"); store.put(first,"b","title","audio");
        assertFalse(Arrays.equals(Files.readAllBytes(new File(new File(root,first),"a.gec").toPath()),Files.readAllBytes(new File(new File(root,first),"b.gec").toPath())));
    }
}
