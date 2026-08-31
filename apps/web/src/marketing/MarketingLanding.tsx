import type { MessageKey, SupportedLocale } from '@global-english/localization'
import { useEffect, useRef, useState } from 'react'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { ANDROID_APP_DOWNLOAD_HREF } from './androidAppDownload'
import './MarketingLanding.css'

export interface MarketingLandingProps {
  readonly locale: SupportedLocale
  readonly message: (key: MessageKey) => InterfaceMessageResolution
  readonly nativeApp?: boolean
  readonly onNativeSignIn?: () => void
  readonly authorizationUnavailable?: boolean
}

const banners = [
  '/marketing/banners/banner-1.jpg',
  '/marketing/banners/banner-2.jpg',
  '/marketing/banners/banner-3.jpg',
  '/marketing/banners/banner-4.jpg',
  '/marketing/banners/banner-5.jpg',
] as const

const whyCards = [
  {
    title: 'marketing.why1.title',
    body: 'marketing.why1.body',
    image: '/marketing/features/levels.jpg',
  },
  {
    title: 'marketing.why2.title',
    body: 'marketing.why2.body',
    image: '/marketing/features/discussions.jpg',
  },
  {
    title: 'marketing.why3.title',
    body: 'marketing.why3.body',
    image: '/marketing/features/video.jpg',
  },
] as const

const differenceCards = [
  {
    title: 'marketing.benefit.hybrid.title',
    body: 'marketing.benefit.hybrid.body',
    image: '/marketing/features/mobile-learner.png',
    fit: 'contain',
  },
  {
    title: 'marketing.benefit.tuition.title',
    body: 'marketing.benefit.tuition.body',
    image: '/marketing/features/trainer.png',
    fit: 'contain',
  },
  {
    title: 'marketing.benefit.together.title',
    body: 'marketing.benefit.together.body',
    image: '/marketing/features/guest.jpg',
    fit: 'contain',
  },
] as const

const pathSessions = [
  {
    title: 'marketing.path.1.title',
    job: 'marketing.path.1.job',
    ai: 'marketing.path.1.ai',
    output: 'marketing.path.1.output',
  },
  {
    title: 'marketing.path.2.title',
    job: 'marketing.path.2.job',
    ai: 'marketing.path.2.ai',
    output: 'marketing.path.2.output',
  },
  {
    title: 'marketing.path.3.title',
    job: 'marketing.path.3.job',
    ai: 'marketing.path.3.ai',
    output: 'marketing.path.3.output',
  },
  {
    title: 'marketing.path.4.title',
    job: 'marketing.path.4.job',
    ai: 'marketing.path.4.ai',
    output: 'marketing.path.4.output',
  },
] as const

const pedagogyPoints = [
  {
    title: 'marketing.pedagogy.1.title',
    body: 'marketing.pedagogy.1.body',
  },
  {
    title: 'marketing.pedagogy.2.title',
    body: 'marketing.pedagogy.2.body',
  },
  {
    title: 'marketing.pedagogy.3.title',
    body: 'marketing.pedagogy.3.body',
  },
  {
    title: 'marketing.pedagogy.4.title',
    body: 'marketing.pedagogy.4.body',
  },
  {
    title: 'marketing.pedagogy.5.title',
    body: 'marketing.pedagogy.5.body',
  },
] as const

const showcase = [
  {
    src: '/marketing/features/app-left.png',
    caption: 'marketing.showcase.app',
  },
  {
    src: '/marketing/features/live.jpg',
    caption: 'marketing.showcase.live',
  },
  {
    src: '/marketing/features/trainers.jpg',
    caption: 'marketing.benefit.hybrid.title',
  },
] as const

/**
 * Progressive scroll-reveal for the below-the-fold marketing sections.
 *
 * Sections are fully visible by default (no-JS, jsdom, and legacy
 * browsers keep working); when IntersectionObserver exists and the
 * visitor accepts motion, sections are marked pending before first paint
 * and transitioned in as they enter the viewport.
 */
function useSectionReveal(containerRef: React.RefObject<HTMLDivElement | null>) {
  useEffect(() => {
    const container = containerRef.current
    if (
      container === null ||
      typeof IntersectionObserver === 'undefined' ||
      (typeof window.matchMedia === 'function' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches)
    ) {
      return undefined
    }

    const sections = Array.from(
      container.querySelectorAll<HTMLElement>('.marketing-section'),
    )
    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            entry.target.classList.add('marketing-reveal--in')
            observer.unobserve(entry.target)
          }
        }
      },
      { rootMargin: '0px 0px -8% 0px', threshold: 0.05 },
    )

    for (const section of sections) {
      // Anything already on screen (or above the fold after a hash jump)
      // stays visible instead of animating from hidden.
      const rect = section.getBoundingClientRect()
      if (rect.top < window.innerHeight * 0.92 && rect.bottom > 0) {
        section.classList.add('marketing-reveal--in')
        continue
      }
      section.classList.add('marketing-reveal--pending')
      observer.observe(section)
    }

    return () => {
      observer.disconnect()
      for (const section of sections) {
        section.classList.remove('marketing-reveal--pending')
      }
    }
  }, [containerRef])
}

