import type { SVGProps } from 'react'

type PortraitProps = SVGProps<SVGSVGElement>

function PortraitFrame({
  background,
  children,
  ...props
}: PortraitProps & { readonly background: string }) {
  return (
    <svg
      aria-hidden="true"
      role="presentation"
      viewBox="0 0 160 160"
      xmlns="http://www.w3.org/2000/svg"
      {...props}
    >
      <circle cx="80" cy="80" fill={background} r="80" />
      <circle cx="80" cy="80" fill="none" r="76" stroke="rgb(15 31 23 / 8%)" strokeWidth="8" />
      {children}
    </svg>
  )
}

function Face({
  cx = 80,
  cy = 70,
  fill,
  smile = '#8a5a36',
}: {
  readonly cx?: number
  readonly cy?: number
  readonly fill: string
  readonly smile?: string
}) {
  return (
    <>
      <circle cx={cx} cy={cy} fill={fill} r="28" />
      <ellipse cx={cx - 10} cy={cy + 2} fill="#fff" rx="5.2" ry="6" />
      <ellipse cx={cx + 10} cy={cy + 2} fill="#fff" rx="5.2" ry="6" />
      <circle cx={cx - 10} cy={cy + 3} fill="#2a1c14" r="2.6" />
      <circle cx={cx + 10} cy={cy + 3} fill="#2a1c14" r="2.6" />
      <path
        d={`M${cx - 7} ${cy + 15}c4 6 10 6 14 0`}
        fill="none"
        stroke={smile}
        strokeLinecap="round"
        strokeWidth="2.2"
      />
    </>
  )
}

/** Original illustrated AI Teacher — not a 3D character or licensed likeness. */
export function TeacherPortrait(props: PortraitProps) {
  return (
    <PortraitFrame background="#c8e6d4" {...props}>
      <path d="M42 148c6-38 20-56 38-56s32 18 38 56" fill="#006b3f" />
      <path d="M50 96c12 20 48 20 60 0-6 30-54 30-60 0Z" fill="#d4a574" />
      <Face fill="#d4a574" />
      <path
        d="M48 72c4-34 18-46 32-46 16 0 28 12 32 36 0 0-12-18-32-18S48 70 48 80Z"
        fill="#3b2414"
      />
      <circle cx="80" cy="42" fill="#3b2414" r="11" />
      <path d="M58 72h44" fill="none" stroke="#2f4a3c" strokeWidth="3.2" />
      <circle cx="58" cy="73" fill="none" r="8" stroke="#2f4a3c" strokeWidth="2.4" />
      <circle cx="102" cy="73" fill="none" r="8" stroke="#2f4a3c" strokeWidth="2.4" />
      <circle cx="108" cy="78" fill="#d4a017" r="3.4" />
    </PortraitFrame>
  )
}

/** Original illustrated AI Friend. */
export function FriendPortrait(props: PortraitProps) {
  return (
    <PortraitFrame background="#f3d6c4" {...props}>
      <path d="M46 148c8-36 18-52 34-52s26 16 34 52" fill="#c45c26" />
      <path d="M54 98c10 18 42 18 52 0-6 26-46 26-52 0Z" fill="#c68642" />
      <Face fill="#c68642" smile="#7a4a28" />
      <path
        d="M50 64c8-28 16-36 30-36 16 0 26 10 32 32-10-12-20-16-32-16s-22 8-30 20Z"
        fill="#2c1810"
      />
      <path
        d="M48 78c2-10 6-18 12-22M112 78c-2-10-6-18-12-22"
        fill="none"
        stroke="#2c1810"
        strokeLinecap="round"
        strokeWidth="6"
      />
    </PortraitFrame>
  )
}

/** Original illustrated AI Coach. */
export function CoachPortrait(props: PortraitProps) {
  return (
    <PortraitFrame background="#f5e6c8" {...props}>
      <path d="M40 148c10-34 22-54 40-54s30 20 40 54" fill="#1b7a4e" />
      <path d="M52 100c12 16 44 16 56 0-8 26-48 26-56 0Z" fill="#e0ac69" />
      <Face fill="#e0ac69" />
      <path
        d="M46 80c2-28 16-40 34-40 16 0 30 10 34 32-12-16-22-20-34-18-16 2-28 14-34 26Z"
        fill="#4a2c12"
      />
      <path
        d="M42 118h76"
        fill="none"
        stroke="#0f1f17"
        strokeLinecap="round"
        strokeWidth="3"
      />
    </PortraitFrame>
  )
}

export function GroveMark(props: PortraitProps) {
  return (
    <svg
      aria-hidden="true"
      role="presentation"
      viewBox="0 0 80 80"
      xmlns="http://www.w3.org/2000/svg"
      {...props}
    >
      <circle cx="40" cy="40" fill="#006b3f" r="40" />
      <path
        d="M40 16c12 10 16 22 16 32-8-4-12-4-16 2-4-6-8-6-16-2 0-10 4-22 16-32Z"
        fill="#c8e6d4"
      />
      <path d="M40 28v28" stroke="#0f1f17" strokeLinecap="round" strokeWidth="3" />
    </svg>
  )
}

export function SpeakWaveIcon(props: PortraitProps) {
  return (
    <svg
      aria-hidden="true"
      role="presentation"
      viewBox="0 0 24 24"
      xmlns="http://www.w3.org/2000/svg"
      {...props}
    >
      <path
        d="M4 14c2-3 4-3 6 0s4 3 6 0 4-3 6 0"
        fill="none"
        stroke="currentColor"
        strokeLinecap="round"
        strokeWidth="2.2"
      />
      <path
        d="M7 9c1.4-2 2.8-2 4.2 0M13 9c1.4-2 2.8-2 4.2 0"
        fill="none"
        stroke="currentColor"
        strokeLinecap="round"
        strokeWidth="2"
      />
    </svg>
  )
}
