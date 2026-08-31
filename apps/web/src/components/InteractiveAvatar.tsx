import {
  AvatarGesture,
  isAvatarGesture,
  normalizeVisemeTimestamps,
  visemeMouthShapeAt,
  type VisemeTimestamp,
} from '@global-english/contracts'
import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { GltfTutorStage } from '../avatars/GltfTutorStage'
import { webglAvailable } from '../avatars/visemeTargets'
import type { CoachGender } from '../coach/coachPreferences'
import { tutorRendererMode } from '../config/avatarConfig'

/**
 * `InteractiveAvatar` is the learner-facing tutor stage.
 *
 * When WebGL is available it loads the project-owned `coach-*.glb` busts
 * (humanoid skeleton + named viseme morph targets) through Three.js. If
 * WebGL is missing, the GLB fails to load, or the unit-test jsdom canvas
 * cannot create a context, the original inline-SVG face rig is used
 * instead. Both renderers share the same viseme/gesture props, figure
 * sizing, and presence-ring chrome.
 */

/** Canonical, provider-agnostic mouth shapes the rig knows how to draw. */
export type InteractiveAvatarMouthShape =
  | 'rest'
  | 'closed'
  | 'A'
  | 'E'
  | 'I'
  | 'O'
  | 'U'

export type CaptionOverflowMode = 'truncate' | 'wrap' | 'clamp-2'

export type InteractiveAvatarSize = 'sm' | 'md' | 'lg' | 'hero'

/** Cosmetic presence state (independent of the real viseme-driven mouth)
 * used only to color the ambient ring and, for `speaking`, add a gentle
 * breathing pulse when no real audio element is attached yet. */
export type InteractiveAvatarPresence =
  | 'idle'
  | 'listening'
  | 'processing'
  | 'preparing'
  | 'speaking'

export interface InteractiveAvatarProps {
  /** Learner-facing display name, e.g. "Swathi" or "ಸ್ವಾತಿ". */
  readonly name?: string
  /** Optional secondary line (status text, last utterance, etc). Accepts
   * a plain string or a localized message element; only a plain string
   * gets an accessible `title` attribute for truncated overflow. */
  readonly caption?: ReactNode | null
  /** How the caption should behave once it is wider than the avatar. */
  readonly captionOverflow?: CaptionOverflowMode
  /** BCP-47 locale of `name`/`caption`, used for the `lang` attribute so
   * mixed Kannada/Latin strings get correct font shaping and line breaking. */
  readonly locale?: string
  /** Which of the two project-owned coach characters to draw. */
  readonly coachGender?: CoachGender
  /** Cosmetic presence ring / breathing-pulse state. */
  readonly presence?: InteractiveAvatarPresence
  /** The audio element whose playback clock drives the viseme loop. */
  readonly audio?: HTMLAudioElement | null
  /** Provider viseme timeline: `{ mouth_shape, timestamp_ms }[]`. */
  readonly visemeTimestamps?: readonly VisemeTimestamp[] | null
  /** One-shot gesture to play. Set back to `null` between turns to allow
   * the same gesture to be triggered again later. */
  readonly gesture?: AvatarGesture | null
  readonly size?: InteractiveAvatarSize
  readonly showName?: boolean
  readonly className?: string
}

const REST_SHAPE: InteractiveAvatarMouthShape = 'rest'

/**
 * Maps the many mouth-shape vocabularies real TTS/viseme providers emit
 * (Azure viseme ids, SAPI phoneme codes, ad-hoc English labels, or the
 * canonical letters this rig already understands) onto the small,
 * guaranteed-renderable shape set above. Unknown input always resolves to
 * `rest`, which is what keeps this fail-safe: a malformed or unfamiliar
 * `mouth_shape` string can never throw or leave the mouth in an undefined
 * state.
 */
