const playbackPlayers = new Set<HTMLAudioElement>()

export function registerSpeechAudio(audio: HTMLAudioElement): void {
  playbackPlayers.add(audio)
}

export function unregisterSpeechAudio(audio: HTMLAudioElement): void {
  playbackPlayers.delete(audio)
}

export function stopSpeechPlayback(): void {
  if (typeof globalThis.speechSynthesis !== 'undefined') {
    globalThis.speechSynthesis.cancel()
  }

  for (const audio of playbackPlayers) {
    audio.pause()
  }
}
