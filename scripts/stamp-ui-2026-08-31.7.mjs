import { createHash } from 'node:crypto'
import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

function hashCatalogue(catalogue) {
  const canonicalEntries = Object.entries(catalogue)
    .sort(([left], [right]) => (left < right ? -1 : 1))
    .map(([key, value]) => [key, value])
  return `sha256:${createHash('sha256')
    .update(JSON.stringify(Object.fromEntries(canonicalEntries)), 'utf8')
    .digest('hex')}`
}

const root = dirname(dirname(fileURLToPath(import.meta.url)))
const catalogueDir = join(root, 'packages/localization/src/catalogues')
const locales = ['en-IN', 'kn-IN', 'hi-IN', 'ta-IN', 'te-IN', 'ml-IN']

const updates = {
  'conversation.change_coach': {
    'en-IN': 'Change',
    'kn-IN': 'ಬದಲಾಯಿಸಿ',
    'hi-IN': 'बदलें',
    'ta-IN': 'மாற்று',
    'te-IN': 'మార్చండి',
    'ml-IN': 'മാറ്റുക',
  },
  'conversation.events_history': {
    'en-IN': 'Earlier turns',
    'kn-IN': 'ಹಿಂದಿನ ಸುತ್ತುಗಳು',
    'hi-IN': 'पिछली बारी',
    'ta-IN': 'முந்தைய முறைகள்',
    'te-IN': 'మునుపటి మలుపులు',
    'ml-IN': 'മുമ്പത്തെ ഊഴങ്ങൾ',
  },
  'conversation.memory.disclosure': {
    'en-IN': 'What {name} remembers',
    'kn-IN': '{name} ನೆನಪಿಟ್ಟಿರುವುದು',
    'hi-IN': '{name} क्या याद रखती/रखता है',
    'ta-IN': '{name} நினைவில் வைத்திருப்பது',
    'te-IN': '{name} గుర్తుంచుకున్నది',
    'ml-IN': '{name} ഓർത്തുവെച്ചത്',
  },
  'conversation.more_actions': {
    'en-IN': 'More',
    'kn-IN': 'ಇನ್ನಷ್ಟು',
    'hi-IN': 'और',
    'ta-IN': 'மேலும்',
    'te-IN': 'మరిన్ని',
    'ml-IN': 'കൂടുതൽ',
  },
  'conversation.practice_settings': {
    'en-IN': 'Practice settings',
    'kn-IN': 'ಅಭ್ಯಾಸ ಸೆಟ್ಟಿಂಗ್‌ಗಳು',
    'hi-IN': 'अभ्यास सेटिंग',
    'ta-IN': 'பயிற்சி அமைப்புகள்',
    'te-IN': 'ప్రాక్టీస్ సెట్టింగ్‌లు',
    'ml-IN': 'പരിശീലന ക്രമീകരണങ്ങൾ',
  },
  'conversation.roleplay.group.daily': {
    'en-IN': 'Daily life',
    'kn-IN': 'ದೈನಂದಿನ ಜೀವನ',
    'hi-IN': 'रोज़मर्रा',
    'ta-IN': 'தினசரி வாழ்க்கை',
    'te-IN': 'రోజువారీ జీవితం',
    'ml-IN': 'ദൈനംദിന ജീവിതം',
  },
  'conversation.roleplay.group.services': {
    'en-IN': 'Services',
    'kn-IN': 'ಸೇವೆಗಳು',
    'hi-IN': 'सेवाएँ',
    'ta-IN': 'சேவைகள்',
    'te-IN': 'సేవలు',
    'ml-IN': 'സേവനങ്ങൾ',
  },
  'conversation.roleplay.group.travel': {
    'en-IN': 'Travel',
    'kn-IN': 'ಪ್ರಯಾಣ',
    'hi-IN': 'यात्रा',
    'ta-IN': 'பயணம்',
    'te-IN': 'ప్రయాణం',
    'ml-IN': 'യാത്ര',
  },
  'conversation.roleplay.group.work': {
    'en-IN': 'Work',
    'kn-IN': 'ಕೆಲಸ',
    'hi-IN': 'काम',
    'ta-IN': 'வேலை',
    'te-IN': 'పని',
    'ml-IN': 'ജോലി',
  },
  'demo.page.me.description': {
    'en-IN': 'Progress, live help, account and language — in one place.',
    'kn-IN': 'ಪ್ರಗತಿ, ನೇರ ಸಹಾಯ, ಖಾತೆ ಮತ್ತು ಭಾಷೆ — ಎಲ್ಲವೂ ಒಂದೇ ಕಡೆ.',
    'hi-IN': 'प्रगति, सीधी मदद, खाता और भाषा — सब एक जगह।',
    'ta-IN': 'முன்னேற்றம், நேரடி உதவி, கணக்கு மற்றும் மொழி — அனைத்தும் ஒரே இடத்தில்.',
    'te-IN': 'పురోగతి, ప్రత్యక్ష సహాయం, ఖాతా మరియు భాష — అన్నీ ఒకే చోట.',
    'ml-IN': 'പുരോഗതി, നേരിട്ടുള്ള സഹായം, അക്കൗണ്ട്, ഭാഷ — എല്ലാം ഒരിടത്ത്.',
  },
  'demo.page.me.title': {
    'en-IN': 'Me',
    'kn-IN': 'ನಾನು',
    'hi-IN': 'मैं',
    'ta-IN': 'நான்',
    'te-IN': 'నేను',
    'ml-IN': 'ഞാൻ',
  },
  'learner.home.offline_summary': {
    'en-IN': 'Save for offline',
    'kn-IN': 'ಆಫ್‌ಲೈನ್‌ಗೆ ಉಳಿಸಿ',
    'hi-IN': 'ऑफ़लाइन सहेजें',
    'ta-IN': 'ஆஃப்லைனுக்குச் சேமி',
    'te-IN': 'ఆఫ్‌లైన్‌కు సేవ్ చేయండి',
    'ml-IN': 'ഓഫ്‌ലൈനിലേക്ക് സംരക്ഷിക്കുക',
  },
  'learner.onboarding.back': {
    'en-IN': 'Back',
    'kn-IN': 'ಹಿಂದೆ',
    'hi-IN': 'वापस',
    'ta-IN': 'பின்செல்',
    'te-IN': 'వెనుకకు',
    'ml-IN': 'തിരികെ',
  },
  'learner.onboarding.next': {
    'en-IN': 'Continue',
    'kn-IN': 'ಮುಂದುವರಿಸಿ',
    'hi-IN': 'जारी रखें',
    'ta-IN': 'தொடரவும்',
    'te-IN': 'కొనసాగించండి',
    'ml-IN': 'തുടരുക',
  },
  'learner.onboarding.step_coach': {
    'en-IN': 'Your coach',
    'kn-IN': 'ನಿಮ್ಮ ಕೋಚ್',
    'hi-IN': 'आपके कोच',
    'ta-IN': 'உங்கள் பயிற்சியாளர்',
    'te-IN': 'మీ కోచ్',
    'ml-IN': 'നിങ്ങളുടെ കോച്ച്',
  },
  'learner.onboarding.step_goal': {
    'en-IN': 'Your goal',
    'kn-IN': 'ನಿಮ್ಮ ಗುರಿ',
    'hi-IN': 'आपका लक्ष्य',
    'ta-IN': 'உங்கள் இலக்கு',
    'te-IN': 'మీ లక్ష్యం',
    'ml-IN': 'നിങ്ങളുടെ ലക്ഷ്യം',
  },
  'learner.onboarding.step_indicator': {
    'en-IN': 'Step {current} of {total}',
    'kn-IN': 'ಹಂತ {current} / {total}',
    'hi-IN': 'चरण {current} / {total}',
    'ta-IN': 'படி {current} / {total}',
    'te-IN': 'దశ {current} / {total}',
    'ml-IN': 'ഘട്ടം {current} / {total}',
  },
  'learner.onboarding.step_welcome': {
    'en-IN': 'Welcome',
    'kn-IN': 'ಸ್ವಾಗತ',
    'hi-IN': 'स्वागत',
    'ta-IN': 'வரவேற்பு',
    'te-IN': 'స్వాగతం',
    'ml-IN': 'സ്വാഗതം',
  },
  'learner.onboarding.welcome_body': {
    'en-IN': 'I am your English coach. First choose a goal, then we can start speaking.',
    'kn-IN': 'ನಾನು ನಿಮ್ಮ ಇಂಗ್ಲಿಷ್ ಕೋಚ್. ಮೊದಲು ಒಂದು ಗುರಿ ಆರಿಸಿ, ನಂತರ ಮಾತನಾಡಲು ಪ್ರಾರಂಭಿಸಬಹುದು.',
    'hi-IN': 'मैं आपका अंग्रेज़ी कोच हूँ। पहले एक लक्ष्य चुनें, फिर हम बोलना शुरू कर सकते हैं।',
    'ta-IN': 'நான் உங்கள் ஆங்கிலப் பயிற்சியாளர். முதலில் ஒரு இலக்கைத் தேர்ந்தெடுங்கள், பிறகு பேசத் தொடங்கலாம்.',
    'te-IN': 'నేను మీ ఇంగ్లీష్ కోచ్. ముందు ఒక లక్ష్యం ఎంచుకోండి, ఆపై మాట్లాడటం మొదలుపెట్టవచ్చు.',
    'ml-IN': 'ഞാൻ നിങ്ങളുടെ ഇംഗ്ലീഷ് കോച്ചാണ്. ആദ്യം ഒരു ലക്ഷ്യം തിരഞ്ഞെടുക്കുക, പിന്നെ സംസാരിക്കാം.',
  },
  'learner.onboarding.welcome_title': {
    'en-IN': 'Welcome',
    'kn-IN': 'ಸ್ವಾಗತ',
    'hi-IN': 'स्वागत है',
    'ta-IN': 'வரவேற்கிறோம்',
    'te-IN': 'స్వాగతం',
    'ml-IN': 'സ്വാഗതം',
  },
  'learner.progress.speaking_minutes': {
    'en-IN': '{minutes} minutes of speaking',
    'kn-IN': '{minutes} ನಿಮಿಷ ಮಾತನಾಡಿದ್ದೀರಿ',
    'hi-IN': '{minutes} मिनट बोला',
    'ta-IN': '{minutes} நிமிடம் பேச்சு',
    'te-IN': '{minutes} నిమిషాలు మాట్లాడారు',
    'ml-IN': '{minutes} മിനിറ്റ് സംസാരം',
  },
  'level.path.part_back': {
    'en-IN': 'Back to sessions',
    'kn-IN': 'ಸೆಷನ್‌ಗಳಿಗೆ ಹಿಂತಿರುಗಿ',
    'hi-IN': 'सत्रों पर वापस',
    'ta-IN': 'அமர்வுகளுக்குத் திரும்பு',
    'te-IN': 'సెషన్‌లకు తిరిగి',
    'ml-IN': 'സെഷനുകളിലേക്ക് മടങ്ങുക',
  },
  'level.path.sessions_done': {
    'en-IN': '{done} of 4 sessions done',
    'kn-IN': '4 ರಲ್ಲಿ {done} ಸೆಷನ್ ಮುಗಿದಿದೆ',
    'hi-IN': '4 में से {done} सत्र पूरे',
    'ta-IN': '4-இல் {done} அமர்வுகள் முடிந்தன',
    'te-IN': '4లో {done} సెషన్‌లు పూర్తి',
    'ml-IN': '4-ൽ {done} സെഷൻ പൂർത്തിയായി',
  },
  'tuition.form_open': {
    'en-IN': 'Request 1:1 help',
    'kn-IN': '1:1 ಸಹಾಯವನ್ನು ವಿನಂತಿಸಿ',
    'hi-IN': '1:1 मदद माँगें',
    'ta-IN': '1:1 உதவி கேளுங்கள்',
    'te-IN': '1:1 సహాయం అభ్యర్థించండి',
    'ml-IN': '1:1 സഹായം അഭ്യർത്ഥിക്കുക',
  },
}

