import type {
  MessageKey,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { LocalizedMessage } from '../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { AvatarSlot } from '../ui/AvatarSlot'

export interface GuestMiraHomeProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
}

export function GuestMiraHome({
  locale,
  localizationPolicy,
}: GuestMiraHomeProps) {
  const message = (key: MessageKey) =>
    resolveInterfaceMessage(key, locale, localizationPolicy)

  return (
    <section
      aria-labelledby="guest-mira-home-title"
      className="guest-mira-home phase1-home"
      data-guest-mira-home="unsigned"
      lang={locale}
    >
      <header className="phase1-home__hello">
        <div className="phase1-home__hello-copy">
          <p className="phase1-home__greeting">
            <LocalizedMessage
              resolution={message('demo.phase1.home.greeting')}
            />
          </p>
          <h1 className="phase1-home__title" id="guest-mira-home-title" tabIndex={-1}>
            <LocalizedMessage resolution={message('demo.phase1.home.title')} />
          </h1>
          <p>
            <LocalizedMessage resolution={message('shell.mira.role')} />
          </p>
        </div>
        <AvatarSlot
          label={message('tutor.name').text}
          size="lg"
          slot="home-teacher"
        />
      </header>
      <article className="phase1-home__lesson">
        <div className="phase1-home__lesson-meta">
          <p className="phase1-home__eyebrow">
            <LocalizedMessage
              resolution={message('demo.phase1.home.hero_label')}
            />
          </p>
        </div>
        <p className="phase1-home__lesson-title">
          <LocalizedMessage
            resolution={message('demo.phase1.home.next_action')}
          />
        </p>
        <p className="phase1-home__level">
          <LocalizedMessage resolution={message('session.sign_in_hint')} />
        </p>
        <a
          className="ge-button ge-button--primary phase1-home__speak"
          href="#sign-in"
        >
          <LocalizedMessage resolution={message('demo.phase1.home.speak')} />
        </a>
        <a
          className="ge-button ge-button--primary phase1-home__cta"
          href="#sign-in"
        >
          <LocalizedMessage
            resolution={message('marketing.hero.cta_sign_in')}
          />
        </a>
      </article>
      <aside className="phase1-home__tip">
        <div>
          <p className="phase1-home__tip-title">
            <LocalizedMessage
              resolution={message('demo.phase1.home.tip_title')}
            />
          </p>
          <p className="phase1-home__tip-body">
            <LocalizedMessage
              resolution={message('demo.phase1.home.tip_body')}
            />
          </p>
        </div>
      </aside>
      <p className="guest-mira-home__about">
        <a className="phase1-home__android--inline" href="#about">
          <LocalizedMessage
            resolution={message('marketing.hero.cta_learn')}
          />
        </a>
      </p>
    </section>
  )
}
