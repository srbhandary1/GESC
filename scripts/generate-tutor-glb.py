#!/usr/bin/env python3
"""Bake project-owned male/female tutor busts as glTF 2.0 binary (.glb).

The meshes are original procedural geometry (not third-party scans or RPM
files). Each file carries a humanoid skeleton, a single 1024×1024 albedo
atlas, and the viseme morph-target names required by the Speak lip-sync
contract. Polygon count is kept well under 20 000 triangles.
"""

from __future__ import annotations

import json
import math
import shutil
import struct
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter

VISEME_NAMES = [
    "viseme_sil",
    "viseme_aa",
    "viseme_E",
    "viseme_I",
    "viseme_O",
    "viseme_U",
    "viseme_PP",
    "viseme_FF",
    "viseme_TH",
    "viseme_DD",
    "jawOpen",
]

BONE_NAMES = [
    "Hips",
    "Spine",
    "Spine1",
    "Neck",
    "Head",
    "LeftShoulder",
    "LeftArm",
    "LeftForeArm",
    "LeftHand",
    "RightShoulder",
    "RightArm",
    "RightForeArm",
    "RightHand",
]

# World-space rest pose (metres, Y-up, character faces +Z).
BONE_WORLD = {
    "Hips": (0.0, 0.92, 0.0),
    "Spine": (0.0, 1.08, 0.0),
    "Spine1": (0.0, 1.26, 0.02),
    "Neck": (0.0, 1.40, 0.03),
    "Head": (0.0, 1.52, 0.04),
    "LeftShoulder": (-0.11, 1.36, 0.02),
    "LeftArm": (-0.20, 1.34, 0.03),
    "LeftForeArm": (-0.38, 1.16, 0.06),
    "LeftHand": (-0.48, 0.98, 0.08),
    "RightShoulder": (0.11, 1.36, 0.02),
    "RightArm": (0.20, 1.34, 0.03),
    "RightForeArm": (0.38, 1.16, 0.06),
    "RightHand": (0.48, 0.98, 0.08),
}

BONE_PARENT = {
    "Hips": None,
    "Spine": "Hips",
    "Spine1": "Spine",
    "Neck": "Spine1",
    "Head": "Neck",
    "LeftShoulder": "Spine1",
    "LeftArm": "LeftShoulder",
    "LeftForeArm": "LeftArm",
    "LeftHand": "LeftForeArm",
    "RightShoulder": "Spine1",
    "RightArm": "RightShoulder",
    "RightForeArm": "RightArm",
    "RightHand": "RightForeArm",
}

HEAD_Y = 1.55
MOUTH = (0.0, 1.468, 0.092)
ATLAS = 1024
MAXIMUM_TRIANGLES = 20_000


def vec_add(a, b):
    return (a[0] + b[0], a[1] + b[1], a[2] + b[2])


def vec_sub(a, b):
    return (a[0] - b[0], a[1] - b[1], a[2] - b[2])


def vec_scale(a, s):
    return (a[0] * s, a[1] * s, a[2] * s)


def vec_len(a):
    return math.sqrt(a[0] * a[0] + a[1] * a[1] + a[2] * a[2])


def vec_norm(a):
    length = vec_len(a)
    if length < 1e-12:
        return (0.0, 1.0, 0.0)
    return (a[0] / length, a[1] / length, a[2] / length)


def dot(a, b):
    return a[0] * b[0] + a[1] * b[1] + a[2] * b[2]


def cross(a, b):
    return (
        a[1] * b[2] - a[2] * b[1],
        a[2] * b[0] - a[0] * b[2],
        a[0] * b[1] - a[1] * b[0],
    )


class Mesh:
    def __init__(self):
        self.positions: list[tuple[float, float, float]] = []
        self.uvs: list[tuple[float, float]] = []
        self.joints: list[tuple[int, int, int, int]] = []
        self.weights: list[tuple[float, float, float, float]] = []
        self.regions: list[str] = []
        self.indices: list[int] = []

    def add_vertex(self, position, uv, joints, weights, region):
        index = len(self.positions)
        self.positions.append(position)
        self.uvs.append(uv)
        self.joints.append(joints)
        self.weights.append(weights)
        self.regions.append(region)
        return index

    def add_tri(self, a, b, c):
        self.indices.extend((a, b, c))