const replacements = {
  'learner.progress.delayed_review_late': {
    'en-IN': '{count} reviews finished late',
    'kn-IN': '{count} ವಿಮರ್ಶೆಗಳು ತಡವಾಗಿ ಮುಗಿದವು',
    'hi-IN': '{count} समीक्षाएँ देर से पूरी हुईं',
    'ta-IN': '{count} மதிப்பாய்வுகள் தாமதமாக முடிந்தன',
    'te-IN': '{count} సమీక్షలు ఆలస్యంగా పూర్తయ్యాయి',
    'ml-IN': '{count} അവലോകനങ്ങൾ വൈകി പൂർത്തിയായി',
  },
  'learner.progress.delayed_review_ontime': {
    'en-IN': '{count} reviews done on time',
    'kn-IN': '{count} ವಿಮರ್ಶೆಗಳು ಸಮಯಕ್ಕೆ ಮುಗಿದವು',
    'hi-IN': '{count} समीक्षाएँ समय पर हुईं',
    'ta-IN': '{count} மதிப்பாய்வுகள் சரியான நேரத்தில்',
    'te-IN': '{count} సమీక్షలు సమయానికి పూర్తయ్యాయి',
    'ml-IN': '{count} അവലോകനങ്ങൾ സമയത്ത് പൂർത്തിയായി',
  },
  'learner.progress.empty.action': {
    'en-IN': "Start today's mission",
    'kn-IN': 'ಇಂದಿನ ಮಿಷನ್ ಪ್ರಾರಂಭಿಸಿ',
    'hi-IN': 'आज का मिशन शुरू करें',
    'ta-IN': 'இன்றைய பணியைத் தொடங்குங்கள்',
    'te-IN': 'నేటి మిషన్ ప్రారంభించండి',
    'ml-IN': 'ഇന്നത്തെ മിഷൻ ആരംഭിക്കുക',
  },
  'learner.progress.speaking_days': {
    'en-IN': '{count} days you practised speaking',
    'kn-IN': 'ನೀವು {count} ದಿನ ಮಾತನಾಡಿ ಅಭ್ಯಾಸ ಮಾಡಿದ್ದೀರಿ',
    'hi-IN': '{count} दिन आपने बोलने का अभ्यास किया',
    'ta-IN': 'நீங்கள் {count} நாள் பேசிப் பயின்றீர்கள்',
    'te-IN': 'మీరు {count} రోజులు మాట్లాడి అభ్యసించారు',
    'ml-IN': 'നിങ്ങൾ {count} ദിവസം സംസാരിച്ച് പരിശീലിച്ചു',
  },
}

const hashes = {}

for (const locale of locales) {
  const path = join(catalogueDir, `${locale}.json`)
  const data = JSON.parse(readFileSync(path, 'utf8'))
  for (const [key, byLocale] of Object.entries(updates)) {
    data[key] = byLocale[locale]
  }
  for (const [key, byLocale] of Object.entries(replacements)) {
    data[key] = byLocale[locale]
  }
  const sorted = Object.fromEntries(
    Object.entries(data).sort(([left], [right]) => left.localeCompare(right)),
  )
  writeFileSync(path, `${JSON.stringify(sorted, null, 2)}\n`)
  hashes[locale] = hashCatalogue(sorted)
}

const governancePath = join(catalogueDir, 'governance.json')
const governance = JSON.parse(readFileSync(governancePath, 'utf8'))
const sourceHash = hashes['en-IN']
const date = '2026-08-31.7'

for (const locale of locales) {
  const entry = governance.catalogues[locale]
  entry.revision = `ui-${locale}-${date}`
  entry.catalogueHash = hashes[locale]
  entry.sourceRevision = `ui-en-IN-${date}`
  entry.sourceHash = sourceHash
}

writeFileSync(governancePath, `${JSON.stringify(governance, null, 2)}\n`)
console.log(hashes)