const MOUTH_SHAPE_ALIASES: Readonly<Record<string, InteractiveAvatarMouthShape>> = {
  rest: 'rest',
  idle: 'rest',
  sil: 'rest',
  silence: 'rest',
  '0': 'rest',
  closed: 'closed',
  m: 'closed',
  b: 'closed',
  p: 'closed',
  mm: 'closed',
  bb: 'closed',
  pp: 'closed',
  a: 'A',
  aa: 'A',
  ah: 'A',
  ae: 'A',
  open: 'A',
  wide: 'A',
  '2': 'A',
  '4': 'A',
  e: 'E',
  eh: 'E',
  ee: 'E',
  ei: 'E',
  smile: 'E',
  '3': 'E',
  i: 'I',
  ih: 'I',
  y: 'I',
  '6': 'I',
  o: 'O',
  oh: 'O',
  ow: 'O',
  ou: 'O',
  round: 'O',
  '8': 'O',
  u: 'U',
  uh: 'U',
  ooh: 'U',
  '7': 'U',
}

function canonicalMouthShape(rawShape: string): InteractiveAvatarMouthShape {
  const normalized = rawShape.trim().toLowerCase()
  return MOUTH_SHAPE_ALIASES[normalized] ?? REST_SHAPE
}

/**
 * Mouth geometry. Every path lives inside the shared `0 0 200 200`
 * viewBox and is centered on the same origin, so switching shapes never
 * changes the mouth's position — only the outline the lips trace.
 */
const MOUTH_PATHS: Readonly<Record<InteractiveAvatarMouthShape, string>> = {
  rest: 'M 78 140 Q 100 144 122 140 Q 100 147 78 140 Z',
  closed: 'M 80 140 L 120 140 Q 100 142.5 80 140 Z',
  A: 'M 81 127 Q 100 160 119 127 Q 100 141 81 127 Z',
  E: 'M 72 133 Q 100 148 128 133 Q 100 140 72 133 Z',
  I: 'M 85 135 Q 100 140.5 115 135 Q 100 138.5 85 135 Z',
  O: 'M 89 129 Q 100 117 111 129 Q 100 151 89 129 Z',
  U: 'M 94 132 Q 100 125.5 106 132 Q 100 143 94 132 Z',
}

interface VisemeControllerState {
  readonly mouthShape: InteractiveAvatarMouthShape
  readonly rawMouthShape: string
  readonly isSpeaking: boolean
}

/**
 * Real-time viseme controller.
 *
 * Internally drives a `requestAnimationFrame` loop that samples the
 * target `<audio>` element's `currentTime` every frame, binary-searches
 * the (pre-sorted, pre-normalized) viseme timeline for the frame that is
 * active at that timestamp, and republishes the canonical mouth shape
 * only when it actually changes (avoiding redundant re-renders).
 *
 * Fail-safe by construction: if `audio` is `null`/`undefined`, or the
 * incoming payload has no usable viseme timestamps, the loop never
 * starts and the shape is pinned to `rest` — the caller's idle-blink
 * fallback then takes over with no console errors and no layout impact.
 */
function useInteractiveVisemeController(
  audio: HTMLAudioElement | null | undefined,
  visemeTimestamps: readonly VisemeTimestamp[] | null | undefined,
): VisemeControllerState {
  const [mouthShape, setMouthShape] = useState<InteractiveAvatarMouthShape>(REST_SHAPE)
  const [rawMouthShape, setRawMouthShape] = useState('rest')
  const [isSpeaking, setIsSpeaking] = useState(false)

  const frames = useMemo(
    () => normalizeVisemeTimestamps(visemeTimestamps ?? []),
    [visemeTimestamps],
  )

  const targetAudio = audio ?? null
  const canAnimate = targetAudio !== null && frames.length > 0

  useEffect(() => {
    if (!canAnimate || targetAudio === null) {
      return
    }

    let frameId = 0
    let cancelled = false

    const tick = () => {
      if (cancelled) {
        return
      }

      if (targetAudio.ended) {
        setMouthShape(REST_SHAPE)
        setRawMouthShape('rest')
        setIsSpeaking(false)
        frameId = window.requestAnimationFrame(tick)
        return
      }

      const timestampMs = Math.round(targetAudio.currentTime * 1000)
      const rawShape = visemeMouthShapeAt(frames, timestampMs)
      const nextShape = canonicalMouthShape(rawShape)
      setRawMouthShape((previous) => (previous === rawShape ? previous : rawShape))
      setMouthShape((previous) => (previous === nextShape ? previous : nextShape))
      setIsSpeaking(!targetAudio.paused)
      frameId = window.requestAnimationFrame(tick)
    }

    frameId = window.requestAnimationFrame(tick)

    return () => {
      cancelled = true
      window.cancelAnimationFrame(frameId)
    }
  }, [canAnimate, frames, targetAudio])

  return {
    mouthShape: canAnimate ? mouthShape : REST_SHAPE,
    rawMouthShape: canAnimate ? rawMouthShape : 'rest',
    isSpeaking: canAnimate && isSpeaking,
  }
}

