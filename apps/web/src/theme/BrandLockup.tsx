import { BRAND_ASSETS } from './brandIdentity'

export function BrandLockup({
  android = false,
  theme,
}: {
  android?: boolean
  theme: 'light' | 'dark'
}) {
  return (
    <span
      aria-hidden="true"
      className={
        android
          ? 'shell-brand-lockup shell-brand-lockup--android'
          : 'shell-brand-lockup'
      }
    >
      <img
        alt=""
        className="shell-brand-logo shell-brand-logo--full"
        height={android ? 48 : 56}
        src={theme === 'light' ? BRAND_ASSETS.logoLight : BRAND_ASSETS.logoDark}
        width={android ? 175 : 220}
      />
      <img
        alt=""
        className="shell-brand-logo shell-brand-logo--icon"
        height={40}
        src={BRAND_ASSETS.logoIcon}
        width={40}
      />
    </span>
  )
}
