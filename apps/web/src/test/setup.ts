import '@testing-library/jest-dom/vitest'

// jsdom deliberately omits media playback. Keep lifecycle tests observable
// without emitting "not implemented" errors when components pause and reset
// detached audio/video elements.
Object.defineProperties(HTMLMediaElement.prototype, {
  load: {
    configurable: true,
    value: () => undefined,
  },
  pause: {
    configurable: true,
    value: () => undefined,
  },
})
