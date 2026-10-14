import { useEffect, useRef, useState } from 'react'
import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { demoLevelFixture } from '../demo/demoLevelFixtures'
import {
  registerSpeechAudio,
  stopSpeechPlayback,
  unregisterSpeechAudio,
} from '../shared/stopSpeechPlayback'
import { AvatarSlot } from '../ui/AvatarSlot'
import { directSignupHref, directSignupPlans } from '../marketing/directSignupPlans'
import { guestSampleAudioSrc } from './guestSampleAudio'
import './GuestWebHome.css'

export interface GuestWebHomeProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}

const SAMPLE_LEVEL = 12
const LOOP_STEPS = [
  'listen',
  'speak',
  'understand',
  'improve',
] as const
const SESSION_STEPS = [1, 2, 3, 4] as const
const TRUST_STEPS = [1, 4, 5] as const

export function GuestWebHome({
  locale,
  localizationPolicy,
}: GuestWebHomeProps) {
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)
  const sample = demoLevelFixture(SAMPLE_LEVEL)
  const phrases = sample?.talk.fallback_turns ?? []
  const check = sample?.check[0]
  const [phraseIndex, setPhraseIndex] = useState(0)
  const [playing, setPlaying] = useState(false)
  const [heard, setHeard] = useState(false)
  const [choice, setChoice] = useState<number | null>(null)
  const [loopStep, setLoopStep] = useState<(typeof LOOP_STEPS)[number]>('listen')
  const [sessionStep, setSessionStep] = useState<(typeof SESSION_STEPS)[number]>(1)
  const audioRef = useRef<HTMLAudioElement>(null)

  const phrase = phrases[phraseIndex] ?? phrases[0] ?? ''
  const sampleAudioSrc = guestSampleAudioSrc(phrase)

  useEffect(() => {
    const audio = audioRef.current
    if (audio === null) {
      return
    }
    registerSpeechAudio(audio)
    return () => {
      audio.pause()
      unregisterSpeechAudio(audio)
    }
  }, [sampleAudioSrc])

  useEffect(() => {
    const audio = audioRef.current
    if (audio === null) {
      return
    }
    audio.pause()
    audio.currentTime = 0
    setPlaying(false)
    setHeard(false)
  }, [phrase])

  const playSample = async () => {
    const audio = audioRef.current
    if (audio === null || sampleAudioSrc === null) {
      return
    }
    stopSpeechPlayback()
    audio.currentTime = 0
    setPlaying(true)
    try {
      await audio.play()
    } catch {
      setPlaying(false)
    }
  }

  return (
    <section
      aria-labelledby="guest-mira-home-title"
      className="guest-mira-home guest-mira-home--web phase1-home"
      data-guest-layout="web"
      data-guest-mira-home="unsigned"
      lang={locale}
    >
      <div className="guest-web-home__hero">
        <div className="guest-web-home__hero-copy">
          <p className="guest-web-home__eyebrow">
            <LocalizedMessage resolution={message('marketing.hero.badge')} />
          </p>
          <h1 className="guest-web-home__title" id="guest-mira-home-title" tabIndex={-1}>
            <LocalizedMessage resolution={message('marketing.hero.title')} />
          </h1>
          <p className="guest-web-home__lede">
            <LocalizedMessage resolution={message('marketing.hero.lede')} />
          </p>
          <div className="guest-web-home__hero-actions">
            <a className="ge-button ge-button--primary" href="#sign-in">
              <LocalizedMessage resolution={message('marketing.hero.cta_sign_in')} />
            </a>
            <a className="ge-button ge-button--ghost" href="#guest-plans">
              <LocalizedMessage resolution={message('auth.paid.open')} />
            </a>
            <a className="ge-button ge-button--ghost" href="#guest-sessions">
              <LocalizedMessage resolution={message('marketing.hero.cta_learn')} />
            </a>
          </div>
        </div>
        <aside className="guest-web-home__teacher">
          <div className="guest-web-home__teacher-row">
            <AvatarSlot
              label={message('tutor.name').text}
              size="lg"
              slot="home-teacher"
            />
            <div>
              <p className="guest-web-home__teacher-name">
                <LocalizedMessage resolution={message('tutor.name')} />
              </p>
              <p className="guest-web-home__muted">
                <LocalizedMessage resolution={message('tutor.role')} />
              </p>
            </div>
          </div>
          <p className="guest-web-home__muted">
            <LocalizedMessage resolution={message('marketing.pedagogy.1.body')} />
          </p>
          <p className="guest-web-home__tip-title">
            <LocalizedMessage resolution={message('demo.phase1.home.tip_title')} />
          </p>
          <p>
            <LocalizedMessage resolution={message('demo.phase1.home.tip_body')} />
          </p>
        </aside>
      </div>

      <section aria-labelledby="guest-plans-title" className="guest-web-home__block" id="guest-plans">
        <h2 className="guest-web-home__section-title" id="guest-plans-title">
          <LocalizedMessage resolution={message('subscription.memberships_title')} />
        </h2>
        <p className="guest-web-home__lede">
          <LocalizedMessage resolution={message('subscription.gst_exclusive')} />
        </p>
        <div className="guest-web-home__plans">
          {directSignupPlans.map((plan) => (
            <article className="guest-web-home__card" key={plan.code}>
              {plan.recommended === true ? (
                <p className="guest-web-home__eyebrow">
                  <LocalizedMessage resolution={message('subscription.recommended')} />
                </p>
              ) : null}
              <h3 className="guest-web-home__card-title">
                <LocalizedMessage resolution={message(plan.name)} />
              </h3>
              <p className="guest-web-home__plan-price">
                <LocalizedMessage resolution={message(plan.price)} />
              </p>
              <p>
                <LocalizedMessage resolution={message(plan.lead)} />
              </p>
              <p className="guest-web-home__muted">
                <LocalizedMessage resolution={message(plan.extra)} />
              </p>
              <a className="ge-button ge-button--primary" href={directSignupHref(plan.code)}>
                <LocalizedMessage resolution={message('auth.paid.open')} />
                {' '}
                <span className="visually-hidden">
                  <LocalizedMessage resolution={message(plan.name)} />
                </span>
              </a>
            </article>
          ))}
        </div>
      </section>

      {sample ? (
        <section className="guest-web-home__sample" id="guest-sample">
          <p className="guest-web-home__eyebrow">
            <LocalizedMessage resolution={message('demo.phase1.home.hero_label')} />
            {` · ${sample.level_number}`}
          </p>
          <h2 className="guest-web-home__section-title">{sample.target_patterns[0]}</h2>
          <p className="guest-web-home__lede">
            {locale === 'kn-IN' ? sample.kannada_summary : sample.objective}
          </p>
          <div className="guest-web-home__sample-grid">
            <article className="guest-web-home__card">
              <p className="guest-web-home__eyebrow">{sample.talk.scenario}</p>
              <p className="guest-web-home__phrase" lang="en">
                {phrase}
              </p>
              {sampleAudioSrc ? (
                <audio
                  aria-hidden="true"
                  data-testid="guest-sample-audio"
                  onEnded={() => {
                    setPlaying(false)
                    setHeard(true)
                  }}
                  onError={() => {
                    setPlaying(false)
                  }}
                  preload="none"
                  ref={audioRef}
                  src={sampleAudioSrc}
                />
              ) : null}
              <div className="guest-web-home__playbar" data-state={playing ? 'playing' : heard ? 'heard' : 'idle'}>
                <span className="guest-web-home__playbar-fill" />
              </div>
              <div className="guest-web-home__hero-actions">
                <button
                  aria-pressed={playing}
                  className="ge-button ge-button--primary"
                  disabled={playing || sampleAudioSrc === null}
                  onClick={() => {
                    void playSample()
                  }}
                  type="button"
                >
                  <LocalizedMessage resolution={message('marketing.loop.listen.title')} />
                </button>
              </div>
              <ul className="guest-web-home__phrases">
                {phrases.map((line, index) => (
                  <li key={line}>
                    <button
                      aria-pressed={phraseIndex === index}
                      className="guest-web-home__phrase-btn"
                      lang="en"
                      onClick={() => {
                        setPhraseIndex(index)
                      }}
                      type="button"
                    >
                      {line}
                    </button>
                  </li>
                ))}
              </ul>
            </article>
            {check ? (
              <article className="guest-web-home__card">
                <p className="guest-web-home__eyebrow">
                  <LocalizedMessage
                    resolution={message('marketing.preview.controls')}
                  />
                </p>
                <h3 className="guest-web-home__card-title" lang="en">
                  {check.prompt}
                </h3>
                <div className="guest-web-home__options" role="group">
                  {check.options.map((option, index) => (
                    <button
                      aria-pressed={choice === index}
                      className="guest-web-home__option"
                      key={option}
                      lang="en"
                      onClick={() => setChoice(index)}
                      type="button"
                    >
                      {option}
                    </button>
                  ))}
                </div>
                {choice !== null ? (
                  <p className="guest-web-home__status" role="status">
                    {choice === check.correct_option ? (
                      <LocalizedMessage
                        resolution={message('marketing.pedagogy.4.title')}
                      />
                    ) : (
                      <span lang="en">
                        {check.options[check.correct_option]}
                      </span>
                    )}
                  </p>
                ) : (
                  <p className="guest-web-home__muted">
                    <LocalizedMessage
                      resolution={message('marketing.pedagogy.4.title')}
                    />
                  </p>
                )}
              </article>
            ) : null}
          </div>
        </section>
      ) : null}

      <section className="guest-web-home__block">
        <h2 className="guest-web-home__section-title">
          <LocalizedMessage resolution={message('marketing.section.how')} />
        </h2>
        <p className="guest-web-home__lede">
          <LocalizedMessage resolution={message('marketing.section.how_lead')} />
        </p>
        <div className="guest-web-home__step-row">
          {LOOP_STEPS.map((step, index) => (
            <button
              aria-pressed={loopStep === step}
              className="guest-web-home__step"
              key={step}
              onClick={() => setLoopStep(step)}
              type="button"
            >
              <span className="guest-web-home__step-index">0{index + 1}</span>
              <LocalizedMessage resolution={message(`marketing.loop.${step}.title`)} />
            </button>
          ))}
        </div>
        <p>
          <LocalizedMessage resolution={message(`marketing.loop.${loopStep}.body`)} />
        </p>
      </section>

      <section className="guest-web-home__block" id="guest-sessions">
        <h2 className="guest-web-home__section-title">
          <LocalizedMessage resolution={message('marketing.pedagogy.2.title')} />
        </h2>
        <p className="guest-web-home__lede">
          <LocalizedMessage resolution={message('marketing.pedagogy.2.body')} />
        </p>
        <div className="guest-web-home__step-row">
          {SESSION_STEPS.map((step) => (
            <button
              aria-pressed={sessionStep === step}
              className="guest-web-home__session"
              key={step}
              onClick={() => setSessionStep(step)}
              type="button"
            >
              <span className="guest-web-home__step-index">0{step}</span>
              <LocalizedMessage resolution={message(`marketing.path.${step}.title`)} />
            </button>
          ))}
        </div>
        <article className="guest-web-home__card">
          <p className="guest-web-home__eyebrow">
            <LocalizedMessage resolution={message(`marketing.path.${sessionStep}.title`)} />
          </p>
          <p>
            <LocalizedMessage resolution={message(`marketing.path.${sessionStep}.job`)} />
          </p>
          <p className="guest-web-home__muted">
            <LocalizedMessage resolution={message(`marketing.path.${sessionStep}.output`)} />
          </p>
        </article>
      </section>

      <section className="guest-web-home__trust">
        {TRUST_STEPS.map((step) => (
          <article className="guest-web-home__card" key={step}>
            <h3 className="guest-web-home__card-title">
              <LocalizedMessage resolution={message(`marketing.pedagogy.${step}.title`)} />
            </h3>
            <p className="guest-web-home__muted">
              <LocalizedMessage resolution={message(`marketing.pedagogy.${step}.body`)} />
            </p>
          </article>
        ))}
      </section>
    </section>
  )
}
