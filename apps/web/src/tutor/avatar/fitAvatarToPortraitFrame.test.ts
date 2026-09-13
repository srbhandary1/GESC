import { Box3, BoxGeometry, Group, Mesh, Vector3 } from 'three'
import { describe, expect, it } from 'vitest'
import { fitAvatarToPortraitFrame } from './fitAvatarToPortraitFrame'

describe('fitAvatarToPortraitFrame', () => {
  it('frames a full-body model waist-up inside a portrait viewport', () => {
    const root = new Group()
    const body = new Mesh(new BoxGeometry(0.6, 1.8, 0.35))
    body.position.y = 0.9
    root.add(body)

    fitAvatarToPortraitFrame(root, { viewportAspect: 14 / 17 })

    const fitted = new Box3().setFromObject(root)
    const size = fitted.getSize(new Vector3())
    const waistY = fitted.min.y + size.y * 0.56

    expect(fitted.max.y).toBeGreaterThan(0.15)
    expect(waistY).toBeGreaterThan(-0.48)
    expect(waistY).toBeLessThan(-0.28)
    expect(Math.abs((fitted.min.x + fitted.max.x) * 0.5)).toBeLessThan(0.05)
  })
})
