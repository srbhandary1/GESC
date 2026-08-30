import type { MessageKey, SupportedLocale } from '@global-english/localization'
import { useEffect, useState } from 'react'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import './MarketingLanding.css'

export interface MarketingLandingProps {
  readonly locale: SupportedLocale
  readonly message: (key: MessageKey) => InterfaceMessageResolution
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
    image: '/marketing/features/club.jpg',
    fit: 'cover',
  },
] as const

const academyPortraits = [
  '/marketing/features/trainer.png',
  '/marketing/features/mobile-learner.png',
  '/marketing/features/highlight.png',
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

export function MarketingLanding({
  locale,
  message,
}: MarketingLandingProps) {
  const [bannerIndex, setBannerIndex] = useState(0)
  const [reduceMotion, setReduceMotion] = useState(false)

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
    <div className="marketing-landing" lang={locale}>
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
        </div>
        <img
          alt=""
          className="marketing-hero__tutor"
          height={420}
          src="/tutor/tutor-maya-hero.png"
          width={320}
        />
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
            <a className="marketing-button marketing-button--primary" href="#sign-in">
              <LocalizedMessage
                resolution={message('marketing.hero.cta_sign_in')}
              />
            </a>
            <a className="marketing-button marketing-button--ghost" href="#how-it-works">
              <LocalizedMessage
                resolution={message('marketing.hero.cta_learn')}
              />
            </a>
          </div>
          <ul className="marketing-hero__portraits" aria-hidden="true">
            {academyPortraits.map((src) => (
              <li key={src}>
                <img alt="" className="marketing-hero__portrait" src={src} />
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section
        className="marketing-section marketing-section--how"
        id="how-it-works"
        aria-labelledby="marketing-how-title"
      >
        <div className="marketing-section__header marketing-section__header--wide">
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
        className="marketing-section"
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
        className="marketing-section"
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
        className="marketing-section"
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
        className="marketing-section"
        aria-labelledby="marketing-showcase-title"
      >
        <div className="marketing-section__header">
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
    </div>
  )
}