const GESTURE_ANIMATION_CLASS: Readonly<Record<AvatarGesture, string>> = {
  [AvatarGesture.ENCOURAGING_NOD]: 'animate-avatar-nod',
  [AvatarGesture.ATTENTIVE_LEAN]: 'animate-avatar-tilt',
  [AvatarGesture.WARM_SMILE]: 'animate-avatar-smile',
}

const GESTURE_DURATION_MS: Readonly<Record<AvatarGesture, number>> = {
  [AvatarGesture.ENCOURAGING_NOD]: 900,
  [AvatarGesture.ATTENTIVE_LEAN]: 900,
  [AvatarGesture.WARM_SMILE]: 700,
}

interface GestureAnimationState {
  readonly committedGesture: AvatarGesture | null
  readonly activeGesture: AvatarGesture | null
  /** Bumped on every trigger to force the CSS animation to restart via a
   * React `key` remount. */
  readonly animationKey: number
}

const INITIAL_GESTURE_STATE: GestureAnimationState = {
  committedGesture: null,
  activeGesture: null,
  animationKey: 0,
}

/**
 * Gesture state trigger.
 *
 * Applying a gesture enum value plays the matching hardware-accelerated
 * CSS transform animation once, then automatically returns to the
 * idle/speaking resting state after the animation's duration. The
 * trigger is derived directly during render (React's documented pattern
 * for "adjusting state when a prop changes") rather than inside an
 * effect body, so only the auto-clear timer — a genuine external-system
 * subscription — lives in `useEffect`. Once the caller clears `gesture`
 * back to `null` between turns (the normal conversation-turn pulse
 * pattern), the same gesture value can be triggered again later.
 */
function useGestureAnimation(
  gesture: AvatarGesture | null | undefined,
): Pick<GestureAnimationState, 'activeGesture' | 'animationKey'> {
  const normalizedGesture =
    gesture != null && isAvatarGesture(gesture) ? gesture : null
  const [state, setState] = useState<GestureAnimationState>(INITIAL_GESTURE_STATE)

  if (normalizedGesture !== null && normalizedGesture !== state.committedGesture) {
    setState((previous) => ({
      committedGesture: normalizedGesture,
      activeGesture: normalizedGesture,
      animationKey: previous.animationKey + 1,
    }))
  } else if (normalizedGesture === null && state.committedGesture !== null) {
    setState((previous) => ({ ...previous, committedGesture: null }))
  }

  const { activeGesture, animationKey } = state

  useEffect(() => {
    if (activeGesture === null) {
      return
    }

    const timeoutId = window.setTimeout(() => {
      setState((previous) =>
        previous.animationKey === animationKey
          ? { ...previous, activeGesture: null }
          : previous,
      )
    }, GESTURE_DURATION_MS[activeGesture])

    return () => window.clearTimeout(timeoutId)
  }, [activeGesture, animationKey])

  return { activeGesture, animationKey }
}