def skin_weights(position, region):
    if region in {"hair", "earring"}:
        return (4, 3, 2, 0), (0.82, 0.14, 0.04, 0.0)
    if region in {"eye", "brow"}:
        return (4, 3, 2, 0), (0.92, 0.08, 0.0, 0.0)

    x, y, z = position
    scores = []
    for index, name in enumerate(BONE_NAMES):
        bx, by, bz = BONE_WORLD[name]
        dx, dy, dz = x - bx, y - by, z - bz
        # Prefer the matching side for arm bones.
        if name.startswith("Left") and x > 0.02:
            dist = 1e6
        elif name.startswith("Right") and x < -0.02:
            dist = 1e6
        else:
            dist = dx * dx + dy * dy + dz * dz
        scores.append((dist, index))
    scores.sort()
    chosen = scores[:4]
    inv = [1.0 / max(item[0], 1e-6) for item in chosen]
    total = sum(inv)
    weights = [value / total for value in inv]
    joints = [item[1] for item in chosen]
    while len(joints) < 4:
        joints.append(0)
        weights.append(0.0)
    return tuple(joints), tuple(weights)


def uv_from_atlas(island, u, v):
    """Map 0..1 local UV into a named atlas island."""
    boxes = {
        "skin": (0.02, 0.02, 0.46, 0.46),
        "hair": (0.52, 0.02, 0.46, 0.46),
        "cloth": (0.02, 0.52, 0.46, 0.46),
        "eye": (0.52, 0.52, 0.22, 0.22),
        "lip": (0.76, 0.52, 0.22, 0.22),
        "mouth": (0.52, 0.76, 0.22, 0.22),
        "earring": (0.76, 0.76, 0.22, 0.22),
        "brow": (0.52, 0.02, 0.46, 0.46), # map brow to hair box
    }
    x, y, w, h = boxes[island]
    return (x + max(0.0, min(1.0, u)) * w, 1.0 - (y + max(0.0, min(1.0, v)) * h))


def paint_atlas(path: Path, gender: str) -> None:
    image = Image.new("RGB", (ATLAS, ATLAS), (24, 22, 28))
    draw = ImageDraw.Draw(image)

    def box(island):
        x, y, w, h = {
            "skin": (0.02, 0.02, 0.46, 0.46),
            "hair": (0.52, 0.02, 0.46, 0.46),
            "cloth": (0.02, 0.52, 0.46, 0.46),
            "eye": (0.52, 0.52, 0.22, 0.22),
            "lip": (0.76, 0.52, 0.22, 0.22),
            "mouth": (0.52, 0.76, 0.22, 0.22),
            "earring": (0.76, 0.76, 0.22, 0.22),
        }[island]
        return [
            int(x * ATLAS),
            int(y * ATLAS),
            int((x + w) * ATLAS),
            int((y + h) * ATLAS),
        ]

    # Skin: warm South-Asian undertone with a soft cheek blush.
    skin = box("skin")
    draw.rectangle(skin, fill=(210, 154, 112))
    sx0, sy0, sx1, sy1 = skin
    for row in range(sy0, sy1):
        t = (row - sy0) / max(sy1 - sy0, 1)
        color = (
            int(220 - 18 * t),
            int(164 - 22 * t),
            int(118 - 16 * t),
        )
        draw.line([(sx0, row), (sx1, row)], fill=color)
    blush = (
        int((sx0 + sx1) * 0.5 - 70),
        int((sy0 + sy1) * 0.62),
        int((sx0 + sx1) * 0.5 + 70),
        int((sy0 + sy1) * 0.82),
    )
    draw.ellipse(blush, fill=(214, 126, 118))

    hair = box("hair")
    hair_fill = (42, 26, 18) if gender == "female" else (32, 22, 16)
    draw.rectangle(hair, fill=hair_fill)
    hx0, hy0, hx1, hy1 = hair
    draw.ellipse(
        (hx0 + 40, hy0 + 24, hx1 - 80, hy0 + 160),
        fill=(72, 48, 32) if gender == "female" else (54, 38, 28),
    )

    cloth = box("cloth")
    if gender == "female":
        draw.rectangle(cloth, fill=(92, 58, 118))
        cx0, cy0, cx1, cy1 = cloth
        draw.rectangle((cx0, cy0, cx1, cy0 + 48), fill=(236, 232, 244))
    else:
        draw.rectangle(cloth, fill=(28, 52, 86))
        cx0, cy0, cx1, cy1 = cloth
        draw.rectangle((cx0, cy0, cx1, cy0 + 36), fill=(246, 248, 252))
        draw.polygon(
            [
                (int((cx0 + cx1) * 0.5), cy0 + 20),
                (cx0 + 30, cy1 - 40),
                (cx1 - 30, cy1 - 40),
            ],
            fill=(36, 64, 102),
        )

    eye = box("eye")
    draw.ellipse(eye, fill=(248, 248, 252))
    ex0, ey0, ex1, ey1 = eye
    iris = (ex0 + 18, ey0 + 16, ex1 - 18, ey1 - 16)
    draw.ellipse(iris, fill=(92, 58, 36))
    pupil = (ex0 + 38, ey0 + 34, ex1 - 38, ey1 - 34)
    draw.ellipse(pupil, fill=(18, 12, 10))
    draw.ellipse((ex0 + 48, ey0 + 28, ex0 + 62, ey0 + 42), fill=(250, 250, 252))

    lip = box("lip")
    draw.ellipse(lip, fill=(176, 72, 78))
    lx0, ly0, lx1, ly1 = lip
    draw.ellipse((lx0 + 10, ly0 + 18, lx1 - 10, ly1 - 8), fill=(128, 42, 48))

    mouth = box("mouth")
    draw.ellipse(mouth, fill=(36, 16, 18))
    mx0, my0, mx1, my1 = mouth
    draw.ellipse((mx0 + 28, my0 + 36, mx1 - 28, my1 - 18), fill=(92, 28, 36))

    earring = box("earring")
    draw.ellipse(earring, fill=(212, 168, 72))

    image = image.filter(ImageFilter.SMOOTH)
    image.save(path, format="PNG", optimize=True)


