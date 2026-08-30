import { describe, expect, it } from 'vitest'
import { formDataWithFileParts } from './formDataWithFileParts'

describe('formDataWithFileParts', () => {
  it('promotes unnamed Blob parts to File so Capacitor keeps the bytes', () => {
    const formData = new FormData()
    formData.append(
      'audio',
      new Blob([new Uint8Array([82, 73, 70, 70])], { type: 'audio/wav' }),
    )

    const rewritten = formDataWithFileParts(formData)
    const audio = rewritten.get('audio')

    expect(audio).toBeInstanceOf(File)
    expect((audio as File).name).toBe('voice-practice.wav')
    expect((audio as File).type).toBe('audio/wav')
  })

  it('renames a File called blob so Capacitor keeps a real filename', () => {
    const formData = new FormData()
    formData.append(
      'audio',
      new File([new Uint8Array([82, 73, 70, 70])], 'blob', {
        type: 'audio/wav',
      }),
    )

    const audio = formDataWithFileParts(formData).get('audio')

    expect(audio).toBeInstanceOf(File)
    expect((audio as File).name).toBe('voice-practice.wav')
  })

  it('keeps an existing File name and type', () => {
    const formData = new FormData()
    formData.append(
      'audio',
      new File([new Uint8Array([82, 73, 70, 70])], 'voice-practice.wav', {
        type: 'audio/wav',
      }),
    )

    const audio = formDataWithFileParts(formData).get('audio')

    expect(audio).toBeInstanceOf(File)
    expect((audio as File).name).toBe('voice-practice.wav')
  })
})
