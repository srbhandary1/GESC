import { describe, expect, it } from 'vitest'
import {
  avatarConfig,
  tutorAtlasUrl,
  tutorGlbUrl,
  tutorRendererMode,
} from './avatarConfig'

describe('avatarConfig', () => {
  it('maps the dashboard teacher selection onto the lobby GLB files', () => {
    expect(avatarConfig.defaultTeacherId).toBe('female')
    expect(avatarConfig.avatars.female.glb).toBe('/avatars/female_tutor.glb')
    expect(avatarConfig.avatars.male.glb).toBe('/avatars/male_tutor.glb')
    expect(tutorGlbUrl('female')).toBe('/avatars/female_tutor.glb')
    expect(tutorGlbUrl('male')).toBe('/avatars/male_tutor.glb')
    expect(tutorAtlasUrl('female')).toBe('/avatars/coach-female.png')
  })

  it('holds tutor surfaces on the SVG rig until a GLB passes visual review (D-0249)', () => {
    expect(tutorRendererMode()).toBe('svg')
  })
})