def add_ellipsoid(mesh: Mesh, center, radii, nu, nv, region, island, v_range=(0.0, 1.0), sculpt=None):
    u0, u1 = 0.0, 1.0
    v0, v1 = v_range
    cols, rows = nu, nv
    grid: list[list[int]] = []
    for j in range(rows + 1):
        v = v0 + (v1 - v0) * (j / rows)
        theta = v * math.pi
        row: list[int] = []
        for i in range(cols):
            u = u0 + (u1 - u0) * (i / cols)
            phi = u * math.pi * 2.0
            x = math.sin(theta) * math.cos(phi)
            y = math.cos(theta)
            z = math.sin(theta) * math.sin(phi)
            local = (x * radii[0], y * radii[1], z * radii[2])
            world = vec_add(center, local)
            if sculpt is not None:
                world = sculpt(world, u, v)
            uv = uv_from_atlas(island, (math.cos(phi) * 0.5 + 0.5), v)
            joints, weights = skin_weights(world, region)
            row.append(mesh.add_vertex(world, uv, joints, weights, region))
        grid.append(row)

    for j in range(rows):
        for i in range(cols):
            a = grid[j][i]
            b = grid[j][(i + 1) % cols]
            c = grid[j + 1][i]
            d = grid[j + 1][(i + 1) % cols]
            if j != 0:
                mesh.add_tri(a, b, c)
            if j != rows - 1:
                mesh.add_tri(b, d, c)


def sculpt_face(gender: str):
    jaw = 1.06 if gender == "male" else 0.90
    eye_depth = 0.011 if gender == "female" else 0.009
    nose_gain = 0.026 if gender == "female" else 0.028

    def apply(world, _u, _v):
        x, y, z = world
        x *= jaw if y < HEAD_Y else 1.0
        # Flatten the back of the skull slightly.
        if z < 0.0:
            z *= 0.86
        # Chin.
        if y < HEAD_Y - 0.035 and z > 0.02:
            drop = (HEAD_Y - 0.035 - y) * (1.4 if gender == "male" else 1.1)
            y -= drop * 0.35
            x *= 0.92
        # Brow bossing.
        if HEAD_Y + 0.012 < y < HEAD_Y + 0.045 and z > 0.04:
            z += 0.006 if gender == "male" else 0.003
        # Eye sockets.
        for side in (-1.0, 1.0):
            ex, ey, ez = side * 0.033, HEAD_Y + 0.014, 0.074
            dx, dy, dz = x - ex, y - ey, z - ez
            dist = math.sqrt(dx * dx + dy * dy + dz * dz)
            if dist < 0.028:
                falloff = (1.0 - dist / 0.028) ** 2
                z -= eye_depth * falloff
        # Nose ridge and tip.
        if abs(x) < 0.016 and HEAD_Y - 0.03 < y < HEAD_Y + 0.02 and z > 0.04:
            ridge = (1.0 - abs(x) / 0.016) * max(0.0, (z - 0.04) / 0.06)
            z += nose_gain * ridge
            if y < HEAD_Y - 0.01:
                z += 0.008 * ridge
                y -= 0.004 * ridge
        # Cheeks.
        if 0.018 < abs(x) < 0.06 and HEAD_Y - 0.03 < y < HEAD_Y + 0.01 and z > 0.03:
            z += 0.006
        return (x, y, z)

    return apply


