import type { Page } from '@playwright/test'

export async function installWorkingMicrophone(page: Page): Promise<void> {
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'mediaDevices', {
      configurable: true,
      value: {
        getUserMedia: async () => {
          const context = new AudioContext()
          const oscillator = context.createOscillator()
          const destination = context.createMediaStreamDestination()
          oscillator.frequency.value = 440
          oscillator.connect(destination)
          oscillator.start()
          await context.resume()
          return destination.stream
        },
      },
    })
  })
}
