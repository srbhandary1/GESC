import type { MessageKey, SupportedLocale } from '@global-english/localization'
import { useState } from 'react'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import {
  APP_VERSION,
  ANDROID_APK_FILENAME,
  ANDROID_APK_HREF,
} from './androidDownload'
import { directSignupHref, directSignupPlans } from './directSignupPlans'
import './MarketingLanding.css'

export interface MarketingLandingProps {
  readonly hideAndroidPromo?: boolean
  readonly locale: SupportedLocale
  readonly message: (key: MessageKey) => InterfaceMessageResolution
}

const learningStages = [
  {
    title: 'marketing.path.1.title',
    job: 'marketing.path.1.job',
    output: 'marketing.path.1.output',
    ai: 'marketing.path.1.ai',
  },
  {
    title: 'marketing.path.2.title',
    job: 'marketing.path.2.job',
    output: 'marketing.path.2.output',
    ai: 'marketing.path.2.ai',
  },
  {
    title: 'marketing.path.3.title',
    job: 'marketing.path.3.job',
    output: 'marketing.path.3.output',
    ai: 'marketing.path.3.ai',
  },
  {
    title: 'marketing.path.4.title',
    job: 'marketing.path.4.job',
    output: 'marketing.path.4.output',
    ai: 'marketing.path.4.ai',
  },
] as const

const previewStages = [
  {
    title: 'marketing.loop.listen.title',
    body: 'marketing.loop.listen.body',
    coach: 'marketing.path.1.job',
    learner: 'conversation.confidence.listen_text_first',
  },
  {
    title: 'marketing.loop.speak.title',
    body: 'marketing.loop.speak.body',
    coach: 'marketing.path.3.job',
    learner: 'marketing.path.3.output',
  },
  {
    title: 'marketing.loop.understand.title',
    body: 'marketing.loop.understand.body',
    coach: 'marketing.pedagogy.4.body',
    learner: 'marketing.why3.body',
  },
  {
    title: 'marketing.loop.improve.title',
    body: 'marketing.loop.improve.body',
    coach: 'marketing.pedagogy.2.body',
    learner: 'marketing.step3.body',
  },
] as const

const outcomes = [
  { title: 'marketing.outcome.work.title', body: 'marketing.outcome.work.body', glyph: '01' },
  { title: 'marketing.outcome.customer.title', body: 'marketing.outcome.customer.body', glyph: '02' },
  { title: 'marketing.outcome.education.title', body: 'marketing.outcome.education.body', glyph: '03' },
  { title: 'marketing.outcome.interview.title', body: 'marketing.outcome.interview.body', glyph: '04' },
  { title: 'marketing.outcome.everyday.title', body: 'marketing.outcome.everyday.body', glyph: '05' },
] as const

const trustPoints = [
  { title: 'marketing.pedagogy.1.title', body: 'marketing.pedagogy.1.body' },
  { title: 'marketing.pedagogy.4.title', body: 'marketing.pedagogy.4.body' },
  { title: 'marketing.pedagogy.5.title', body: 'marketing.pedagogy.5.body' },
] as const

const readinessPoints = [
  { title: 'marketing.trust.adult.title', body: 'marketing.trust.adult.body' },
  { title: 'marketing.why3.title', body: 'marketing.why3.body' },
  { title: 'marketing.trust.offline.title', body: 'marketing.trust.offline.body' },
  { title: 'marketing.benefit.tuition.title', body: 'marketing.benefit.tuition.body' },
] as const

const stats = [
  { label: 'marketing.stats.levels', value: 'marketing.stats.levels_value' },
  { label: 'marketing.stats.ai', value: 'marketing.stats.ai_value' },
  { label: 'marketing.stats.guidance', value: 'marketing.stats.guidance_value' },
  { label: 'marketing.stats.path', value: 'marketing.stats.path_value' },
] as const