def classify_region(position, base_region):
    if base_region != "head":
        return base_region
    x, y, z = position
    dx, dy = x - MOUTH[0], y - MOUTH[1]
    radius = math.hypot(dx, dy)
    if z < 0.02:
        return "head"
    if radius < 0.011:
        return "mouth_interior"
    if radius < 0.024:
        return "lip_upper" if dy >= -0.001 else "lip_lower"
    if dy < 0.0 and radius < 0.072 and y < HEAD_Y - 0.004:
        return "jaw"
    if radius < 0.05:
        return "cheek"
    # Eyelids: a band over each eye.
    for side in (-1.0, 1.0):
        ex, ey = side * 0.033, HEAD_Y + 0.016
        if math.hypot(x - ex, y - ey) < 0.018 and z > 0.05:
            return "eyelid"
    return "head"


def viseme_delta(name: str, position, region):
    if name == "viseme_sil":
        return (0.0, 0.0, 0.0)

    x, y, z = position
    dx, dy = x - MOUTH[0], y - MOUTH[1]
    radius = math.hypot(dx, dy) + 1e-6
    nx, ny = dx / radius, dy / radius
    falloff = max(0.0, 1.0 - radius / 0.08)

    def scale(vec, amount):
        return (vec[0] * amount, vec[1] * amount, vec[2] * amount)

    if region == "mouth_interior":
        open_back = {
            "viseme_aa": (-dx * 0.15, dy * 0.9, -0.016),
            "viseme_E": (-dx * 0.05, dy * 0.45, -0.008),
            "viseme_I": (dx * 0.08, dy * 0.25, -0.005),
            "viseme_O": (-dx * 0.35, dy * 0.55, -0.012),
            "viseme_U": (-dx * 0.55, dy * 0.2, -0.006),
            "viseme_PP": (0.0, -dy * 0.6, 0.004),
            "viseme_FF": (0.0, dy * 0.15, -0.01),
            "viseme_TH": (0.0, dy * 0.3, 0.004),
            "viseme_DD": (0.0, dy * 0.22, -0.006),
            "jawOpen": (0.0, dy * 1.1, -0.02),
        }[name]
        return scale(open_back, 1.0)

    if region == "lip_upper":
        table = {
            "viseme_aa": (0.0, 0.007, -0.003),
            "viseme_E": (nx * 0.006, 0.003, 0.0),
            "viseme_I": (nx * 0.004, 0.002, 0.0),
            "viseme_O": (-dx * 0.35, 0.006, 0.004),
            "viseme_U": (-dx * 0.55, 0.003, 0.007),
            "viseme_PP": (0.0, -0.004, 0.005),
            "viseme_FF": (0.0, 0.005, 0.001),
            "viseme_TH": (0.0, 0.003, 0.002),
            "viseme_DD": (0.0, 0.003, 0.0),
            "jawOpen": (0.0, 0.004, -0.002),
        }[name]
        return table

    if region == "lip_lower":
        table = {
            "viseme_aa": (0.0, -0.016, -0.004),
            "viseme_E": (nx * 0.006, -0.004, 0.0),
            "viseme_I": (nx * 0.003, -0.003, 0.0),
            "viseme_O": (-dx * 0.35, -0.008, 0.004),
            "viseme_U": (-dx * 0.55, -0.004, 0.007),
            "viseme_PP": (0.0, 0.004, 0.005),
            "viseme_FF": (0.0, 0.002, -0.006),
            "viseme_TH": (0.0, -0.005, 0.003),
            "viseme_DD": (0.0, -0.005, 0.0),
            "jawOpen": (0.0, -0.022, -0.004),
        }[name]
        return table

    if region == "jaw":
        amount = falloff
        table = {
            "viseme_aa": (0.0, -0.014, 0.002),
            "viseme_E": (nx * 0.002, -0.003, 0.0),
            "viseme_I": (0.0, -0.002, 0.0),
            "viseme_O": (0.0, -0.008, 0.001),
            "viseme_U": (0.0, -0.003, 0.002),
            "viseme_PP": (0.0, 0.0, 0.0),
            "viseme_FF": (0.0, -0.002, 0.0),
            "viseme_TH": (0.0, -0.004, 0.0),
            "viseme_DD": (0.0, -0.004, 0.0),
            "jawOpen": (0.0, -0.024, 0.003),
        }[name]
        return scale(table, amount)

    if region == "cheek" and name in {"viseme_E", "viseme_aa", "viseme_O"}:
        pull = 0.004 if name == "viseme_E" else 0.002
        return (nx * pull, 0.0, 0.001 * falloff)

    if region == "eyelid":
        return (0.0, 0.0, 0.0)

    return (0.0, 0.0, 0.0)


