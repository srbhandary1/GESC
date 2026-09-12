import { describe, expect, it } from 'vitest'
import { serializeMultipartFormData } from './serializeMultipartFormData'

describe('serializeMultipartFormData', () => {
  it('encodes a WAV file part Laravel can parse', async () => {
    const formData = new FormData()
    formData.append(
      'audio',
      new File([new Uint8Array([82, 73, 70, 70])], 'voice-practice.wav', {
        type: 'audio/wav',
      }),
    )

    const serialized = await serializeMultipartFormData(formData)
    const body = new TextDecoder().decode(serialized.body)

    expect(serialized.contentType).toMatch(
      /^multipart\/form-data; boundary=----geAndroidForm[0-9a-f]+$/u,
    )
    expect(body).toContain(
      'Content-Disposition: form-data; name="audio"; filename="voice-practice.wav"',
    )
    expect(body).toContain('Content-Type: audio/wav')
    expect(body).toContain('RIFF')
    expect(body).toMatch(/----geAndroidForm[0-9a-f]+--\r\n$/u)
  })

  it('rejects unsafe field names', async () => {
    const formData = new FormData()
    formData.append('not valid', 'nope')

    await expect(serializeMultipartFormData(formData)).rejects.toThrow(
      'The form field name is not allowed.',
    )
  })
})
