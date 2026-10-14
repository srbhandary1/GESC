import { describe, expect, it } from 'vitest'
import { enINCatalogue, knINCatalogue } from '@global-english/localization'
import { CONCEPT_MESSAGE_KEYS } from './learnerLabelKeys'
import { resolveInterfaceMessage } from './interfaceLocalization'

describe('Kannada learner interface coverage', () => {
  it('provides Kannada descriptions for every configured concept without translating grammar tokens', () => {
    expect(Object.keys(CONCEPT_MESSAGE_KEYS)).toHaveLength(17)
    for (const key of Object.values(CONCEPT_MESSAGE_KEYS)) {
      const translated = resolveInterfaceMessage(key, 'kn-IN', 'development-preview')
      expect(translated.resolvedLocale).toBe('kn-IN')
      expect(translated.text).toMatch(/[\u0C80-\u0CFF]/u)
      expect(translated.text.split(' — ')[0]).toBe(enINCatalogue[key].split(' — ')[0])
    }
  })

  it('has no copied English learner interface messages except names, language autonyms, prices and abbreviations', () => {
    const intentional = new Set(['app.name', 'demo.page.tuition.title', 'locale.en_IN', 'locale.hi_IN', 'locale.kn_IN', 'locale.ml_IN', 'locale.ta_IN', 'locale.te_IN', 'marketing.plan.demo.price', 'marketing.plan.modules.price', 'marketing.plan.premium.price', 'marketing.stats.ai_value', 'marketing.stats.levels_value', 'nav.tuition', 'page.tuition.title', 'subscription.gst'])
    const copied = Object.entries(enINCatalogue).filter(([key, text]) => (!key.startsWith('operator.') || key.startsWith('operator.password.')) && !intentional.has(key) && knINCatalogue[key as keyof typeof knINCatalogue] === text).map(([key]) => key)
    expect(copied).toEqual([])
  })
})
