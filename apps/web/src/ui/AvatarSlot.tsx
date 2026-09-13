import miraPortrait from './avatars/mira.jpg'
import './AvatarSlot.css'

export type AvatarRole = 'teacher' | 'friend' | 'coach'

export interface AvatarSlotProps {
  readonly label: string
  readonly size?: 'sm' | 'md' | 'lg'
  readonly slot: 'home-teacher' | 'ai-teacher' | 'ai-friend' | 'ai-coach'
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
      data-avatar-size={size}
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