export function MarketingLanding({
  hideAndroidPromo = false,
  locale,
  message,
}: MarketingLandingProps) {
  const [previewIndex, setPreviewIndex] = useState(0)
  const [typedPreview, setTypedPreview] = useState(false)
  const preview = previewStages[previewIndex] ?? previewStages[0]

  return (
    <div className="marketing-landing" lang={locale}>
      <section aria-labelledby="marketing-hero-title" className="marketing-hero">
        <div aria-hidden="true" className="marketing-hero__grid" />
        <div className="marketing-hero__banner">
          <p className="marketing-hero__banner-name">
            <LocalizedMessage resolution={message('app.name')} />
          </p>
          <h1 className="marketing-hero__title" id="marketing-hero-title" tabIndex={-1}>
            <LocalizedMessage resolution={message('marketing.hero.title')} />
          </h1>
        </div>
        <div className="marketing-hero__copy">
          <p className="marketing-hero__badge">
            <span aria-hidden="true" className="marketing-hero__badge-signal" />
            <LocalizedMessage resolution={message('marketing.hero.badge')} />
          </p>
          <p className="marketing-hero__lede">
            <LocalizedMessage resolution={message('marketing.hero.lede')} />
          </p>
          <div className="marketing-intro__actions">
            <a className="marketing-button marketing-button--primary" href="#sign-in">
              <LocalizedMessage resolution={message('marketing.hero.cta_sign_in')} />
              <span aria-hidden="true">→</span>
            </a>
            <a className="marketing-button marketing-button--ghost" href="#how-it-works">
              <LocalizedMessage resolution={message('marketing.hero.cta_learn')} />
            </a>
          </div>
          <ol aria-label={message('marketing.stats.path').text} className="marketing-hero__path">
            {learningStages.map((stage, index) => (
              <li key={stage.title}>
                <span aria-hidden="true">0{index + 1}</span>
                <LocalizedMessage resolution={message(stage.title)} />
              </li>
            ))}
          </ol>
        </div>
        <div className="marketing-hero__presence">
          <div aria-hidden="true" className="marketing-hero__orbit marketing-hero__orbit--outer" />
          <div aria-hidden="true" className="marketing-hero__orbit marketing-hero__orbit--inner" />
          <div aria-hidden="true" className="marketing-hero__voice-core"><i /><i /><i /><i /><i /></div>
          <div className="marketing-hero__ai-card">
            <span><LocalizedMessage resolution={message('tutor.name')} /></span>
            <strong><LocalizedMessage resolution={message('conversation.disclosure_title')} /></strong>
            <p><LocalizedMessage resolution={message('conversation.prototype_notice')} /></p>
          </div>
        </div>
      </section>

      <section aria-labelledby="marketing-stats-title" className="studio-section studio-metrics">
        <h2 className="visually-hidden" id="marketing-stats-title">
          <LocalizedMessage resolution={message('marketing.stats.title')} />
        </h2>
        <ul>
          {stats.map((item) => (
            <li key={item.label}>
              <strong><LocalizedMessage resolution={message(item.value)} /></strong>
              <span><LocalizedMessage resolution={message(item.label)} /></span>
            </li>
          ))}
        </ul>
      </section>

      <section aria-labelledby="marketing-showcase-title" className="studio-section studio-demo">
        <div className="studio-section__heading">
          <p><LocalizedMessage resolution={message('marketing.hero.badge')} /></p>
          <h2 id="marketing-showcase-title"><LocalizedMessage resolution={message('marketing.showcase.title')} /></h2>
          <span><LocalizedMessage resolution={message('marketing.showcase.lead')} /></span>
        </div>
        <div className="studio-demo__stage">
          <div className="studio-demo__rail" aria-hidden="true"><i /><i /><i /></div>
          <div
            aria-label={message('marketing.preview.controls').text}
            className="studio-preview__controls"
            role="group"
          >
            {previewStages.map((stage, index) => (
              <button
                aria-pressed={index === previewIndex}
                key={stage.title}
                onClick={() => {
                  setPreviewIndex(index)
                  if (index !== 1) {
                    setTypedPreview(false)
                  }
                }}
                type="button"
              >
                <LocalizedMessage resolution={message(stage.title)} />
              </button>
            ))}
          </div>
          <div className="studio-demo__conversation">
            <p className="studio-demo__label">
              <LocalizedMessage resolution={message(preview.title)} />
            </p>
            <p className="studio-demo__hint">
              <LocalizedMessage resolution={message(preview.body)} />
            </p>
            <div className="studio-demo__bubble studio-demo__bubble--coach">
              <LocalizedMessage resolution={message(preview.coach)} />
            </div>
            <div className={typedPreview ? 'studio-demo__bubble studio-demo__bubble--typed' : 'studio-demo__bubble studio-demo__bubble--learner'}>
              <LocalizedMessage
                resolution={message(
                  typedPreview
                    ? 'marketing.preview.type_instead'
                    : preview.learner,
                )}
              />
            </div>
            {typedPreview ? null : (
              <div aria-hidden="true" className="studio-demo__wave"><i /><i /><i /><i /><i /><i /><i /></div>
            )}
          </div>
          <button
            className="studio-preview__type"
            onClick={() => {
              setTypedPreview(true)
              setPreviewIndex(1)
            }}
            type="button"
          >
            <LocalizedMessage resolution={message('conversation.confidence.type_instead')} />
          </button>
        </div>
      </section>

      <section aria-labelledby="marketing-how-title" className="studio-section studio-loop" id="how-it-works">
        <div className="studio-section__heading">
          <p><LocalizedMessage resolution={message('marketing.stats.path')} /></p>
          <h2 id="marketing-how-title"><LocalizedMessage resolution={message('marketing.section.how')} /></h2>
          <span><LocalizedMessage resolution={message('marketing.section.how_lead')} /></span>
        </div>
        <ol>
          {learningStages.map((stage, index) => (
            <li key={stage.title}>
              <span className="studio-loop__number">0{index + 1}</span>
              <div>
                <h3><LocalizedMessage resolution={message(stage.title)} /></h3>
                <p><LocalizedMessage resolution={message(stage.job)} /></p>
                <small><LocalizedMessage resolution={message(stage.output)} /></small>
              </div>
            </li>
          ))}
        </ol>
      </section>

      <section aria-labelledby="marketing-outcomes-title" className="studio-section studio-outcomes">
        <div className="studio-section__heading studio-section__heading--center">
          <p><LocalizedMessage resolution={message('marketing.section.difference')} /></p>
          <h2 id="marketing-outcomes-title"><LocalizedMessage resolution={message('marketing.section.why')} /></h2>
          <span><LocalizedMessage resolution={message('marketing.section.difference_lead')} /></span>
        </div>
        <ul>
          {outcomes.map((outcome) => (
            <li key={outcome.title}>
              <span aria-hidden="true">{outcome.glyph}</span>
              <h3><LocalizedMessage resolution={message(outcome.title)} /></h3>
              <p><LocalizedMessage resolution={message(outcome.body)} /></p>
            </li>
          ))}
        </ul>
      </section>

      <section aria-labelledby="marketing-trust-title" className="studio-section studio-trust">
        <div className="studio-trust__presence" aria-hidden="true">
          <div className="studio-trust__core"><i /><i /><i /></div>
        </div>
        <div className="studio-trust__copy">
          <div className="studio-section__heading">
            <p><LocalizedMessage resolution={message('conversation.disclosure_title')} /></p>
            <h2 id="marketing-trust-title"><LocalizedMessage resolution={message('marketing.section.pedagogy')} /></h2>
            <span><LocalizedMessage resolution={message('marketing.section.pedagogy_lead')} /></span>
          </div>
          <ul>
            {trustPoints.map((point) => (
              <li key={point.title}>
                <span aria-hidden="true">✓</span>
                <div><h3><LocalizedMessage resolution={message(point.title)} /></h3><p><LocalizedMessage resolution={message(point.body)} /></p></div>
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section aria-labelledby="marketing-plans-title" className="studio-section studio-plans">
        <div className="studio-section__heading studio-section__heading--center">
          <p><LocalizedMessage resolution={message('subscription.title')} /></p>
          <h2 id="marketing-plans-title"><LocalizedMessage resolution={message('marketing.benefit.together.title')} /></h2>
          <span><LocalizedMessage resolution={message('subscription.gst_exclusive')} /></span>
        </div>
        <ul>
          {directSignupPlans.map((plan) => (
            <li className={plan.recommended === true ? 'studio-plan studio-plan--recommended' : 'studio-plan'} key={plan.code}>
              {plan.recommended === true ? <p className="studio-plan__flag"><LocalizedMessage resolution={message('subscription.recommended')} /></p> : null}
              <h3><LocalizedMessage resolution={message(plan.name)} /></h3>
              <strong><LocalizedMessage resolution={message(plan.price)} /></strong>
              <p><LocalizedMessage resolution={message(plan.lead)} /></p>
              <small><LocalizedMessage resolution={message(plan.extra)} /></small>
              <a className="marketing-button marketing-button--primary" href={directSignupHref(plan.code)}>
                <LocalizedMessage resolution={message('auth.paid.open')} />
                {' '}
                <span className="visually-hidden">
                  <LocalizedMessage resolution={message(plan.name)} />
                </span>
              </a>
            </li>
          ))}
        </ul>
      </section>

      <section aria-labelledby="marketing-readiness-title" className="studio-section studio-readiness">
        <div className="studio-section__heading studio-section__heading--center">
          <p><LocalizedMessage resolution={message('marketing.section.difference')} /></p>
          <h2 id="marketing-readiness-title"><LocalizedMessage resolution={message('marketing.benefit.hybrid.title')} /></h2>
          <span><LocalizedMessage resolution={message('marketing.section.difference_lead')} /></span>
        </div>
        <ul>
          {readinessPoints.map((point) => (
            <li key={point.title}>
              <h3><LocalizedMessage resolution={message(point.title)} /></h3>
              <p><LocalizedMessage resolution={message(point.body)} /></p>
            </li>
          ))}
        </ul>
      </section>

      {!hideAndroidPromo ? (
        <section aria-labelledby="marketing-android-title" className="studio-section studio-android" id="android-app">
          <div>
            <p><LocalizedMessage resolution={message('marketing.showcase.app')} /></p>
            <h2 id="marketing-android-title"><LocalizedMessage resolution={message('marketing.android.title')} /></h2>
            <span><LocalizedMessage resolution={message('marketing.android.lead')} /></span>
          </div>
          <a className="marketing-button marketing-button--android" download={ANDROID_APK_FILENAME} href={ANDROID_APK_HREF}>
            <LocalizedMessage resolution={message('marketing.android.download')} />{' '}<span>v{APP_VERSION}</span>
          </a>
        </section>
      ) : null}

      <section aria-labelledby="marketing-join-title" className="studio-section studio-closing">
        <div aria-hidden="true" className="studio-closing__glow" />
        <p><LocalizedMessage resolution={message('marketing.hero.badge')} /></p>
        <h2 id="marketing-join-title"><LocalizedMessage resolution={message('marketing.join.title')} /></h2>
        <span><LocalizedMessage resolution={message('marketing.join.body')} /></span>
        <a className="marketing-button marketing-button--primary" href="#sign-in">
          <LocalizedMessage resolution={message('marketing.hero.cta_sign_in')} />
          <span aria-hidden="true">→</span>
        </a>
      </section>
    </div>
  )
}
