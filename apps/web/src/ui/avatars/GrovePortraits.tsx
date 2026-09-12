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
      <path
        d="M18 118c18-14 38-8 52 2 16 11 34 10 52-4 8-6 16-8 20-8"
        fill="none"
        stroke="rgb(0 107 63 / 22%)"
        strokeLinecap="round"
        strokeWidth="6"
      />
      {children}
    </svg>
  )
}

/** Original illustrated AI Teacher — not a 3D character or licensed likeness. */
export function TeacherPortrait(props: PortraitProps) {
  return (
    <PortraitFrame background="#c8e6d4" {...props}>
      <path
        d="M46 142c4-36 18-54 34-54s30 18 34 54"
        fill="#006b3f"
      />
      <path d="M54 92c8 18 44 18 52 0-4 28-48 28-52 0Z" fill="#d4a574" />
      <circle cx="80" cy="72" fill="#d4a574" r="26" />
      <path
        d="M52 74c2-28 16-40 28-40 14 0 26 10 28 32 0 0-10-16-28-16s-28 16-28 24Z"
        fill="#3b2414"
      />
      <circle cx="80" cy="46" fill="#3b2414" r="10" />
      <circle cx="70" cy="72" fill="#2a1c14" r="2.4" />
      <circle cx="90" cy="72" fill="#2a1c14" r="2.4" />
      <path
        d="M74 84c3 4 9 4 12 0"
        fill="none"
        stroke="#8a5a36"
        strokeLinecap="round"
        strokeWidth="2"
      />
      <circle cx="104" cy="76" fill="#d4a017" r="3" />
    </PortraitFrame>
  )
}

/** Original illustrated AI Friend. */
export function FriendPortrait(props: PortraitProps) {
  return (
    <PortraitFrame background="#f3d6c4" {...props}>
      <path d="M48 142c6-34 16-48 32-48s26 14 32 48" fill="#c45c26" />
      <path d="M56 94c8 16 40 16 48 0-6 24-42 24-48 0Z" fill="#c68642" />
      <circle cx="80" cy="74" fill="#c68642" r="25" />
      <path
        d="M54 70c4-26 14-34 26-34 14 0 24 8 28 30-8-10-18-14-28-14s-20 6-26 18Z"
        fill="#2c1810"
      />
      <circle cx="70" cy="74" fill="#2a1c14" r="2.4" />
      <circle cx="90" cy="74" fill="#2a1c14" r="2.4" />
      <path
        d="M72 86c4 5 12 5 16 0"
        fill="none"
        stroke="#7a4a28"
        strokeLinecap="round"
        strokeWidth="2.2"
      />
    </PortraitFrame>
  )
}

/** Original illustrated AI Coach. */
export function CoachPortrait(props: PortraitProps) {
  return (
    <PortraitFrame background="#f5e6c8" {...props}>
      <path d="M44 142c8-32 20-50 36-50s28 18 36 50" fill="#1b7a4e" />
      <path d="M54 96c10 16 42 16 52 0-8 26-44 26-52 0Z" fill="#e0ac69" />
      <circle cx="80" cy="72" fill="#e0ac69" r="26" />
      <path
        d="M48 78c2-30 14-42 32-42 16 0 28 10 32 34-10-18-20-22-32-20-16 2-26 14-32 28Z"
        fill="#4a2c12"
      />
      <circle cx="70" cy="72" fill="#2a1c14" r="2.4" />
      <circle cx="90" cy="72" fill="#2a1c14" r="2.4" />
      <path
        d="M74 84c4 3 8 3 12 0"
        fill="none"
        stroke="#8a5a36"
        strokeLinecap="round"
        strokeWidth="2"
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