def add_eye(mesh: Mesh, center, radius):
    add_ellipsoid(
        mesh,
        center,
        (radius, radius * 0.92, radius * 0.78),
        14,
        10,
        "eye",
        "eye",
    )


def add_ear(mesh: Mesh, center, gender):
    radii = (0.012, 0.022, 0.009) if gender == "female" else (0.013, 0.024, 0.01)
    add_ellipsoid(mesh, center, radii, 10, 8, "head", "skin")


def add_hair(mesh: Mesh, gender: str):
    if gender == "female":
        add_ellipsoid(
            mesh,
            (0.0, HEAD_Y + 0.02, -0.01),
            (0.10, 0.11, 0.11),
            28,
            16,
            "hair",
            "hair",
            v_range=(0.0, 0.62),
        )
        for side in (-1.0, 1.0):
            add_ellipsoid(
                mesh,
                (side * 0.07, HEAD_Y - 0.02, -0.01),
                (0.034, 0.09, 0.04),
                12,
                10,
                "hair",
                "hair",
            )
        # Bangs.
        add_ellipsoid(
            mesh,
            (0.0, HEAD_Y + 0.06, 0.07),
            (0.07, 0.03, 0.03),
            16,
            8,
            "hair",
            "hair",
        )
    else:
        add_ellipsoid(
            mesh,
            (0.0, HEAD_Y + 0.03, -0.005),
            (0.088, 0.07, 0.092),
            24,
            12,
            "hair",
            "hair",
            v_range=(0.0, 0.48),
        )


def add_torso(mesh: Mesh, gender: str):
    shoulder = 0.17 if gender == "male" else 0.15
    add_ellipsoid(
        mesh,
        (0.0, 1.16, 0.02),
        (shoulder, 0.22, 0.09),
        22,
        14,
        "cloth",
        "cloth",
        v_range=(0.18, 0.82),
    )
    # Collar / polo placket.
    add_ellipsoid(
        mesh,
        (0.0, 1.38, 0.04),
        (0.06, 0.035, 0.05),
        16,
        8,
        "cloth",
        "cloth",
    )
    if gender == "male":
        for side in (-1.0, 1.0):
            add_ellipsoid(
                mesh,
                (side * 0.09, 1.22, 0.05),
                (0.05, 0.16, 0.03),
                10,
                8,
                "cloth",
                "cloth",
            )


def add_arm(mesh: Mesh, side: float):
    shoulder = (side * 0.18, 1.32, 0.02)
    add_ellipsoid(mesh, shoulder, (0.04, 0.05, 0.04), 10, 8, "cloth", "cloth")
    add_ellipsoid(
        mesh,
        (side * 0.30, 1.18, 0.04),
        (0.035, 0.12, 0.035),
        10,
        10,
        "cloth",
        "cloth",
    )
    add_ellipsoid(
        mesh,
        (side * 0.42, 1.02, 0.07),
        (0.03, 0.08, 0.03),
        8,
        8,
        "head",
        "skin",
    )


def add_neck(mesh: Mesh):
    add_ellipsoid(
        mesh,
        (0.0, 1.43, 0.03),
        (0.038, 0.05, 0.038),
        14,
        8,
        "head",
        "skin",
    )


