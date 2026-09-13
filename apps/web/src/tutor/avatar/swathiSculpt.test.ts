import { describe, expect, it } from 'vitest'
import { SWATHI_LOOK } from './swathiMotion'
import { SWATHI_AVATAR_MODE } from './SwathiAvatarScene'

describe('original Swathi sculpt', () => {
  it('uses the project-owned stylized teacher, not the Fab mesh', () => {
    expect(SWATHI_AVATAR_MODE).toBe('original-sculpt-v4')
    expect(SWATHI_LOOK).toBe('stylized-teal-auburn-glasses')
  })
})