export function MarketingLanding({
  locale,
  message,
  nativeApp = false,
  onNativeSignIn,
  authorizationUnavailable = false,
}: MarketingLandingProps) {
  const containerRef = useRef<HTMLDivElement | null>(null)
  const [bannerIndex, setBannerIndex] = useState(0)
  const [reduceMotion, setReduceMotion] = useState(false)

  useSectionReveal(containerRef)

  useEffect(() => {
    if (typeof window.matchMedia !== 'function') {
      return undefined
    }

    const media = window.matchMedia('(prefers-reduced-motion: reduce)')
    const sync = () => {
      setReduceMotion(media.matches)
    }
    sync()
    media.addEventListener('change', sync)
    return () => {
      media.removeEventListener('change', sync)
    }
  }, [])

  useEffect(() => {
    if (reduceMotion) {
      return undefined
    }

    const timer = window.setInterval(() => {
      setBannerIndex((current) => (current + 1) % banners.length)
    }, 5200)

    return () => {
      window.clearInterval(timer)
    }
  }, [reduceMotion])

  return (
    <div className="marketing-landing" lang={locale} ref={containerRef}>
      <section className="marketing-hero" aria-labelledby="marketing-hero-title">
        <div className="marketing-hero__media" aria-hidden="true">
          {banners.map((src, index) => (
            <img
              key={src}
              alt=""
              className={
                index === bannerIndex
                  ? 'marketing-hero__slide marketing-hero__slide--active'
                  : 'marketing-hero__slide'
              }
              src={src}
            />
          ))}
          <div className="marketing-hero__scrim" />
          <div className="marketing-hero__aurora" />
          <div className="marketing-hero__grid" />
          <div className="marketing-hero__orbs" aria-hidden="true">
            <span />
            <span />
          </div>
        </div>
        <div className="marketing-hero__copy">
          <p className="marketing-hero__badge">
            <LocalizedMessage resolution={message('marketing.hero.badge')} />
          </p>
          <h1
            className="marketing-hero__title"
            id="marketing-hero-title"
            tabIndex={-1}
          >
            <LocalizedMessage resolution={message('marketing.hero.title')} />
          </h1>
          <p className="marketing-hero__lede">
            <LocalizedMessage resolution={message('marketing.hero.lede')} />
          </p>
          <div className="marketing-hero__actions">
            {onNativeSignIn === undefined ? (
              <a
                className="marketing-button marketing-button--primary"
                href="#sign-in"
              >
                <LocalizedMessage
                  resolution={message('marketing.hero.cta_sign_in')}
                />
              </a>
            ) : (
              <button
                className="marketing-button marketing-button--primary"
                onClick={onNativeSignIn}
                type="button"
              >
                <LocalizedMessage
                  resolution={message('marketing.hero.cta_sign_in')}
                />
              </button>
            )}
            <a
              className="marketing-button marketing-button--ghost"
              href="#how-it-works"
              onClick={(event) => {
                event.preventDefault()
                document
                  .getElementById('how-it-works')
                  ?.scrollIntoView({ block: 'start' })
              }}
            >
              <LocalizedMessage
                resolution={message('marketing.hero.cta_learn')}
              />
            </a>
            {nativeApp || onNativeSignIn !== undefined ? null : (
              <a
                className="marketing-button marketing-button--ghost"
                href={ANDROID_APP_DOWNLOAD_HREF}
              >
                <LocalizedMessage
                  resolution={message('marketing.hero.cta_android')}
                />
              </a>
            )}
          </div>
          {authorizationUnavailable ? (
            <p className="marketing-hero__error" role="alert">
              <LocalizedMessage
                resolution={message('session.unavailable')}
              />
            </p>
          ) : null}
          {nativeApp || onNativeSignIn !== undefined ? null : (
            <p className="marketing-hero__android-help">
              <LocalizedMessage
                resolution={message('marketing.android.download_help')}
              />
            </p>
          )}
        </div>
      </section>

      <section
        className="marketing-section marketing-section--how marketing-section--light"
        id="how-it-works"
        aria-labelledby="marketing-how-title"
      >
        <div className="marketing-section__header marketing-section__header--centered">
          <h2 id="marketing-how-title">
            <LocalizedMessage resolution={message('marketing.section.how')} />
          </h2>
          <p>
            <LocalizedMessage
              resolution={message('marketing.section.how_lead')}
            />
          </p>
        </div>
        <ol className="marketing-path">
          {pathSessions.map((session, index) => (
            <li className="marketing-path__item" key={session.title}>
              <span className="marketing-path__index" aria-hidden="true">
                {index + 1}
              </span>
              <div className="marketing-path__body">
                <h3>
                  <LocalizedMessage resolution={message(session.title)} />
                </h3>
                <dl className="marketing-path__meta">
                  <div>
                    <dt>
                      <LocalizedMessage resolution={message('marketing.path.job')} />
                    </dt>
                    <dd>
                      <LocalizedMessage resolution={message(session.job)} />
                    </dd>
                  </div>
                  <div>
                    <dt>
                      <LocalizedMessage resolution={message('marketing.path.ai')} />
                    </dt>
                    <dd>
                      <LocalizedMessage resolution={message(session.ai)} />
                    </dd>
                  </div>
                  <div>
                    <dt>
                      <LocalizedMessage
                        resolution={message('marketing.path.output')}
                      />
                    </dt>
                    <dd>
                      <LocalizedMessage resolution={message(session.output)} />
                    </dd>
                  </div>
                </dl>
              </div>
            </li>
          ))}
        </ol>
      </section>

      <section
        className="marketing-section marketing-section--dark"
        aria-labelledby="marketing-pedagogy-title"
      >
        <div className="marketing-section__header marketing-section__header--wide">
          <h2 id="marketing-pedagogy-title">
            <LocalizedMessage
              resolution={message('marketing.section.pedagogy')}
            />
          </h2>
          <p>
            <LocalizedMessage
              resolution={message('marketing.section.pedagogy_lead')}
            />
          </p>
        </div>
        <ul className="marketing-pedagogy">
          {pedagogyPoints.map((item) => (
            <li className="marketing-pedagogy__item" key={item.title}>
              <h3>
                <LocalizedMessage resolution={message(item.title)} />
              </h3>
              <p>
                <LocalizedMessage resolution={message(item.body)} />
              </p>
            </li>
          ))}
        </ul>
      </section>

      <section
        className="marketing-section marketing-section--light"
        aria-labelledby="marketing-why-title"
      >
        <div className="marketing-section__header">
          <h2 id="marketing-why-title">
            <LocalizedMessage resolution={message('marketing.section.why')} />
          </h2>
        </div>
        <ul className="marketing-why">
          {whyCards.map((item) => (
            <li className="marketing-why__item" key={item.title}>
              <div className="marketing-why__media">
                <img alt="" className="marketing-why__image" src={item.image} />
              </div>
              <div className="marketing-why__copy">
                <h3>
                  <LocalizedMessage resolution={message(item.title)} />
                </h3>
                <p>
                  <LocalizedMessage resolution={message(item.body)} />
                </p>
              </div>
            </li>
          ))}
        </ul>
      </section>

      <section
        className="marketing-section marketing-section--dark"
        aria-labelledby="marketing-difference-title"
      >
        <div className="marketing-section__header">
          <h2 id="marketing-difference-title">
            <LocalizedMessage
              resolution={message('marketing.section.difference')}
            />
          </h2>
          <p>
            <LocalizedMessage
              resolution={message('marketing.section.difference_lead')}
            />
          </p>
        </div>
        <ul className="marketing-benefits">
          {differenceCards.map((item) => (
            <li className="marketing-benefit" key={item.title}>
              <div
                className={
                  item.fit === 'contain'
                    ? 'marketing-benefit__media marketing-benefit__media--contain'
                    : 'marketing-benefit__media'
                }
              >
                <img alt="" className="marketing-benefit__image" src={item.image} />
              </div>
              <div className="marketing-benefit__copy">
                <h3>
                  <LocalizedMessage resolution={message(item.title)} />
                </h3>
                <p>
                  <LocalizedMessage resolution={message(item.body)} />
                </p>
              </div>
            </li>
          ))}
        </ul>
      </section>

      <section
        className="marketing-section marketing-section--light"
        aria-labelledby="marketing-showcase-title"
      >
        <div className="marketing-section__header marketing-section__header--centered">
          <h2 id="marketing-showcase-title">
            <LocalizedMessage
              resolution={message('marketing.showcase.title')}
            />
          </h2>
          <p>
            <LocalizedMessage
              resolution={message('marketing.showcase.lead')}
            />
          </p>
        </div>
        <ul className="marketing-showcase">
          {showcase.map((item) => (
            <li className="marketing-showcase__item" key={item.src}>
              <div className="marketing-showcase__frame">
                <img alt="" className="marketing-showcase__image" src={item.src} />
              </div>
              <p>
                <LocalizedMessage resolution={message(item.caption)} />
              </p>
            </li>
          ))}
        </ul>
      </section>
      <footer className="marketing-footer">
        <img
          alt=""
          className="marketing-footer__logo"
          src="/marketing/brand/logo-footer.png"
        />
      </footer>
    </div>
  )
}
