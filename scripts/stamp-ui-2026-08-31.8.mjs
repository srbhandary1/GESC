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

const replacements = {
  'conversation.confidence.progress': {
    'en-IN': 'Practice step',
    'kn-IN': 'ಅಭ್ಯಾಸ',
    'hi-IN': 'अभ्यास चरण',
    'ta-IN': 'பயிற்சிப் படி',
    'te-IN': 'ప్రాక్టీస్ దశ',
    'ml-IN': 'പരിശീലന ഘട്ടം',
  },
  'conversation.confidence.reflect_summary': {
    'en-IN':
      'You completed this speaking practice. AI feedback is advisory and does not change marks.',
    'kn-IN':
      'ನೀವು ಈ ಮಾತನಾಡುವ ಅಭ್ಯಾಸವನ್ನು ಪೂರ್ಣಗೊಳಿಸಿದ್ದೀರಿ. AI ಪ್ರತಿಕ್ರಿಯೆ ಸಲಹಾತ್ಮಕ ಮತ್ತು ಅಂಕಗಳನ್ನು ಬದಲಾಯಿಸುವುದಿಲ್ಲ.',
    'hi-IN':
      'आपने यह बोलने का अभ्यास पूरा किया। AI प्रतिक्रिया सलाहमात्र है और अंक नहीं बदलती।',
    'ta-IN':
      'இந்தப் பேச்சுப் பயிற்சியை முடித்தீர்கள். AI கருத்து ஆலோசனை மட்டும்; மதிப்பெண்களை மாற்றாது.',
    'te-IN':
      'మీరు ఈ మాట్లాడే ప్రాక్టీస్ పూర్తి చేశారు. AI అభిప్రాయం సలహా మాత్రమే, మార్కులు మారవు.',
    'ml-IN':
      'നിങ്ങൾ ഈ സംസാര പരിശീലനം പൂർത്തിയാക്കി. AI പ്രതികരണം ഉപദേശം മാത്രം; മാർക്ക് മാറില്ല.',
  },
  'conversation.confidence.skip': {
    'en-IN': 'Skip this practice',
    'kn-IN': 'ಈ ಅಭ್ಯಾಸವನ್ನು ಬಿಟ್ಟುಬಿಡಿ',
    'hi-IN': 'यह अभ्यास छोड़ें',
    'ta-IN': 'இந்தப் பயிற்சியைத் தவிர்',
    'te-IN': 'ఈ ప్రాక్టీస్ దాటవేయి',
    'ml-IN': 'ഈ പരിശീലനം ഒഴിവാക്കുക',
  },
  'conversation.demo.prototype_notice': {
    'en-IN':
      'Replies are scripted for this demo so the journey is reliable offline. Device speech plays and recognizes practice on this device only.',
    'kn-IN':
      'ಈ ಡೆಮೊದಲ್ಲಿ ಉತ್ತರಗಳು ಸ್ಕ್ರಿಪ್ಟ್ ಆಗಿವೆ. ಸಾಧನದ ಮಾತು ಈ ಸಾಧನದಲ್ಲೇ ಅಭ್ಯಾಸವನ್ನು ಗುರುತಿಸುತ್ತದೆ.',
    'hi-IN':
      'इस डेमो में उत्तर स्क्रिप्टेड हैं। डिवाइस वाणी यहीं अभ्यास पहचानती है।',
    'ta-IN':
      'இந்த டெமோவில் பதில்கள் எழுதப்பட்டவை. சாதனப் பேச்சு இங்கேயே பயிற்சியை அடையாளம் காணும்.',
    'te-IN':
      'ఈ డెమోలో సమాధానాలు స్క్రిప్టెడ్. పరికరం ఇక్కడే ప్రాక్టీస్‌ను గుర్తిస్తుంది.',
    'ml-IN':
      'ഈ ഡെമോയിൽ മറുപടികൾ സ്ക്രിപ്റ്റ് ചെയ്തതാണ്. ഉപകരണ സംസാരം ഇവിടെ മാത്രം പരിശീലനം തിരിച്ചറിയുന്നു.',
  },
  'conversation.demo.recognition_unavailable': {
    'en-IN':
      'Speech recognition is not available in this browser. Type your practice instead.',
    'kn-IN':
      'ಈ ಬ್ರೌಸರ್‌ನಲ್ಲಿ ಮಾತು ಗುರುತಿಸುವಿಕೆ ಲಭ್ಯವಿಲ್ಲ. ಬದಲಿಗೆ ಅಭ್ಯಾಸವನ್ನು ಟೈಪ್ ಮಾಡಿ.',
    'hi-IN':
      'इस ब्राउज़र में वाक् पहचान उपलब्ध नहीं है। अभ्यास टाइप करें।',
    'ta-IN':
      'இந்த உலாவியில் பேச்சு அடையாளம் இல்லை. பயிற்சியைத் தட்டச்சு செய்யுங்கள்.',
    'te-IN':
      'ఈ బ్రౌజర్‌లో స్పీచ్ రికగ్నిషన్ లేదు. ప్రాక్టీస్ టైప్ చేయండి.',
    'ml-IN':
      'ഈ ബ്രൗസറിൽ സംസാര തിരിച്ചറിയൽ ഇല്ല. പരിശീലനം ടൈപ്പ് ചെയ്യുക.',
  },
  'conversation.demo.speak_turn': {
    'en-IN': 'Speak your practice',
    'kn-IN': 'ನಿಮ್ಮ ಅಭ್ಯಾಸವನ್ನು ಮಾತನಾಡಿ',
    'hi-IN': 'अपना अभ्यास बोलें',
    'ta-IN': 'உங்கள் பயிற்சியைப் பேசுங்கள்',
    'te-IN': 'మీ ప్రాక్టీస్ మాట్లాడండి',
    'ml-IN': 'നിങ്ങളുടെ പരിശീലനം സംസാരിക്കുക',
  },
  'conversation.events_history': {
    'en-IN': 'Earlier practice',
    'kn-IN': 'ಹಿಂದಿನ ಅಭ್ಯಾಸ',
    'hi-IN': 'पिछला अभ्यास',
    'ta-IN': 'முந்தைய பயிற்சி',
    'te-IN': 'మునుపటి ప్రాక్టీస్',
    'ml-IN': 'മുമ്പത്തെ പരിശീലനം',
  },
  'conversation.events_title': {
    'en-IN': 'Practice status',
    'kn-IN': 'ಅಭ್ಯಾಸದ ಸ್ಥಿತಿ',
    'hi-IN': 'अभ्यास स्थिति',
    'ta-IN': 'பயிற்சி நிலை',
    'te-IN': 'ప్రాక్టీస్ స్థితి',
    'ml-IN': 'പരിശീലന നില',
  },
  'conversation.failed': {
    'en-IN': 'This practice could not be completed.',
    'kn-IN': 'ಈ ಅಭ್ಯಾಸವನ್ನು ಪೂರ್ಣಗೊಳಿಸಲಾಗಲಿಲ್ಲ.',
    'hi-IN': 'यह अभ्यास पूरा नहीं हो सका।',
    'ta-IN': 'இந்தப் பயிற்சியை முடிக்க முடியவில்லை.',
    'te-IN': 'ఈ ప్రాక్టీస్ పూర్తి కాలేదు.',
    'ml-IN': 'ഈ പരിശീലനം പൂർത്തിയാക്കാനായില്ല.',
  },
  'conversation.roleplay.advancing': {
    'en-IN': 'Continuing practice',
    'kn-IN': 'ಅಭ್ಯಾಸ ಮುಂದುವರಿಯುತ್ತಿದೆ',
    'hi-IN': 'अभ्यास जारी',
    'ta-IN': 'பயிற்சி தொடர்கிறது',
    'te-IN': 'ప్రాక్టీస్ కొనసాగుతోంది',
    'ml-IN': 'പരിശീലനം തുടരുന്നു',
  },
  'conversation.roleplay.privacy_notice': {
    'en-IN':
      'Practice progress is stored as digests and state only. Advisory feedback does not change marks.',
    'kn-IN':
      'ಅಭ್ಯಾಸದ ಪ್ರಗತಿ ಡೈಜೆಸ್ಟ್ ಮತ್ತು ಸ್ಥಿತಿ ಮಾತ್ರ. ಸಲಹಾತ್ಮಕ ಪ್ರತಿಕ್ರಿಯೆ ಅಂಕಗಳನ್ನು ಬದಲಾಯಿಸುವುದಿಲ್ಲ.',
    'hi-IN':
      'अभ्यास प्रगति केवल डाइजेस्ट और स्थिति के रूप में रखी जाती है। सलाह अंक नहीं बदलती।',
    'ta-IN':
      'பயிற்சி முன்னேற்றம் டைஜெஸ்ட் மற்றும் நிலையாக மட்டும் சேமிக்கப்படும். ஆலோசனை மதிப்பெண்களை மாற்றாது.',
    'te-IN':
      'ప్రాక్టీస్ పురోగతి డైజెస్ట్ మరియు స్థితి మాత్రమే. సలహా మార్కులు మార్చదు.',
    'ml-IN':
      'പരിശീലന പുരോഗതി ഡൈജസ്റ്റും നിലയും മാത്രം. ഉപദേശം മാർക്ക് മാറ്റില്ല.',
  },
  'conversation.roleplay.progress': {
    'en-IN': 'Practice step',
    'kn-IN': 'ಅಭ್ಯಾಸ',
    'hi-IN': 'अभ्यास चरण',
    'ta-IN': 'பயிற்சிப் படி',
    'te-IN': 'ప్రాక్టీస్ దశ',
    'ml-IN': 'പരിശീലന ഘട്ടം',
  },
  'conversation.send': {
    'en-IN': 'Send practice',
    'kn-IN': 'ಅಭ್ಯಾಸ ಕಳುಹಿಸಿ',
    'hi-IN': 'अभ्यास भेजें',
    'ta-IN': 'பயிற்சியை அனுப்பு',
    'te-IN': 'ప్రాక్టీస్ పంపండి',
    'ml-IN': 'പരിശീലനം അയയ്ക്കുക',
  },
  'conversation.sending': {
    'en-IN': 'Sending practice',
    'kn-IN': 'ಅಭ್ಯಾಸ ಕಳುಹಿಸಲಾಗುತ್ತಿದೆ',
    'hi-IN': 'अभ्यास भेजा जा रहा है',
    'ta-IN': 'பயிற்சி அனுப்பப்படுகிறது',
    'te-IN': 'ప్రాక్టీస్ పంపబడుతోంది',
    'ml-IN': 'പരിശീലനം അയയ്ക്കുന്നു',
  },
  'conversation.state.accepted': {
    'en-IN': 'Practice accepted',
    'kn-IN': 'ಅಭ್ಯಾಸ ಸ್ವೀಕರಿಸಲಾಗಿದೆ',
    'hi-IN': 'अभ्यास स्वीकार हुआ',
    'ta-IN': 'பயிற்சி ஏற்கப்பட்டது',
    'te-IN': 'ప్రాక్టీస్ ఆమోదించబడింది',
    'ml-IN': 'പരിശീലനം സ്വീകരിച്ചു',
  },
  'conversation.state.completed': {
    'en-IN': 'Practice completed',
    'kn-IN': 'ಅಭ್ಯಾಸ ಪೂರ್ಣಗೊಂಡಿದೆ',
    'hi-IN': 'अभ्यास पूरा हुआ',
    'ta-IN': 'பயிற்சி முடிந்தது',
    'te-IN': 'ప్రాక్టీస్ పూర్తయింది',
    'ml-IN': 'പരിശീലനം പൂർത്തിയായി',
  },
  'conversation.state.failed': {
    'en-IN': 'Practice failed',
    'kn-IN': 'ಅಭ್ಯಾಸ ವಿಫಲವಾಗಿದೆ',
    'hi-IN': 'अभ्यास विफल',
    'ta-IN': 'பயிற்சி தோல்வி',
    'te-IN': 'ప్రాక్టీస్ విఫలమైంది',
    'ml-IN': 'പരിശീലനം പരാജയപ്പെട്ടു',
  },
  'conversation.utterance_label': {
    'en-IN': 'Type one English practice sentence',
    'kn-IN': 'ಒಂದು ಇಂಗ್ಲಿಷ್ ಅಭ್ಯಾಸ ವಾಕ್ಯವನ್ನು ಟೈಪ್ ಮಾಡಿ',
    'hi-IN': 'एक अंग्रेज़ी अभ्यास वाक्य टाइप करें',
    'ta-IN': 'ஒரு ஆங்கிலப் பயிற்சி வாக்கியத்தைத் தட்டச்சு செய்யுங்கள்',
    'te-IN': 'ఒక ఇంగ్లీష్ ప్రాక్టీస్ వాక్యం టైప్ చేయండి',
    'ml-IN': 'ഒരു ഇംഗ്ലീഷ് പരിശീലന വാചകം ടൈപ്പ് ചെയ്യുക',
  },
  'conversation.voice.error': {
    'en-IN':
      'Microphone transcription is not available right now. You can type this practice if you ask to. Marks and progress do not require a recording.',
    'kn-IN':
      'ಮೈಕ್ರೊಫೋನ್ ಲಿಪ್ಯಂತರ ಈಗ ಲಭ್ಯವಿಲ್ಲ. ಕೇಳಿದರೆ ಈ ಅಭ್ಯಾಸವನ್ನು ಟೈಪ್ ಮಾಡಬಹುದು. ಅಂಕ ಮತ್ತು ಪ್ರಗತಿಗೆ ರೆಕಾರ್ಡಿಂಗ್ ಅಗತ್ಯವಿಲ್ಲ.',
    'hi-IN':
      'माइक्रोफ़ोन ट्रांसक्रिप्शन अभी उपलब्ध नहीं है। माँगने पर यह अभ्यास टाइप कर सकते हैं। अंक के लिए रिकॉर्डिंग जरूरी नहीं।',
    'ta-IN':
      'மைக்ரோஃபோன் எழுத்துப்பெயர்ப்பு இப்போது இல்லை. கேட்டால் இந்தப் பயிற்சியைத் தட்டச்சு செய்யலாம். மதிப்பெண்ணுக்குப் பதிவு தேவையில்லை.',
    'te-IN':
      'మైక్రోఫోన్ ట్రాన్స్‌క్రిప్షన్ ఇప్పుడు లేదు. అడిగితే ఈ ప్రాక్టీస్ టైప్ చేయవచ్చు. మార్కులకు రికార్డింగ్ అవసరం లేదు.',
    'ml-IN':
      'മൈക്രോഫോൺ ട്രാൻസ്ക്രിപ്ഷൻ ഇപ്പോൾ ലഭ്യമല്ല. ചോദിച്ചാൽ ഈ പരിശീലനം ടൈപ്പ് ചെയ്യാം. മാർക്കിന് റെക്കോർഡിംഗ് വേണ്ട.',
  },
  'conversation.voice.permission': {
    'en-IN':
      'Your browser asks for the microphone when you hold the button. Letting go sends this practice to Swathi.',
    'kn-IN':
      'ಬಟನ್ ಹಿಡಿದಾಗ ಬ್ರೌಸರ್ ಮೈಕ್ರೊಫೋನ್ ಕೇಳುತ್ತದೆ. ಬಿಟ್ಟರೆ ಈ ಅಭ್ಯಾಸ Swathi ಗೆ ಹೋಗುತ್ತದೆ.',
    'hi-IN':
      'बटन दबाने पर ब्राउज़र माइक्रोफ़ोन माँगता है। छोड़ने पर यह अभ्यास स्वाति को जाता है।',
    'ta-IN':
      'பொத்தானைப் பிடிக்கும்போது உலாவி மைக்ரோஃபோனைக் கேட்கும். விட்டால் இந்தப் பயிற்சி Swathiக்குச் செல்லும்.',
    'te-IN':
      'బటన్ పట్టుకున్నప్పుడు బ్రౌజర్ మైక్ అడుగుతుంది. వదిలితే ఈ ప్రాక్టీస్ Swathiకి వెళ్తుంది.',
    'ml-IN':
      'ബട്ടൺ അമർത്തുമ്പോൾ ബ്രൗസർ മൈക്ക് ചോദിക്കും. വിട്ടാൽ ഈ പരിശീലനം Swathi-യിലേക്ക് പോകും.',
  },
  'conversation.voice.playback_help': {
    'en-IN': 'Listen if practice failed. Hold again to retry, or type instead.',
    'kn-IN':
      'ಅಭ್ಯಾಸ ವಿಫಲವಾದರೆ ಕೇಳಿ. ಮತ್ತೆ ಹಿಡಿದು ಪ್ರಯತ್ನಿಸಿ, ಅಥವಾ ಟೈಪ್ ಮಾಡಿ.',
    'hi-IN':
      'अभ्यास असफल हो तो सुनें। फिर से दबाएँ, या टाइप करें।',
    'ta-IN':
      'பயிற்சி தோல்வியானால் கேளுங்கள். மீண்டும் பிடிக்கவும் அல்லது தட்டச்சு செய்யவும்.',
    'te-IN':
      'ప్రాక్టీస్ విఫలమైతే వినండి. మళ్లీ పట్టుకోండి, లేదా టైప్ చేయండి.',
    'ml-IN':
      'പരിശീലനം പരാജയപ്പെട്ടാൽ കേൾക്കുക. വീണ്ടും പിടിക്കുക, അല്ലെങ്കിൽ ടൈപ്പ് ചെയ്യുക.',
  },
  'learner.home.streak_label': {
    'en-IN': 'Speaking practice streak',
    'kn-IN': 'ಮಾತನಾಡುವ ಅಭ್ಯಾಸದ ಸರಣಿ',
    'hi-IN': 'बोलने के अभ्यास की लड़ी',
    'ta-IN': 'பேச்சுப் பயிற்சித் தொடர்',
    'te-IN': 'మాట్లాడే ప్రాక్టీస్ స్ట్రీక్',
    'ml-IN': 'സംസാര പരിശീലന ശ്രേണി',
  },
  'learner.home.streak_many': {
    'en-IN': '{count} days you practised speaking',
    'kn-IN': 'ನೀವು {count} ದಿನ ಮಾತನಾಡಿ ಅಭ್ಯಾಸ ಮಾಡಿದ್ದೀರಿ',
    'hi-IN': '{count} दिन आपने बोलने का अभ्यास किया',
    'ta-IN': 'நீங்கள் {count} நாள் பேசிப் பயின்றீர்கள்',
    'te-IN': 'మీరు {count} రోజులు మాట్లాడి అభ్యసించారు',
    'ml-IN': 'നിങ്ങൾ {count} ദിവസം സംസാരിച്ച് പരിശീലിച്ചു',
  },
  'learner.home.streak_one': {
    'en-IN': '1 day you practised speaking',
    'kn-IN': 'ನೀವು 1 ದಿನ ಮಾತನಾಡಿ ಅಭ್ಯಾಸ ಮಾಡಿದ್ದೀರಿ',
    'hi-IN': '1 दिन आपने बोलने का अभ्यास किया',
    'ta-IN': 'நீங்கள் 1 நாள் பேசிப் பயின்றீர்கள்',
    'te-IN': 'మీరు 1 రోజు మాట్లాడి అభ్యసించారు',
    'ml-IN': 'നിങ്ങൾ 1 ദിവസം സംസാരിച്ച് പരിശീലിച്ചു',
  },
}

const hashes = {}

for (const locale of locales) {
  const path = join(catalogueDir, `${locale}.json`)
  const data = JSON.parse(readFileSync(path, 'utf8'))
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
const date = '2026-08-31.8'

for (const locale of locales) {
  const entry = governance.catalogues[locale]
  entry.revision = `ui-${locale}-${date}`
  entry.catalogueHash = hashes[locale]
  entry.sourceRevision = `ui-en-IN-${date}`
  entry.sourceHash = sourceHash
}

writeFileSync(governancePath, `${JSON.stringify(governance, null, 2)}\n`)
console.log(hashes)
