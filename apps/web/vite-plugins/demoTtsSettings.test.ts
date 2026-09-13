import { afterEach, describe, expect, it, vi } from 'vitest'
import { mkdtempSync, readFileSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { writeFileSync } from 'node:fs'
import {
  CLEAR_SECRET,
  DEFAULT_ELEVENLABS_VOICE_ID,
  defaultProviderOrder,
  maskSecret,
  mergeSettingsUpdate,
  resolveDemoTtsBundle,
  resolveProviderOrder,
  writeDemoTtsSettingsFile,
} from './demoTtsSettings.mjs'

function isolateSettings() {
  const directory = mkdtempSync(path.join(tmpdir(), 'ge-demo-tts-settings-'))
  vi.stubEnv('DEMO_TTS_SETTINGS_FILE', path.join(directory, 'settings.json'))
  vi.stubEnv('DEMO_TTS_ENV_FILE', path.join(directory, 'missing.tts.env'))
  vi.stubEnv('AZURE_SPEECH_ENDPOINT', '')
  vi.stubEnv('AZURE_SPEECH_KEY', '')
  vi.stubEnv('AZURE_SPEECH_SECRET_FILE', '')
  vi.stubEnv('SARVAM_API_KEY', '')
  vi.stubEnv('SARVAM_API_KEY_FILE', '')
  vi.stubEnv('SARVAM_BASE_URL', '')
  vi.stubEnv('ELEVENLABS_API_KEY', '')
  vi.stubEnv('ELEVENLABS_VOICE_ID', '')
  vi.stubEnv('TTS_PROVIDER_ORDER', '')
  return directory
}

afterEach(() => {
  vi.unstubAllEnvs()
})

describe('demo TTS settings store', () => {
  it('prefers Indian-English Sarvam then ElevenLabs then Azure', () => {
    expect(defaultProviderOrder('en-IN')).toEqual([
      'sarvam',
      'elevenlabs',
      'azure',
    ])
    expect(defaultProviderOrder('hi-IN')).toEqual([
      'sarvam',
      'azure',
      'elevenlabs',
    ])
  })

  it('lets an explicit order override locale defaults', () => {
    expect(resolveProviderOrder('hi-IN', 'azure, elevenlabs')).toEqual([
      'azure',
      'elevenlabs',
    ])
  })

  it('masks secrets without returning the raw value', () => {
    expect(maskSecret('abcd')).toBe('••••')
    expect(maskSecret('sarvam-secret-1234')).toBe('••••1234')
    expect(maskSecret('')).toBe('')
  })

  it('keeps blank POST fields unchanged and clears with __clear__', () => {
    const next = mergeSettingsUpdate(
      { sarvam_api_key: 'keep-me', azure_speech_key: 'remove-me' },
      { sarvam_api_key: '', azure_key: CLEAR_SECRET, elevenlabs_voice_id: 'voice-1' },
    )
    expect(next.sarvam_api_key).toBe('keep-me')
    expect(next.azure_speech_key).toBeUndefined()
    expect(next.elevenlabs_voice_id).toBe('voice-1')
  })

  it('lets environment values win over the host settings file', () => {
    const directory = isolateSettings()
    writeDemoTtsSettingsFile({
      sarvam_api_key: 'file-sarvam-key',
      azure_speech_endpoint: 'https://file.cognitiveservices.azure.com',
      azure_speech_key: 'file-azure-key',
    })
    vi.stubEnv('SARVAM_API_KEY', 'env-sarvam-key')
    vi.stubEnv(
      'AZURE_SPEECH_ENDPOINT',
      'https://env.cognitiveservices.azure.com',
    )
    vi.stubEnv('AZURE_SPEECH_KEY', 'env-azure-key')

    const bundle = resolveDemoTtsBundle()
    expect(bundle.sarvam?.key).toBe('env-sarvam-key')
    expect(bundle.azure?.endpoint).toBe(
      'https://env.cognitiveservices.azure.com',
    )
    expect(bundle.azure?.key).toBe('env-azure-key')
    rmSync(directory, { recursive: true, force: true })
  })

  it('rejects off-allowlist provider URLs and writes a 0600 settings file', () => {
    const directory = isolateSettings()
    writeDemoTtsSettingsFile({
      sarvam_api_key: 'sarvam-file-key',
      sarvam_base_url: 'https://evil.example/tts',
      elevenlabs_api_key: 'eleven-file-key',
      elevenlabs_voice_id: 'voice-fixture',
      elevenlabs_base_url: 'http://api.elevenlabs.io',
    })

    const bundle = resolveDemoTtsBundle()
    expect(bundle.sarvam?.baseUrl).toBe('https://api.sarvam.ai')
    expect(bundle.elevenlabs?.baseUrl).toBe('https://api.elevenlabs.io')

    const filePath = path.join(directory, 'settings.json')
    expect(readFileSync(filePath, 'utf8')).toContain('sarvam-file-key')
    rmSync(directory, { recursive: true, force: true })
  })

  it('treats ElevenLabs as configured from an API key and a public default voice', () => {
    const directory = isolateSettings()
    writeDemoTtsSettingsFile({ elevenlabs_api_key: 'eleven-only-key' })
    const bundle = resolveDemoTtsBundle()
    expect(bundle.elevenlabs?.key).toBe('eleven-only-key')
    expect(bundle.elevenlabs?.voiceId).toBe(DEFAULT_ELEVENLABS_VOICE_ID)
    rmSync(directory, { recursive: true, force: true })
  })

  it('loads allowlisted host tts.env names without overriding process env', () => {
    const directory = isolateSettings()
    const envFile = path.join(directory, 'tts.env')
    writeFileSync(
      envFile,
      [
        'SARVAM_API_KEY=file-sarvam-key',
        'ELEVENLABS_API_KEY=file-eleven-key',
        'IGNORED_SECRET=should-not-load',
        '',
      ].join('\n'),
      { mode: 0o600 },
    )
    vi.stubEnv('DEMO_TTS_ENV_FILE', envFile)
    vi.stubEnv('SARVAM_API_KEY', 'env-sarvam-key')

    const bundle = resolveDemoTtsBundle()
    expect(bundle.sarvam?.key).toBe('env-sarvam-key')
    expect(bundle.elevenlabs?.key).toBe('file-eleven-key')
    expect(process.env.IGNORED_SECRET).toBeUndefined()
    rmSync(directory, { recursive: true, force: true })
  })
})