const SIZE_CLASSES: Readonly<Record<InteractiveAvatarSize, string>> = {
  sm: 'w-14 sm:w-16',
  md: 'w-28 sm:w-32',
  lg: 'w-44 sm:w-48',
  hero: 'w-56 sm:w-64 lg:w-72',
}

const CAPTION_OVERFLOW_CLASSES: Readonly<Record<CaptionOverflowMode, string>> = {
  truncate: 'truncate whitespace-nowrap',
  wrap: 'whitespace-normal break-words [overflow-wrap:anywhere]',
  'clamp-2': 'line-clamp-2 break-words [overflow-wrap:anywhere]',
}

/** Ambient ring color per cosmetic presence state: cyan idle/listen,
 * violet processing, magenta speaking. */
const PRESENCE_RING_PRIMARY_CLASS: Readonly<Record<InteractiveAvatarPresence, string>> = {
  idle: 'border-[rgba(46,230,255,0.5)] [animation-duration:2.4s]',
  listening: 'border-[rgba(122,238,255,0.7)] [animation-duration:2.4s]',
  processing: 'border-[rgba(196,181,255,0.7)] [animation-duration:1.6s]',
  preparing: 'border-[rgba(196,181,255,0.7)] [animation-duration:1.6s]',
  speaking: 'border-[rgba(214,29,110,0.65)] [animation-duration:1.1s]',
}
const PRESENCE_RING_SECONDARY_CLASS = 'border-[rgba(124,92,255,0.45)]'

/** Reuses the design-system's multi-script UI font token
 * (`--ge-font-family-ui`, covering Kannada/Malayalam/Tamil/Telugu/
 * Devanagari plus Latin) so mixed-script display names like "ಸ್ವಾತಿ" /
 * "Swathi" shape and measure correctly before the overflow rules below
 * ever apply. */
const AVATAR_TEXT_FONT_CLASS = '[font-family:var(--ge-font-family-ui)]'

/** Per-gender art: hairstyle, collar color, brow weight, and a couple of
 * small distinguishing accents (earrings for the female character, a
 * light stubble/mustache accent for the male character). Everything else
 * (skin, eyes, cheeks, nose, mouth rig) is shared. */
const GENDER_ART: Readonly<
  Record<
    CoachGender,
    {
      readonly hairPath: string
      readonly collarFill: string
      readonly browStrokeWidth: string
      readonly browPathLeft: string
      readonly browPathRight: string
      readonly showEarrings: boolean
      readonly showFacialHairAccent: boolean
    }
  >
> = {
  female: {
    hairPath:
      'M 40 96 Q 34 44 100 30 Q 166 44 160 96 Q 160 70 138 62 Q 128 78 100 78 Q 72 78 62 62 Q 40 70 40 96 Z',
    collarFill: '#4b3b6b',
    browStrokeWidth: '3.4',
    browPathLeft: 'M 66 82 Q 78 74 92 79',
    browPathRight: 'M 108 79 Q 122 74 134 82',
    showEarrings: true,
    showFacialHairAccent: false,
  },
  male: {
    hairPath:
      'M 44 92 Q 40 46 100 36 Q 160 46 156 92 Q 154 64 132 58 Q 100 48 68 58 Q 46 64 44 92 Z',
    collarFill: '#2c4a5e',
    browStrokeWidth: '4.4',
    browPathLeft: 'M 64 80 L 92 78',
    browPathRight: 'M 108 78 L 136 80',
    showEarrings: false,
    showFacialHairAccent: true,
  },
}

