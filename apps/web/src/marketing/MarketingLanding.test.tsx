import type { MessageKey } from '@global-english/localization'
import { cleanup, render, screen, within } from '@testing-library/react'
import { afterEach, describe, expect, it } from 'vitest'
import { resolveInterfaceMessage } from '../localization/interfaceLocalization'
import { APP_VERSION, ANDROID_APK_HREF } from './androidDownload'
import { MarketingLanding } from './MarketingLanding'

const message = (key: MessageKey) =>
  resolveInterfaceMessage(key, 'en-IN', 'development-preview')

describe('MarketingLanding studio hero', () => {
  afterEach(() => {
    cleanup()
  })

  it('presents one stable first screen with two actions and disclosed AI', () => {
    render(<MarketingLanding locale="en-IN" message={message} />)

    expect(
      screen.getByRole('heading', {
        level: 1,
        name: message('marketing.hero.title').text,
      }),
    ).toBeInTheDocument()
    const hero = screen.getByRole('heading', {
      level: 1,
      name: message('marketing.hero.title').text,
    }).closest('section')

    expect(hero).not.toBeNull()
    const firstScreen = within(hero!)

    expect(
      firstScreen.getByRole('link', {
        name: message('marketing.hero.cta_sign_in').text,
      }),
    ).toHaveAttribute('href', '#sign-in')
    expect(
      firstScreen.getByRole('link', {
        name: message('marketing.hero.cta_learn').text,
      }),
    ).toHaveAttribute('href', '#how-it-works')
    expect(
      screen.getByText(message('conversation.prototype_notice').text),
    ).toBeInTheDocument()
    expect(document.querySelector('[aria-roledescription="carousel"]')).toBeNull()
    expect(document.querySelector('.marketing-hero__slide')).toBeNull()
    expect(document.querySelector('.marketing-hero__banner')).not.toBeNull()
    expect(
      firstScreen.getByRole('list', { name: 'Session path' }),
    ).toHaveTextContent(/Introduction.*Practice.*Conversation.*Interaction/)
    expect(
      screen.getAllByRole('link', {
        name: message('marketing.hero.cta_sign_in').text,
      }),
    ).toHaveLength(2)
    const androidLinks = screen.getAllByRole('link', {
      name: `${message('marketing.android.download').text} v${APP_VERSION}`,
    })

    expect(androidLinks).toHaveLength(1)
    expect(androidLinks[0]).toHaveAttribute('href', ANDROID_APK_HREF)
  })

  it('presents one coherent guest journey without legacy marketing imagery', () => {
    render(<MarketingLanding locale="en-IN" message={message} />)

    for (const heading of [
      'marketing.showcase.title',
      'marketing.section.how',
      'marketing.section.why',
      'marketing.section.pedagogy',
      'marketing.benefit.together.title',
      'marketing.join.title',
    ] as const) {
      expect(
        screen.getByRole('heading', { name: message(heading).text }),
      ).toBeInTheDocument()
    }

    expect(screen.getByRole('heading', { name: '5-day start' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Premium features' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Premium with Role-play' })).toBeInTheDocument()
    expect(
      screen.getByRole('link', { name: 'Pay and create an account Premium with Role-play' }),
    ).toHaveAttribute('href', '#sign-up/monthly_modules_roleplay')
    expect(
      screen.getByRole('group', {
        name: message('marketing.preview.controls').text,
      }),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', {
        name: message('marketing.outcome.work.title').text,
      }),
    ).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: message('marketing.trust.adult.title').text })).toBeInTheDocument()
    expect(document.querySelector('.marketing-split')).toBeNull()
    expect(document.querySelector('.marketing-showcase')).toBeNull()
    expect(document.querySelector('.marketing-why')).toBeNull()
    expect(document.querySelector('img')).toBeNull()
  })
})