def build_character(gender: str) -> Mesh:
    mesh = Mesh()
    head_r = (0.084, 0.112, 0.094) if gender == "female" else (0.088, 0.114, 0.096)
    add_ellipsoid(
        mesh,
        (0.0, HEAD_Y, 0.0),
        head_r,
        48,
        36,
        "head",
        "skin",
        sculpt=sculpt_face(gender),
    )
    add_neck(mesh)
    add_torso(mesh, gender)
    add_hair(mesh, gender)
    add_eye(mesh, (-0.033, HEAD_Y + 0.014, 0.078), 0.016)
    add_eye(mesh, (0.033, HEAD_Y + 0.014, 0.078), 0.016)
    add_ear(mesh, (-0.086, HEAD_Y - 0.002, -0.004), gender)
    add_ear(mesh, (0.086, HEAD_Y - 0.002, -0.004), gender)
    add_arm(mesh, -1.0)
    add_arm(mesh, 1.0)
    if gender == "female":
        add_ellipsoid(
            mesh,
            (-0.086, HEAD_Y - 0.02, -0.002),
            (0.007, 0.007, 0.007),
            8,
            6,
            "earring",
            "earring",
        )
        add_ellipsoid(
            mesh,
            (0.086, HEAD_Y - 0.02, -0.002),
            (0.007, 0.007, 0.007),
            8,
            6,
            "earring",
            "earring",
        )

    mesh.regions = [
        classify_region(position, region)
        for position, region in zip(mesh.positions, mesh.regions)
    ]
    for index, (position, region) in enumerate(zip(mesh.positions, mesh.regions)):
        if region == "mouth_interior":
            mesh.uvs[index] = uv_from_atlas("mouth", 0.5, 0.5)
        elif region in {"lip_upper", "lip_lower"}:
            dx = (position[0] - MOUTH[0]) / 0.03 + 0.5
            dy = (position[1] - MOUTH[1]) / 0.03 + 0.5
            mesh.uvs[index] = uv_from_atlas("lip", dx, dy)
    for side in (-1.0, 1.0):
        add_ellipsoid(
            mesh,
            (side * 0.028, HEAD_Y + 0.038, 0.078),
            (0.018, 0.006, 0.008),
            10,
            6,
            "brow",
            "hair",
        )
    return mesh


def compute_normals(mesh: Mesh):
    normals = [(0.0, 0.0, 0.0)] * len(mesh.positions)
    positions = mesh.positions
    for i in range(0, len(mesh.indices), 3):
        a, b, c = mesh.indices[i], mesh.indices[i + 1], mesh.indices[i + 2]
        ab = vec_sub(positions[b], positions[a])
        ac = vec_sub(positions[c], positions[a])
        n = cross(ab, ac)
        for index in (a, b, c):
            normals[index] = vec_add(normals[index], n)
    return [vec_norm(item) for item in normals]


def align4(blob: bytes, pad: bytes) -> bytes:
    remainder = len(blob) % 4
    if remainder == 0:
        return blob
    return blob + pad * (4 - remainder)


def pack_f32(values):
    return struct.pack("<" + "f" * len(values), *values)


def pack_u16(values):
    return struct.pack("<" + "H" * len(values), *values)


