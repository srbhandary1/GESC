package com.globalenglish.app;

import java.io.*;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.util.*;
import javax.crypto.Cipher;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;

/** App-private archive. No plaintext files, logs, credentials, or cloud transport. */
public final class EncryptedConversationStore {
    public interface Keys {
        SecretKey get(String partition) throws Exception;
        void delete(String partition) throws Exception;
    }
    public static final String NOTICE_VERSION = "android-history-2026-09-26.1";
    public static final int MAX_BODY = 4 * 1024 * 1024;
    public static final long MAX_TOTAL = 256L * 1024 * 1024;
    private final File root;
    private final Keys keys;
    public EncryptedConversationStore(File root, Keys keys) { this.root = root; this.keys = keys; }
    public static String partition(String value) throws Exception {
        if (value == null || !value.matches("[a-zA-Z0-9_-]{1,80}:[a-zA-Z0-9_-]{1,80}:[a-zA-Z0-9_-]{1,80}")) throw new IOException("invalid_partition");
        byte[] hash = MessageDigest.getInstance("SHA-256").digest(value.getBytes(StandardCharsets.UTF_8));
        StringBuilder out = new StringBuilder(); for (byte b : hash) out.append(String.format(Locale.ROOT, "%02x", b & 255)); return out.toString();
    }
    private File directory(String partition) throws IOException {
        if (!partition.matches("[a-f0-9]{64}")) throw new IOException("invalid_partition");
        File dir = new File(root, partition);
        if (!dir.isDirectory() && !dir.mkdirs()) throw new IOException("archive_unavailable");
        return dir;
    }
    private File file(String partition, String id) throws IOException {
        if (id == null || !id.matches("[a-zA-Z0-9_-]{1,90}")) throw new IOException("invalid_record");
        return new File(directory(partition), id + ".gec");
    }
    private byte[] seal(String partition, String id, byte[] data) throws Exception {
        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.ENCRYPT_MODE, keys.get(partition));
        cipher.updateAAD((partition + ":" + id).getBytes(StandardCharsets.UTF_8));
        byte[] body = cipher.doFinal(data), iv = cipher.getIV();
        if (iv.length != 12) throw new IOException("invalid_nonce");
        ByteArrayOutputStream out = new ByteArrayOutputStream(); out.write(iv); out.write(body); return out.toByteArray();
    }
    private String open(String partition, String id, byte[] data) throws Exception {
        if (data.length < 28) throw new IOException("invalid_archive");
        Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
        cipher.init(Cipher.DECRYPT_MODE, keys.get(partition), new GCMParameterSpec(128, data, 0, 12));
        cipher.updateAAD((partition + ":" + id).getBytes(StandardCharsets.UTF_8));
        return new String(cipher.doFinal(data, 12, data.length - 12), StandardCharsets.UTF_8);
    }
    public synchronized boolean enabled(String partition) throws Exception {
        File setting = file(partition, "settings");
        return setting.exists() && ("enabled:" + NOTICE_VERSION).equals(read(partition, "settings"));
    }
    public synchronized void enable(String partition, boolean enabled) throws Exception {
        write(partition, "settings", "{}", enabled ? "enabled:" + NOTICE_VERSION : "disabled", true);
    }
    public synchronized void put(String partition, String id, String metadata, String body) throws Exception {
        if ("settings".equals(id) || !enabled(partition)) throw new IOException("history_not_enabled");
        write(partition, id, metadata, body, false);
    }
    private void write(String partition, String id, String metadata, String body, boolean replace) throws Exception {
        File target = file(partition, id);
        if (!replace && target.exists()) return; // Stable turn IDs make replay idempotent.
        byte[] raw = body.getBytes(StandardCharsets.UTF_8), meta = metadata.getBytes(StandardCharsets.UTF_8);
        if (raw.length > MAX_BODY || meta.length > 8192) throw new IOException("history_full");
        long size = 0; int count = 0;
        File[] files = directory(partition).listFiles();
        if (files == null) throw new IOException("archive_unavailable");
        for (File entry : files) { size += entry.length(); count++; }
        if (!replace && (size + raw.length + meta.length + 80 > MAX_TOTAL || count >= 2000)) throw new IOException("history_full");
        byte[] sealedMeta = seal(partition, id + ":metadata", meta), sealedBody = seal(partition, id + ":body", raw);
        File temp = new File(target.getPath() + ".pending");
        try (FileOutputStream output = new FileOutputStream(temp); DataOutputStream data = new DataOutputStream(output)) {
            data.writeInt(1); data.writeInt(sealedMeta.length); data.write(sealedMeta); data.writeInt(sealedBody.length); data.write(sealedBody); data.flush(); output.getFD().sync();
        } catch (Exception failure) { temp.delete(); throw failure; }
        if (!temp.renameTo(target)) { temp.delete(); throw new IOException("archive_write_failed"); }
    }
    private byte[] block(DataInputStream in, int maximum) throws IOException {
        int length = in.readInt(); if (length < 28 || length > maximum + 28) throw new IOException("invalid_archive");
        byte[] data = new byte[length]; in.readFully(data); return data;
    }
    public synchronized String read(String partition, String id) throws Exception {
        try (DataInputStream input = new DataInputStream(new FileInputStream(file(partition, id)))) {
            if (input.readInt() != 1) throw new IOException("invalid_archive");
            block(input, 8192);
            return open(partition, id + ":body", block(input, MAX_BODY));
        }
    }
    public synchronized Map<String, String> list(String partition) throws Exception {
        Map<String, String> items = new LinkedHashMap<>(); File[] files = directory(partition).listFiles();
        if (files == null) throw new IOException("archive_unavailable");
        Arrays.sort(files, (a,b) -> Long.compare(b.lastModified(), a.lastModified()));
        for (File file : files) {
            if (!file.getName().endsWith(".gec") || file.getName().equals("settings.gec")) continue;
            String id = file.getName().substring(0, file.getName().length() - 4);
            try (DataInputStream input = new DataInputStream(new FileInputStream(file))) {
                if (input.readInt() != 1) throw new IOException("invalid_archive");
                items.put(id, open(partition, id + ":metadata", block(input, 8192)));
            }
        }
        return items;
    }
    public synchronized void remove(String partition, String id) throws Exception {
        if ("settings".equals(id)) throw new IOException("invalid_record");
        File target = file(partition,id); if (target.exists() && !target.delete()) throw new IOException("archive_delete_failed");
    }
    public synchronized void clear(String partition) throws Exception {
        keys.delete(partition); // Cryptographic erasure before unlinking files.
        File[] files = directory(partition).listFiles();
        if (files == null) throw new IOException("archive_unavailable");
        for (File file : files) if (!file.delete()) throw new IOException("archive_delete_failed");
    }
}
