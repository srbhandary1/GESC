import {
  CoachPortrait,
  FriendPortrait,
  TeacherPortrait,
} from './avatars/GrovePortraits'

export type AvatarRole = 'teacher' | 'friend' | 'coach'

export interface AvatarSlotProps {
  readonly label: string
  readonly size?: 'sm' | 'md' | 'lg'
  readonly slot: 'home-teacher' | 'ai-teacher' | 'ai-friend' | 'ai-coach'
}

const sizeClass: Readonly<Record<NonNullable<AvatarSlotProps['size']>, string>> =
  {
    sm: 'w-[min(3.5rem,100%)]',
    md: 'w-[min(4.5rem,100%)]',
    lg: 'w-[min(8.5rem,42vw)]',
  }

function portraitFor(slot: AvatarSlotProps['slot']) {
  if (slot === 'ai-friend') {
    return FriendPortrait
  }

  if (slot === 'ai-coach') {
    return CoachPortrait
  }

  return TeacherPortrait
}

/**
 * Accessible AI-tutor portrait slot. Original static illustrations only —
 * no third-party avatar SDK, Lottie, or video.
 */
export function AvatarSlot({
  label,
  size = 'md',
  slot,
}: AvatarSlotProps) {
  const Portrait = portraitFor(slot)

  return (
    <div
      aria-label={label}
      className={`${sizeClass[size]} aspect-square max-w-full overflow-hidden rounded-full bg-status shadow-elevation-1 ring-2 ring-action/30 dark:bg-surface-high dark:ring-action/45`}
      data-avatar-slot={slot}
      role="img"
    >
      <Portrait className="block h-full w-full" />
    </div>
  )
}
