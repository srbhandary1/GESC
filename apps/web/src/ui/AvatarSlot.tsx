import miraPortrait from './avatars/mira.jpg'

export type AvatarRole = 'teacher' | 'friend' | 'coach'

export interface AvatarSlotProps {
  readonly label: string
  readonly size?: 'sm' | 'md' | 'lg'
  readonly slot: 'home-teacher' | 'ai-teacher' | 'ai-friend' | 'ai-coach'
}

const sizeClass: Readonly<Record<NonNullable<AvatarSlotProps['size']>, string>> =
  {
    sm: 'w-16',
    md: 'w-[4.5rem]',
    lg: 'w-[min(9.25rem,46vw)]',
  }

/**
 * Mira coach portrait. Static licensed-free Wonder still — no avatar SDK.
 */
export function AvatarSlot({
  label,
  size = 'md',
  slot,
}: AvatarSlotProps) {
  return (
    <div
      aria-label={label}
      className={`${sizeClass[size]} aspect-square max-w-full overflow-hidden rounded-full bg-[#e8f3ec] shadow-[0_0_0_2px_rgb(0_107_63_/_60%)]`}
      data-avatar-slot={slot}
      role="img"
    >
      <img
        alt=""
        className="block h-full w-full object-cover"
        src={miraPortrait}
      />
    </div>
  )
}