def write_glb(mesh: Mesh, destination: Path, gender: str) -> dict:
    normals = compute_normals(mesh)
    positions = [c for vertex in mesh.positions for c in vertex]
    normals_flat = [c for vertex in normals for c in vertex]
    uvs = [c for uv in mesh.uvs for c in uv]
    joints = [c for item in mesh.joints for c in item]
    weights = [c for item in mesh.weights for c in item]
    indices = mesh.indices

    morph_blobs = []
    morph_minmax = []
    for name in VISEME_NAMES:
        deltas = []
        for position, region in zip(mesh.positions, mesh.regions):
            deltas.extend(viseme_delta(name, position, region))
        morph_blobs.append(pack_f32(deltas))
        xs = deltas[0::3]
        ys = deltas[1::3]
        zs = deltas[2::3]
        morph_minmax.append(
            ([min(xs), min(ys), min(zs)], [max(xs), max(ys), max(zs)])
        )

    ibm = []
    for name in BONE_NAMES:
        tx, ty, tz = BONE_WORLD[name]
        ibm.extend((1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, -tx, -ty, -tz, 1.0))

    bin_parts = [
        pack_f32(positions),
        pack_f32(normals_flat),
        pack_f32(uvs),
        pack_u16(joints),
        pack_f32(weights),
        pack_u16(indices),
        pack_f32(ibm),
        *morph_blobs,
    ]
    offsets = []
    cursor = 0
    aligned_parts = []
    for part in bin_parts:
        padded = align4(part, b"\x00")
        offsets.append((cursor, len(part)))
        aligned_parts.append(padded)
        cursor += len(padded)
    blob = b"".join(aligned_parts)

    def accessor_minmax_positions():
        xs = positions[0::3]
        ys = positions[1::3]
        zs = positions[2::3]
        return [min(xs), min(ys), min(zs)], [max(xs), max(ys), max(zs)]

    pos_min, pos_max = accessor_minmax_positions()
    vertex_count = len(mesh.positions)
    index_count = len(indices)

    views = []
    accessors = []

    def add_view(index, stride=None, target=None):
        offset, length = offsets[index]
        view = {"buffer": 0, "byteOffset": offset, "byteLength": length}
        if stride is not None:
            view["byteStride"] = stride
        if target is not None:
            view["target"] = target
        views.append(view)
        return len(views) - 1

    view_pos = add_view(0, 12, 34962)
    view_nrm = add_view(1, 12, 34962)
    view_uv = add_view(2, 8, 34962)
    view_joints = add_view(3, 8, 34962)
    view_weights = add_view(4, 16, 34962)
    view_indices = add_view(5, None, 34963)
    view_ibm = add_view(6)
    morph_views = [add_view(7 + i, 12, 34962) for i in range(len(VISEME_NAMES))]

    def add_accessor(view, component, type_name, count, min_v=None, max_v=None, normalized=False):
        accessor = {
            "bufferView": view,
            "componentType": component,
            "count": count,
            "type": type_name,
        }
        if min_v is not None:
            accessor["min"] = min_v
        if max_v is not None:
            accessor["max"] = max_v
        if normalized:
            accessor["normalized"] = True
        accessors.append(accessor)
        return len(accessors) - 1

    acc_pos = add_accessor(view_pos, 5126, "VEC3", vertex_count, pos_min, pos_max)
    acc_nrm = add_accessor(view_nrm, 5126, "VEC3", vertex_count)
    acc_uv = add_accessor(view_uv, 5126, "VEC2", vertex_count)
    acc_joints = add_accessor(view_joints, 5123, "VEC4", vertex_count)
    acc_weights = add_accessor(view_weights, 5126, "VEC4", vertex_count)
    acc_indices = add_accessor(view_indices, 5123, "SCALAR", index_count)
    acc_ibm = add_accessor(view_ibm, 5126, "MAT4", len(BONE_NAMES))
    acc_morphs = []
    for view, (mn, mx) in zip(morph_views, morph_minmax):
        acc_morphs.append(add_accessor(view, 5126, "VEC3", vertex_count, mn, mx))

    bone_nodes = []
    # Node 0 is the skinned mesh. Nodes 1.. are bones in BONE_NAMES order.
    children_of = {name: [] for name in BONE_NAMES}
    for name in BONE_NAMES:
        parent = BONE_PARENT[name]
        if parent is not None:
            children_of[parent].append(name)

    def local_translation(name):
        world = BONE_WORLD[name]
        parent = BONE_PARENT[name]
        if parent is None:
            return list(world)
        return list(vec_sub(world, BONE_WORLD[parent]))

    nodes = [
        {
            "name": f"tutor_{gender}",
            "mesh": 0,
            "skin": 0,
            "translation": [0.0, 0.0, 0.0],
        }
    ]
    bone_node_index = {}
    for name in BONE_NAMES:
        bone_node_index[name] = len(nodes)
        node = {
            "name": name,
            "translation": local_translation(name),
        }
        nodes.append(node)

    for name in BONE_NAMES:
        kids = children_of[name]
        if kids:
            nodes[bone_node_index[name]]["children"] = [bone_node_index[k] for k in kids]

    gltf = {
        "asset": {
            "version": "2.0",
            "generator": "global-english-tutor-glb-baker",
        },
        "scene": 0,
        "scenes": [{"name": f"tutor_{gender}", "nodes": [0, bone_node_index["Hips"]]}],
        "nodes": nodes,
        "meshes": [
            {
                "name": f"tutor_{gender}_mesh",
                "extras": {"targetNames": VISEME_NAMES},
                "weights": [0.0] * len(VISEME_NAMES),
                "primitives": [
                    {
                        "attributes": {
                            "POSITION": acc_pos,
                            "NORMAL": acc_nrm,
                            "TEXCOORD_0": acc_uv,
                            "JOINTS_0": acc_joints,
                            "WEIGHTS_0": acc_weights,
                        },
                        "indices": acc_indices,
                        "material": 0,
                        "targets": [{"POSITION": accessor} for accessor in acc_morphs],
                    }
                ],
            }
        ],
        "skins": [
            {
                "name": "tutor_humanoid",
                "inverseBindMatrices": acc_ibm,
                "skeleton": bone_node_index["Hips"],
                "joints": [bone_node_index[name] for name in BONE_NAMES],
            }
        ],
        "materials": [
            {
                "name": "tutor_atlas",
                "pbrMetallicRoughness": {
                    "baseColorTexture": {"index": 0},
                    "metallicFactor": 0.0,
                    "roughnessFactor": 0.62,
                },
                "doubleSided": False,
            }
        ],
        "textures": [{"source": 0, "sampler": 0}],
        "images": [{"mimeType": "image/png", "uri": f"coach-{gender}.png"}],
        "samplers": [
            {
                "magFilter": 9729,
                "minFilter": 9987,
                "wrapS": 10497,
                "wrapT": 10497,
            }
        ],
        "buffers": [{"byteLength": len(blob)}],
        "bufferViews": views,
        "accessors": accessors,
    }

    json_bytes = align4(
        json.dumps(gltf, separators=(",", ":")).encode("utf-8"),
        b" ",
    )
    total = 12 + 8 + len(json_bytes) + 8 + len(blob)
    header = struct.pack("<4sII", b"glTF", 2, total)
    json_chunk = struct.pack("<I4s", len(json_bytes), b"JSON") + json_bytes
    bin_chunk = struct.pack("<I4s", len(blob), b"BIN\x00") + blob
    destination.write_bytes(header + json_chunk + bin_chunk)

    triangle_count = index_count // 3
    return {
        "path": str(destination),
        "gender": gender,
        "vertices": vertex_count,
        "triangles": triangle_count,
        "visemes": VISEME_NAMES,
        "bones": BONE_NAMES,
        "bytes": total,
    }


