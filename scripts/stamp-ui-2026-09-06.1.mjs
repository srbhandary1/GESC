import { createHash } from 'node:crypto'
import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const catalogueDir = join(
  dirname(fileURLToPath(import.meta.url)),
  '../packages/localization/src/catalogues',
)
const locales = ['en-IN', 'kn-IN', 'hi-IN', 'ml-IN', 'ta-IN', 'te-IN']
const date = '2026-09-06.1'

const english = {
  'learner.audio.slow': 'Slow',
  'learner.audio.slow_status': 'Model audio is playing slowly.',
  'learner.home.review_interval': 'Due review. {days}-day interval.',
  'learner.onboarding.orientation':
    'You will listen first, then speak a short turn. Type instead is always available. Marks follow published rules, not AI.',
  'learner.progress.improvement_list': 'Improvement highlights',
  'learner.progress.loading': 'Loading your progress',
  'learner.progress.retry': 'Try again',
  'learner.progress.summary': 'Summary statistics',
  'marketing.loop.improve.body':
    'Try the same idea again. Marks follow published rules, not AI feeling.',
  'marketing.loop.improve.title': 'Improve',
  'marketing.loop.listen.body':
    'Hear a reviewed English line before you speak.',
  'marketing.loop.listen.title': 'Listen',
  'marketing.loop.speak.body':
    'Answer in a short turn. Hold to talk, or type instead if you need to.',
  'marketing.loop.speak.title': 'Speak',
  'marketing.loop.understand.body':
    'Hear one clear English fix, with Kannada when it helps.',
  'marketing.loop.understand.title': 'Understand',
  'marketing.outcome.customer.body':
    'Practise polite, clear answers for customer questions.',
  'marketing.outcome.customer.title': 'Customer conversations',
  'marketing.outcome.education.body':
    'Follow class talk and explain your ideas in English.',
  'marketing.outcome.education.title': 'Education',
  'marketing.outcome.everyday.body':
    'Use English in shops, travel and daily talk without freezing.',
  'marketing.outcome.everyday.title': 'Everyday confidence',
  'marketing.outcome.interview.body':
    'Rehearse interview answers you can actually use.',
  'marketing.outcome.interview.title': 'Interviews',
  'marketing.outcome.work.body':
    'Practise the English you need with colleagues and managers.',
  'marketing.outcome.work.title': 'Work conversations',
  'marketing.plan.demo.extra': 'Free start. GST exclusive.',
  'marketing.plan.demo.lead':
    '48 hours. Levels 1–2. Five live-AI minutes. Role-play included.',
  'marketing.plan.demo.name': 'Two-day demo',
  'marketing.plan.demo.price': '₹0',
  'marketing.plan.modules.extra': 'Role-play needs the Premium plan.',
  'marketing.plan.modules.lead':
    'One calendar month. All published levels. 40 live-AI minutes.',
  'marketing.plan.modules.name': 'Modules',
  'marketing.plan.modules.price': '₹350',
  'marketing.plan.premium.extra': 'Recommended. GST exclusive.',
  'marketing.plan.premium.lead':
    'One calendar month. All published levels. 90 live-AI minutes. Role-play included.',
  'marketing.plan.premium.name': 'Premium',
  'marketing.plan.premium.price': '₹500',
  'marketing.preview.controls': 'Preview Listen, Speak, Understand and Improve',
  'marketing.preview.type_instead':
    'Type instead if you do not want to record yet.',
  'marketing.trust.adult.body':
    'This course is for learners aged 18 or older.',
  'marketing.trust.adult.title': 'Adults only',
  'marketing.trust.offline.body':
    'Downloaded lessons stay usable without AI when the network is weak.',
  'marketing.trust.offline.title': 'Low-bandwidth and offline',
}

