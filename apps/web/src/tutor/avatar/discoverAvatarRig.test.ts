import { Bone, Group } from 'three'
import { describe, expect, it } from 'vitest'
import { discoverAvatarRig } from './discoverAvatarRig'

describe('discoverAvatarRig', () => {
  it('finds numbered Mixamo body joints used by imported tutor models', () => {
    const root = new Group()
    const names = [
      'mixamorig:Spine_02',
      'mixamorig:Spine2_04',
      'mixamorig:Head_06',
      'mixamorig:LeftShoulder_08',
      'mixamorig:LeftArm_09',
      'mixamorig:LeftForeArm_010',
      'mixamorig:LeftHand_011',
      'mixamorig:RightShoulder_032',
      'mixamorig:RightArm_033',
      'mixamorig:RightForeArm_034',
      'mixamorig:RightHand_035',
    ]

    for (const name of names) {
      const bone = new Bone()
      bone.name = name
      root.add(bone)
    }

    const rig = discoverAvatarRig(root)

    expect(rig.spine?.name).toBe('mixamorig:Spine_02')
    expect(rig.chest?.name).toBe('mixamorig:Spine2_04')
    expect(rig.head?.name).toBe('mixamorig:Head_06')
    expect(rig.leftShoulder?.name).toBe('mixamorig:LeftShoulder_08')
    expect(rig.leftArm?.name).toBe('mixamorig:LeftArm_09')
    expect(rig.leftForeArm?.name).toBe('mixamorig:LeftForeArm_010')
    expect(rig.leftHand?.name).toBe('mixamorig:LeftHand_011')
    expect(rig.rightShoulder?.name).toBe('mixamorig:RightShoulder_032')
    expect(rig.rightArm?.name).toBe('mixamorig:RightArm_033')
    expect(rig.rightForeArm?.name).toBe('mixamorig:RightForeArm_034')
    expect(rig.rightHand?.name).toBe('mixamorig:RightHand_035')
    expect(rig.jaw).toBeNull()
  })
})
