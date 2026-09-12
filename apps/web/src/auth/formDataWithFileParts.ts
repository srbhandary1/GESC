/**
 * Capacitor's Android fetch interceptor only preserves multipart files when the
 * part is a File. A Blob is coerced to the string "[object Blob]". jsdom and
 * some browsers also wrap an unnamed Blob as a File named "blob".
 */
export function formDataWithFileParts(formData: FormData): FormData {
  const rewritten = new FormData()

  for (const [name, value] of formData.entries()) {
    if (typeof value === 'string') {
      rewritten.append(name, value)
      continue
    }

    const file = new File([value], filePartName(value), {
      type: value.type.trim() === '' ? 'audio/wav' : value.type,
    })
    rewritten.append(name, file, file.name)
  }

  return rewritten
}

function filePartName(value: Blob): string {
  const name =
    'name' in value && typeof value.name === 'string' ? value.name.trim() : ''

  if (name === '' || name.toLowerCase() === 'blob') {
    return 'voice-practice.wav'
  }

  return name
}
