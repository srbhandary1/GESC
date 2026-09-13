import { describe, expect, it, vi } from 'vitest'
import {
  KeystoreSecureCredentialStore,
  SECURE_CREDENTIAL_STORAGE_KEYS,
} from './KeystoreSecureCredentialStore'
import type { GlobalEnglishSecureCredentialsPlugin } from './nativeSecureCredentialPlugin'

class MemoryPlugin implements GlobalEnglishSecureCredentialsPlugin {
  readonly values = new Map<string, string>()

  async get(options: { key: string }): Promise<{ value: string | null }> {
    return { value: this.values.get(options.key) ?? null }
  }

  async set(options: { key: string; value: string }): Promise<void> {
    this.values.set(options.key, options.value)
  }

  async remove(options: { key: string }): Promise<void> {
    this.values.delete(options.key)
  }

  async take(options: {
    key: string
  }): Promise<{ value: string | null }> {
    const value = this.values.get(options.key) ?? null
    this.values.delete(options.key)

    return { value }
  }

  async clearAuthentication(): Promise<void> {
    this.values.delete(SECURE_CREDENTIAL_STORAGE_KEYS.refreshToken)
    this.values.delete(SECURE_CREDENTIAL_STORAGE_KEYS.pendingAuthorization)
  }
}

describe('KeystoreSecureCredentialStore', () => {
  it('persists installation ULID and refresh token only through the secure plugin', async () => {
    const plugin = new MemoryPlugin()
    const takeSpy = vi.spyOn(plugin, 'take')
    const removeSpy = vi.spyOn(plugin, 'remove')
    const store = new KeystoreSecureCredentialStore({
      createUlid: () => '01JTESTINSTALLATION000000001',
      plugin,
    })

    const installationId = await store.getOrCreateInstallationId()
    expect(installationId).toBe('01JTESTINSTALLATION000000001')
    expect(
      plugin.values.get(SECURE_CREDENTIAL_STORAGE_KEYS.installationId),
    ).toBe('01JTESTINSTALLATION000000001')

    await store.writeRefreshToken('refresh-secret')
    expect(await store.readRefreshToken()).toBe('refresh-secret')

    await store.writePendingAuthorization({
      state: 'state-1',
      verifier: 'verifier-1',
    })
    await expect(store.takePendingAuthorization()).resolves.toEqual({
      state: 'state-1',
      verifier: 'verifier-1',
    })
    await expect(store.takePendingAuthorization()).resolves.toBeNull()
    expect(takeSpy).toHaveBeenCalledTimes(2)
    expect(removeSpy).not.toHaveBeenCalled()

    await store.clearAuthentication()
    expect(await store.readRefreshToken()).toBeNull()
    expect(
      plugin.values.get(SECURE_CREDENTIAL_STORAGE_KEYS.installationId),
    ).toBe('01JTESTINSTALLATION000000001')
    expect(JSON.stringify([...plugin.values.entries()])).not.toContain(
      'refresh-secret',
    )
  })

  it('writes credentials only through the reviewed secure plugin keys', async () => {
    const plugin = new MemoryPlugin()
    const setSpy = vi.spyOn(plugin, 'set')
    const store = new KeystoreSecureCredentialStore({
      createUlid: () => '01JTESTINSTALLATION000000002',
      plugin,
    })

    await store.writeRefreshToken('only-via-secure-plugin')
    expect(setSpy).toHaveBeenCalledWith({
      key: SECURE_CREDENTIAL_STORAGE_KEYS.refreshToken,
      value: 'only-via-secure-plugin',
    })
    expect([...plugin.values.keys()]).toEqual([
      SECURE_CREDENTIAL_STORAGE_KEYS.refreshToken,
    ])
  })
})
