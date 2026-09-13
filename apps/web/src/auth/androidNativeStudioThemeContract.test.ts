import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const authDirectory = dirname(fileURLToPath(import.meta.url))
const androidMain = resolve(
  authDirectory,
  '../../android-app/app/src/main',
)

describe('production Android Studio chrome contract', () => {
  it('uses Global Spoken English brand colours for native chrome', () => {
    const colors = readFileSync(
      resolve(androidMain, 'res/values/colors.xml'),
      'utf8',
    )
    const launcherBackground = readFileSync(
      resolve(androidMain, 'res/values/ic_launcher_background.xml'),
      'utf8',
    )
    const launcherVector = readFileSync(
      resolve(androidMain, 'res/drawable/ic_launcher_background.xml'),
      'utf8',
    )
    const styles = readFileSync(
      resolve(androidMain, 'res/values/styles.xml'),
      'utf8',
    )
    const launchDrawable = readFileSync(
      resolve(androidMain, 'res/drawable/studio_launch.xml'),
      'utf8',
    )

    expect(colors).toContain('#006B3F')
    expect(colors).toContain('#1A1A2E')
    expect(colors).toContain('#E31C23')
    expect(colors).not.toContain('#67e8f9')
    expect(colors).not.toContain('#040713')
    expect(launcherBackground).toContain('#F0FDF4')
    expect(launcherVector).toContain('#F0FDF4')
    expect(launcherVector).not.toContain('#040713')
    expect(styles).toContain('@color/colorPrimaryDark')
    expect(styles).toContain('@drawable/studio_launch')
    expect(launchDrawable).toContain('@color/colorPrimaryDark')
  })
})