def main() -> int:
    repo = Path(__file__).resolve().parents[1]
    out_dir = repo / "apps" / "web" / "public" / "avatars"
    out_dir.mkdir(parents=True, exist_ok=True)
    tmp = out_dir / ".atlas-tmp.png"

    summaries = []
    for gender in ("female", "male"):
        paint_atlas(tmp, gender)
        texture = tmp.read_bytes()
        mesh = build_character(gender)
        triangle_count = len(mesh.indices) // 3
        if triangle_count > MAXIMUM_TRIANGLES:
            raise SystemExit(
                f"{gender} avatar is {triangle_count} triangles; limit is {MAXIMUM_TRIANGLES}"
            )
        if len(mesh.positions) > 65535:
            raise SystemExit(f"{gender} avatar exceeds 16-bit indices")
        destination = out_dir / f"coach-{gender}.glb"
        atlas_path = out_dir / f"coach-{gender}.png"
        atlas_path.write_bytes(texture)
        summary = write_glb(mesh, destination, gender)
        shutil.copyfile(destination, out_dir / f"{gender}_tutor.glb")
        summaries.append(
            {
                **summary,
                "path": f"apps/web/public/avatars/coach-{gender}.glb",
                "lobby_path": f"apps/web/public/avatars/{gender}_tutor.glb",
                "atlas": f"apps/web/public/avatars/coach-{gender}.png",
            }
        )

    tmp.unlink(missing_ok=True)
    report = out_dir / "tutor-glb-report.json"
    report.write_text(json.dumps(summaries, indent=2) + "\n", encoding="utf-8")
    for item in summaries:
        print(
            f"{item['gender']}: {item['triangles']} tris, "
            f"{item['vertices']} verts, {item['bytes']} bytes -> {item['path']}"
        )
    return 0


if __name__ == "__main__":
    sys.exit(main())