export function InteractiveAvatar({
  name = 'Swathi',
  caption = null,
  captionOverflow = 'truncate',
  locale = 'en',
  coachGender = 'female',
  presence = 'idle',
  audio = null,
  visemeTimestamps = null,
  gesture = null,
  size = 'md',
  showName = true,
  className = '',
}: InteractiveAvatarProps) {
  const { mouthShape, rawMouthShape, isSpeaking } = useInteractiveVisemeController(
    audio,
    visemeTimestamps,
  )
  const { activeGesture, animationKey } = useGestureAnimation(gesture)
  const [gltfFailed, setGltfFailed] = useState(false)
  const useGltf =
    tutorRendererMode() === 'gltf' && !gltfFailed && webglAvailable()

  const headAnimationClass =
    activeGesture !== null
      ? `${GESTURE_ANIMATION_CLASS[activeGesture]} motion-reduce:animate-none`
      : isSpeaking
        ? ''
        : 'animate-avatar-idle-sway motion-reduce:animate-none'

  const mouthPath = MOUTH_PATHS[mouthShape]
  const art = GENDER_ART[coachGender]
  const showSpeakingPulse = presence === 'speaking' || isSpeaking

  return (
    <figure
      className={`flex flex-col items-center gap-2 ${SIZE_CLASSES[size]} ${className}`.trim()}
      data-testid="interactive-avatar"
      data-coach={coachGender}
      data-presence={presence}
      data-mouth-shape={mouthShape}
      data-gesture={activeGesture ?? 'none'}
      data-speaking={isSpeaking}
      data-renderer={useGltf ? 'gltf' : 'svg'}
    >
      <div className="relative aspect-square w-full">
        <span
          aria-hidden="true"
          className={`animate-avatar-ring motion-reduce:animate-none pointer-events-none absolute inset-[6%] rounded-full border-2 ${PRESENCE_RING_PRIMARY_CLASS[presence]}`}
        />
        <span
          aria-hidden="true"
          className={`animate-avatar-ring motion-reduce:animate-none pointer-events-none absolute inset-[6%] rounded-full border-2 ${PRESENCE_RING_SECONDARY_CLASS}`}
          style={{ animationDelay: '0.8s' }}
        />
        <div
          className={`relative h-full w-full overflow-hidden rounded-full border-[3px] border-white/90 bg-gradient-to-b from-[#eef3ff] to-[#cfe0ff] shadow-[0_0_0_6px_rgba(46,230,255,0.22),0_18px_40px_rgba(7,16,24,0.28)] ${showSpeakingPulse ? 'animate-avatar-speak-pulse motion-reduce:animate-none' : ''}`}
        >
          {useGltf ? (
            <GltfTutorStage
              gender={coachGender}
              gesture={activeGesture}
              mouthShape={rawMouthShape}
              onFailure={() => setGltfFailed(true)}
              speaking={isSpeaking}
            />
          ) : (
          <svg
            aria-label={name}
            className="h-full w-full"
            preserveAspectRatio="xMidYMid meet"
            role="img"
            viewBox="0 0 200 200"
          >
            <defs>
              <radialGradient id={`interactive-avatar-skin-${coachGender}`} cx="45%" cy="35%" r="75%">
                <stop offset="0%" stopColor="#f4c9a6" />
                <stop offset="100%" stopColor="#e2a97c" />
              </radialGradient>
              <linearGradient id={`interactive-avatar-hair-${coachGender}`} x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stopColor="#5b3a25" />
                <stop offset="100%" stopColor="#2e1d12" />
              </linearGradient>
            </defs>

            <g
              key={`interactive-avatar-head-${animationKey}`}
              className={headAnimationClass}
              style={{ transformBox: 'fill-box', transformOrigin: '50% 50%' }}
            >
              {/* Neck/shoulder hint: same coordinate space as the head, so
                  it can never desync from the face on resize. */}
              <path
                d="M 82 168 Q 100 178 118 168 L 128 200 L 72 200 Z"
                fill={`url(#interactive-avatar-skin-${coachGender})`}
              />
              <path d="M 60 200 Q 100 176 140 200 Z" fill={art.collarFill} />

              {/* Hair */}
              <path d={art.hairPath} fill={`url(#interactive-avatar-hair-${coachGender})`} />

              {/* Ears */}
              <ellipse cx="42" cy="106" rx="7" ry="10" fill={`url(#interactive-avatar-skin-${coachGender})`} />
              <ellipse cx="158" cy="106" rx="7" ry="10" fill={`url(#interactive-avatar-skin-${coachGender})`} />
              {art.showEarrings ? (
                <>
                  <circle cx="42" cy="112" r="2.4" fill="#c9932f" />
                  <circle cx="158" cy="112" r="2.4" fill="#c9932f" />
                </>
              ) : null}

              {/* Face */}
              <ellipse cx="100" cy="104" rx="54" ry="62" fill={`url(#interactive-avatar-skin-${coachGender})`} />

              {/* Cheeks */}
              <ellipse cx="66" cy="122" rx="12" ry="7" fill="#e8768a" opacity="0.28" />
              <ellipse cx="134" cy="122" rx="12" ry="7" fill="#e8768a" opacity="0.28" />

              {/* Eyebrows */}
              <path
                d={art.browPathLeft}
                fill="none"
                stroke="#2a1a12"
                strokeLinecap="round"
                strokeWidth={art.browStrokeWidth}
              />
              <path
                d={art.browPathRight}
                fill="none"
                stroke="#2a1a12"
                strokeLinecap="round"
                strokeWidth={art.browStrokeWidth}
              />

              {/* Eyes (blink is a scaleY squash on each eye group) */}
              <g
                className="animate-avatar-blink motion-reduce:animate-none"
                style={{ transformBox: 'fill-box', transformOrigin: '50% 50%' }}
              >
                <ellipse cx="79" cy="98" rx="10" ry="7" fill="#fff" />
                <circle cx="80" cy="98" r="4.4" fill="#3c2416" />
                <circle cx="81.5" cy="96.5" r="1.2" fill="#fff" />
              </g>
              <g
                className="animate-avatar-blink motion-reduce:animate-none"
                style={{
                  animationDelay: '120ms',
                  transformBox: 'fill-box',
                  transformOrigin: '50% 50%',
                }}
              >
                <ellipse cx="121" cy="98" rx="10" ry="7" fill="#fff" />
                <circle cx="120" cy="98" r="4.4" fill="#3c2416" />
                <circle cx="121.5" cy="96.5" r="1.2" fill="#fff" />
              </g>

              {/* Nose */}
              <path
                d="M 98 104 Q 94 118 100 121 Q 104 121 102 116"
                fill="none"
                stroke="#c98b62"
                strokeLinecap="round"
                strokeWidth="2"
              />

              {art.showFacialHairAccent ? (
                <path
                  d="M 84 124 Q 100 129 116 124"
                  fill="none"
                  opacity="0.55"
                  stroke="#3a2318"
                  strokeLinecap="round"
                  strokeWidth="2.2"
                />
              ) : null}

              {/* Mouth: the adjustable viseme path. It shares this exact
                  coordinate space with everything above, so it is
                  mathematically impossible for it to clip or drift away
                  from the face independently of the rest of the rig. */}
              <path
                d={mouthPath}
                data-testid="interactive-avatar-mouth"
                fill="#b5453f"
                stroke="#7a2c28"
                strokeLinecap="round"
                strokeWidth="1.5"
              />
            </g>
          </svg>
          )}
        </div>
      </div>

      {showName ? (
        <figcaption
          className={`flex max-w-full flex-col items-center text-center ${AVATAR_TEXT_FONT_CLASS}`}
          lang={locale}
        >
          <span
            className={`max-w-full text-sm font-semibold text-[#f2eefe] sm:text-base ${CAPTION_OVERFLOW_CLASSES.truncate}`}
            title={name}
          >
            {name}
          </span>
          {caption != null && caption !== '' ? (
            <span
              className={`max-w-full text-xs text-[#b9b1d4] sm:text-sm ${CAPTION_OVERFLOW_CLASSES[captionOverflow]}`}
              title={
                typeof caption === 'string' && captionOverflow === 'truncate'
                  ? caption
                  : undefined
              }
            >
              {caption}
            </span>
          ) : null}
        </figcaption>
      ) : null}
    </figure>
  )
}
