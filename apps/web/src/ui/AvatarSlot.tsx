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
      className="size-[4.5rem] shrink-0 rounded-full bg-status shadow-elevation-1 ring-2 ring-action/25 dark:bg-surface-high dark:ring-action/40"
      data-avatar-slot={slot}
      role="img"
    />
  )
}