const kannada = {
  'learner.audio.slow': 'ನಿಧಾನ',
  'learner.audio.slow_status': 'ಮಾದರಿ ಆಡಿಯೋ ನಿಧಾನವಾಗಿ ಚಾಲನೆಯಲ್ಲಿದೆ.',
  'learner.home.review_interval': 'ಮರುಪರಿಶೀಲನೆ ಬಾಕಿ. {days}-ದಿನದ ಅಂತರ.',
  'learner.onboarding.orientation':
    'ಮೊದಲು ಕೇಳುತ್ತೀರಿ, ನಂತರ ಚಿಕ್ಕ ಉತ್ತರ ಮಾತನಾಡುತ್ತೀರಿ. ಬೇಕಾದರೆ ಟೈಪ್ ಮಾಡಬಹುದು. ಅಂಕಗಳು ಪ್ರಕಟಿತ ನಿಯಮಗಳನ್ನು ಅನುಸರಿಸುತ್ತವೆ, AI ಅಲ್ಲ.',
  'learner.progress.improvement_list': 'ಸುಧಾರಣೆಯ ಮುಖ್ಯಾಂಶಗಳು',
  'learner.progress.loading': 'ನಿಮ್ಮ ಪ್ರಗತಿ ಲೋಡ್ ಆಗುತ್ತಿದೆ',
  'learner.progress.retry': 'ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ',
  'learner.progress.summary': 'ಸಾರಾಂಶ ಅಂಕಿಅಂಶಗಳು',
  'marketing.loop.improve.body':
    'ಅದೇ ಕಲ್ಪನೆಯನ್ನು ಮತ್ತೆ ಪ್ರಯತ್ನಿಸಿ. ಅಂಕಗಳು ಪ್ರಕಟಿತ ನಿಯಮಗಳನ್ನು ಅನುಸರಿಸುತ್ತವೆ, AI ಭಾವನೆಯನ್ನು ಅಲ್ಲ.',
  'marketing.loop.improve.title': 'ಸುಧಾರಿಸಿ',
  'marketing.loop.listen.body':
    'ಮಾತನಾಡುವ ಮೊದಲು ಪರಿಶೀಲಿಸಿದ ಇಂಗ್ಲಿಷ್ ಸಾಲನ್ನು ಕೇಳಿ.',
  'marketing.loop.listen.title': 'ಕೇಳಿ',
  'marketing.loop.speak.body':
    'ಚಿಕ್ಕ ಉತ್ತರ ಹೇಳಿ. ಹಿಡಿದು ಮಾತನಾಡಿ, ಅಥವಾ ಬೇಕಾದರೆ ಟೈಪ್ ಮಾಡಿ.',
  'marketing.loop.speak.title': 'ಮಾತನಾಡಿ',
  'marketing.loop.understand.body':
    'ಒಂದು ಸ್ಪಷ್ಟ ಇಂಗ್ಲಿಷ್ ಸರಿಪಡಿಸುವಿಕೆ ಕೇಳಿ; ಅಗತ್ಯವಿದ್ದಾಗ ಕನ್ನಡದಲ್ಲಿ.',
  'marketing.loop.understand.title': 'ಅರ್ಥಮಾಡಿಕೊಳ್ಳಿ',
  'marketing.outcome.customer.body':
    'ಗ್ರಾಹಕ ಪ್ರಶ್ನೆಗಳಿಗೆ ಸೌಜನ್ಯದ, ಸ್ಪಷ್ಟ ಉತ್ತರಗಳನ್ನು ಅಭ್ಯಾಸ ಮಾಡಿ.',
  'marketing.outcome.customer.title': 'ಗ್ರಾಹಕ ಸಂಭಾಷಣೆ',
  'marketing.outcome.education.body':
    'ತರಗತಿಯ ಮಾತನ್ನು ಅನುಸರಿಸಿ ಮತ್ತು ನಿಮ್ಮ ಕಲ್ಪನೆಗಳನ್ನು ಇಂಗ್ಲಿಷ್‌ನಲ್ಲಿ ವಿವರಿಸಿ.',
  'marketing.outcome.education.title': 'ಶಿಕ್ಷಣ',
  'marketing.outcome.everyday.body':
    'ಅಂಗಡಿ, ಪ್ರಯಾಣ ಮತ್ತು ದೈನಂದಿನ ಮಾತಿನಲ್ಲಿ ಇಂಗ್ಲಿಷ್ ಬಳಸಿ — ಹೆದರದೆ.',
  'marketing.outcome.everyday.title': 'ದೈನಂದಿನ ಆತ್ಮವಿಶ್ವಾಸ',
  'marketing.outcome.interview.body':
    'ನಿಜವಾಗಿ ಬಳಸಬಹುದಾದ ಸಂದರ್ಶನ ಉತ್ತರಗಳನ್ನು ಅಭ್ಯಾಸ ಮಾಡಿ.',
  'marketing.outcome.interview.title': 'ಸಂದರ್ಶನಗಳು',
  'marketing.outcome.work.body':
    'ಸಹೋದ್ಯೋಗಿಗಳು ಮತ್ತು ಮ್ಯಾನೇಜರ್‌ಗಳೊಂದಿಗೆ ಬೇಕಾದ ಇಂಗ್ಲಿಷ್ ಅನ್ನು ಅಭ್ಯಾಸ ಮಾಡಿ.',
  'marketing.outcome.work.title': 'ಕೆಲಸದ ಸಂಭಾಷಣೆ',
  'marketing.plan.demo.extra': 'ಉಚಿತ ಆರಂಭ. GST ಹೊರತುಪಡಿಸಿ.',
  'marketing.plan.demo.lead':
    '48 ಗಂಟೆ. ಮಟ್ಟ 1–2. ಐದು ಲೈವ್-AI ನಿಮಿಷ. ರೋಲ್-ಪ್ಲೇ ಒಳಗೊಂಡಿದೆ.',
  'marketing.plan.demo.name': 'Two-day demo',
  'marketing.plan.demo.price': '₹0',
  'marketing.plan.modules.extra': 'ರೋಲ್-ಪ್ಲೇಗೆ Premium ಯೋಜನೆ ಬೇಕು.',
  'marketing.plan.modules.lead':
    'ಒಂದು ಕ್ಯಾಲೆಂಡರ್ ತಿಂಗಳು. ಪ್ರಕಟಿತ ಎಲ್ಲಾ ಮಟ್ಟಗಳು. 40 ಲೈವ್-AI ನಿಮಿಷ.',
  'marketing.plan.modules.name': 'Modules',
  'marketing.plan.modules.price': '₹350',
  'marketing.plan.premium.extra': 'ಶಿಫಾರಸು. GST ಹೊರತುಪಡಿಸಿ.',
  'marketing.plan.premium.lead':
    'ಒಂದು ಕ್ಯಾಲೆಂಡರ್ ತಿಂಗಳು. ಪ್ರಕಟಿತ ಎಲ್ಲಾ ಮಟ್ಟಗಳು. 90 ಲೈವ್-AI ನಿಮಿಷ. ರೋಲ್-ಪ್ಲೇ ಒಳಗೊಂಡಿದೆ.',
  'marketing.plan.premium.name': 'Premium',
  'marketing.plan.premium.price': '₹500',
  'marketing.preview.controls': 'ಕೇಳಿ, ಮಾತನಾಡಿ, ಅರ್ಥಮಾಡಿಕೊಳ್ಳಿ, ಸುಧಾರಿಸಿ — ಮುನ್ನೋಟ',
  'marketing.preview.type_instead':
    'ಇನ್ನೂ ರೆಕಾರ್ಡ್ ಮಾಡಬೇಕಿಲ್ಲದಿದ್ದರೆ ಟೈಪ್ ಮಾಡಿ.',
  'marketing.trust.adult.body':
    'ಈ ಕೋರ್ಸ್ 18 ವರ್ಷ ಅಥವಾ ಹೆಚ್ಚಿನ ವಯಸ್ಸಿನ ಕಲಿಯುವವರಿಗೆ.',
  'marketing.trust.adult.title': 'ವಯಸ್ಕರಿಗೆ ಮಾತ್ರ',
  'marketing.trust.offline.body':
    'ನೆಟ್‌ವರ್ಕ್ ದುರ್ಬಲವಾದಾಗ ಡೌನ್‌ಲೋಡ್ ಮಾಡಿದ ಪಾಠಗಳು AI ಇಲ್ಲದೆ ಬಳಸಬಹುದು.',
  'marketing.trust.offline.title': 'ಕಡಿಮೆ ಬ್ಯಾಂಡ್‌ವಿಡ್ತ್ ಮತ್ತು ಆಫ್‌ಲೈನ್',
}

