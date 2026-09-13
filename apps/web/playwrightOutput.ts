import { tmpdir } from 'node:os'
import { join } from 'node:path'

export function playwrightOutputDir(suite: string): string {
  const configured = process.env.PLAYWRIGHT_OUTPUT_DIR?.trim()
  if (configured) {
    return configured
  }

  const userId = typeof process.getuid === 'function' ? process.getuid() : 'user'

  return join(
    tmpdir(),
    `global-spoken-english-playwright-${userId}`,
    suite,
  )
}
