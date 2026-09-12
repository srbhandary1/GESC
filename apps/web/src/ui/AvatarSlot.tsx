export interface AvatarSlotProps {
  readonly label: string
  readonly slot: 'home-teacher' | 'ai-teacher'
}

/**
 * Empty, accessible composition hook for a future teacher avatar
 * (Lottie / video / WebGL). No face, image, or third-party SDK.
 */
export function AvatarSlot({ label, slot }: AvatarSlotProps) {
  return (
    <div
      aria-label={label}
      className="size-[4.5rem] shrink-0 rounded-full border border-line/70 bg-surface-high shadow-elevation-1 ring-2 ring-action/20 dark:bg-surface dark:ring-action/30"
      data-avatar-slot={slot}
      role="img"
    />
  )
}