function canonicalizeCatalogue(catalogue) {
  const canonicalEntries = Object.entries(catalogue).sort(([left], [right]) =>
    left < right ? -1 : left > right ? 1 : 0,
  )
  return JSON.stringify(Object.fromEntries(canonicalEntries))
}

function hashCatalogue(catalogue) {
  return `sha256:${createHash('sha256')
    .update(canonicalizeCatalogue(catalogue), 'utf8')
    .digest('hex')}`
}

const hashes = {}

for (const locale of locales) {
  const path = join(catalogueDir, `${locale}.json`)
  const data = JSON.parse(readFileSync(path, 'utf8'))
  const added = locale === 'kn-IN' ? kannada : english
  const merged = {}
  for (const [key, value] of Object.entries({ ...data, ...added })) {
    merged[key] = String(value).normalize('NFC')
  }
  const sorted = Object.fromEntries(
    Object.entries(merged).sort(([left], [right]) =>
      left < right ? -1 : left > right ? 1 : 0,
    ),
  )
  writeFileSync(path, `${JSON.stringify(sorted, null, 2)}\n`)
  hashes[locale] = hashCatalogue(sorted)
}

const governancePath = join(catalogueDir, 'governance.json')
const governance = JSON.parse(readFileSync(governancePath, 'utf8'))
const sourceHash = hashes['en-IN']

for (const locale of locales) {
  const entry = governance.catalogues[locale]
  entry.revision = `ui-${locale}-${date}`
  entry.catalogueHash = hashes[locale]
  entry.sourceRevision = `ui-en-IN-${date}`
  entry.sourceHash = sourceHash
}

writeFileSync(governancePath, `${JSON.stringify(governance, null, 2)}\n`)
console.log(hashes)
