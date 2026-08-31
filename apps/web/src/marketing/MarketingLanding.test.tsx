import type { MessageKey } from '@global-english/localization'
import { cleanup, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { InterfaceMessageResolution } from '../localization/interfaceLocalization'
import { ANDROID_APP_DOWNLOAD_HREF } from './androidAppDownload'
import { MarketingLanding } from './MarketingLanding'

afterEach(cleanup)

function message(key: MessageKey): InterfaceMessageResolution {
  return {
    fallbackEvent: null,
    isPreview: false,
    reason: 'requested-locale',
    requestedLocale: 'en-IN',
    resolvedLocale: 'en-IN',
    reviewStatus: 'source',
    revision: 'test',
    text: key,
    usedFallback: false,
  }
}

describe('MarketingLanding', () => {
  it('offers the invite-only Android APK from the homepage hero', () => {
    render(<MarketingLanding locale="en-IN" message={message} />)

    const download = screen.getByRole('link', {
      name: 'marketing.hero.cta_android',
    })
    expect(download).toHaveAttribute('href', ANDROID_APP_DOWNLOAD_HREF)
    expect(download).not.toHaveAttribute('download')
    expect(
      screen.getByRole('link', { name: 'marketing.hero.cta_sign_in' }),
    ).toHaveAttribute('href', '#sign-in')
    expect(
      screen.getByText('marketing.android.download_help'),
    ).toBeInTheDocument()
  })

  it('starts native sign-in from the hero and hides the APK download in the Android app', async () => {
    const user = userEvent.setup()
    const onNativeSignIn = vi.fn()

    render(
      <MarketingLanding
        locale="en-IN"
        message={message}
        nativeApp
        onNativeSignIn={onNativeSignIn}
      />,
    )

    await user.click(
      screen.getByRole('button', { name: 'marketing.hero.cta_sign_in' }),
    )

    expect(onNativeSignIn).toHaveBeenCalledOnce()
    expect(
      screen.queryByRole('link', { name: 'marketing.hero.cta_android' }),
    ).not.toBeInTheDocument()
    expect(
      screen.queryByText('marketing.android.download_help'),
    ).not.toBeInTheDocument()
  })

  it('explains when native sign-in could not open the system browser', () => {
    render(
      <MarketingLanding
        authorizationUnavailable
        locale="en-IN"
        message={message}
        nativeApp
        onNativeSignIn={() => undefined}
      />,
    )

    expect(screen.getByRole('alert')).toHaveTextContent('session.unavailable')
  })
})
