import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { hashCatalogue } from '../packages/localization/src/validation.ts'

const root = dirname(dirname(fileURLToPath(import.meta.url)))
const catalogueDir = join(root, 'packages/localization/src/catalogues')

const sharedUpdates = {
  'page.home.continue_practice': {
    'en-IN':
      'Open Learn to continue Introduction, Practice, Conversation, and Interaction.',
    'kn-IN':
      'ಪರಿಚಯ, ಅಭ್ಯಾಸ, ಸಂಭಾಷಣೆ ಮತ್ತು ಪರಸ್ಪರ ಕ್ರಿಯೆಯನ್ನು ಮುಂದುವರಿಸಲು ಕಲಿಯಿರಿ ತೆರೆಯಿರಿ.',
    'hi-IN':
      'परिचय, अभ्यास, बातचीत और संवाद जारी रखने के लिए सीखें खोलें।',
    'ta-IN':
      'அறிமுகம், பயிற்சி, உரையாடல், தொடர்பு ஆகியவற்றைத் தொடர கற்க திறக்கவும்.',
    'te-IN':
      'పరిచయం, అభ్యాసం, సంభాషణ, పరస్పర చర్య కొనసాగించడానికి నేర్చుకోండి తెరవండి.',
    'ml-IN':
      'ആമുഖം, പരിശീലനം, സംഭാഷണം, ഇടപെടൽ തുടരാൻ പഠിക്കുക തുറക്കുക.',
  },
  'page.learn.description': {
    'en-IN':
      'Follow Introduction, Practice, Conversation, and Interaction for your current level.',
    'kn-IN':
      'ನಿಮ್ಮ ಪ್ರಸ್ತುತ ಮಟ್ಟಕ್ಕೆ ಪರಿಚಯ, ಅಭ್ಯಾಸ, ಸಂಭಾಷಣೆ ಮತ್ತು ಪರಸ್ಪರ ಕ್ರಿಯೆಯನ್ನು ಅನುಸರಿಸಿ.',
    'hi-IN':
      'अपने वर्तमान स्तर के लिए परिचय, अभ्यास, बातचीत और संवाद का पालन करें।',
    'ta-IN':
      'உங்கள் தற்போதைய நிலைக்கு அறிமுகம், பயிற்சி, உரையாடல், தொடர்பு ஆகியவற்றைப் பின்பற்றுங்கள்.',
    'te-IN':
      'మీ ప్రస్తుత స్థాయికి పరిచయం, అభ్యాసం, సంభాషణ, పరస్పర చర్యను అనుసరించండి.',
    'ml-IN':
      'നിങ്ങളുടെ നിലവിലെ ലെവലിന് ആമുഖം, പരിശീലനം, സംഭാഷണം, ഇടപെടൽ പിന്തുടരുക.',
  },
  'demo.page.learn.description': {
    'en-IN':
      'Follow Introduction, Practice, Conversation, and Interaction for this level.',
    'kn-IN':
      'ಈ ಮಟ್ಟಕ್ಕೆ ಪರಿಚಯ, ಅಭ್ಯಾಸ, ಸಂಭಾಷಣೆ ಮತ್ತು ಪರಸ್ಪರ ಕ್ರಿಯೆಯನ್ನು ಅನುಸರಿಸಿ.',
    'hi-IN':
      'इस स्तर के लिए परिचय, अभ्यास, बातचीत और संवाद का पालन करें।',
    'ta-IN':
      'இந்த நிலைக்கு அறிமுகம், பயிற்சி, உரையாடல், தொடர்பு ஆகியவற்றைப் பின்பற்றுங்கள்.',
    'te-IN':
      'ఈ స్థాయికి పరిచయం, అభ్యాసం, సంభాషణ, పరస్పర చర్యను అనుసరించండి.',
    'ml-IN':
      'ഈ ലെവലിന് ആമുഖം, പരിശീലനം, സംഭാഷണം, ഇടപെടൽ പിന്തുടരുക.',
  },
  'demo.page.learn.title': {
    'en-IN': 'Learn',
    'kn-IN': 'ಕಲಿಯಿರಿ',
    'hi-IN': 'सीखें',
    'ta-IN': 'கற்க',
    'te-IN': 'నేర్చుకోండి',
    'ml-IN': 'പഠിക്കുക',
  },
}

const newKeys = {
  'demo.page.speak.description': {
    'en-IN':
      'Hold a short English exchange. Speak or type; hear Maya reply on this device.',
    'kn-IN':
      'ಸಣ್ಣ ಇಂಗ್ಲಿಷ್ ಸಂಭಾಷಣೆ ನಡೆಸಿ. ಮಾತನಾಡಿ ಅಥವಾ ಟೈಪ್ ಮಾಡಿ; ಈ ಸಾಧನದಲ್ಲಿ Maya ಉತ್ತರವನ್ನು ಕೇಳಿ.',
    'hi-IN':
      'छोटा अंग्रेज़ी संवाद करें। बोलें या टाइप करें; Maya का उत्तर इसी डिवाइस पर सुनें।',
    'ta-IN':
      'சிறு ஆங்கில உரையாடல் நடத்துங்கள். பேசுங்கள் அல்லது தட்டச்சு செய்யுங்கள்; Maya பதிலை இந்தச் சாதனத்தில் கேளுங்கள்.',
    'te-IN':
      'చిన్న ఆంగ్ల సంభాషణ చేయండి. మాట్లాడండి లేదా టైప్ చేయండి; Maya సమాధానం ఈ పరికరంలో వినండి.',
    'ml-IN':
      'ചെറിയ ഇംഗ്ലീഷ് സംഭാഷണം നടത്തുക. സംസാരിക്കുകയോ ടൈപ്പ് ചെയ്യുകയോ ചെയ്യുക; Maya മറുപടി ഈ ഉപകരണത്തിൽ കേൾക്കുക.',
  },
  'demo.page.speak.title': {
    'en-IN': 'Speak with Maya',
    'kn-IN': 'Maya ಜೊತೆ ಮಾತನಾಡಿ',
    'hi-IN': 'Maya से बोलें',
    'ta-IN': 'Maya உடன் பேசுங்கள்',
    'te-IN': 'Mayaతో మాట్లాడండి',
    'ml-IN': 'Mayaയോട് സംസാരിക്കുക',
  },
  'demo.page.tuition.description': {
    'en-IN':
      'Request live help and open the institute contact links. This demo does not place a call.',
    'kn-IN':
      'ನೇರ ಸಹಾಯವನ್ನು ವಿನಂತಿಸಿ ಮತ್ತು ಸಂಸ್ಥೆಯ ಸಂಪರ್ಕ ಕೊಂಡಿಗಳನ್ನು ತೆರೆಯಿರಿ. ಈ ಡೆಮೊ ಕರೆ ಮಾಡುವುದಿಲ್ಲ.',
    'hi-IN':
      'सीधी मदद का अनुरोध करें और संस्थान के संपर्क लिंक खोलें। यह डेमो कॉल नहीं करता।',
    'ta-IN':
      'நேரடி உதவியைக் கோரி நிறுவனத் தொடர்பு இணைப்புகளைத் திறக்கவும். இந்தச் செயல்விளக்கம் அழைப்பு செய்யாது.',
    'te-IN':
      'ప్రత్యక్ష సహాయం అభ్యర్థించి సంస్థ సంప్రదింపు లింక్‌లు తెరవండి. ఈ డెమో కాల్ చేయదు.',
    'ml-IN':
      'നേരിട്ടുള്ള സഹായം അഭ്യർത്ഥിച്ച് സ്ഥാപന ബന്ധ കണ്ണികൾ തുറക്കുക. ഈ ഡെമോ കോൾ ചെയ്യില്ല.',
  },
  'demo.page.tuition.title': {
    'en-IN': '1:1',
    'kn-IN': '1:1',
    'hi-IN': '1:1',
    'ta-IN': '1:1',
    'te-IN': '1:1',
    'ml-IN': '1:1',
  },
  'nav.speak': {
    'en-IN': 'Speak',
    'kn-IN': 'ಮಾತನಾಡಿ',
    'hi-IN': 'बोलें',
    'ta-IN': 'பேசு',
    'te-IN': 'మాట్లాడండి',
    'ml-IN': 'സംസാരിക്കുക',
  },
  'nav.tuition': {
    'en-IN': '1:1',
    'kn-IN': '1:1',
    'hi-IN': '1:1',
    'ta-IN': '1:1',
    'te-IN': '1:1',
    'ml-IN': '1:1',
  },
  'page.speak.description': {
    'en-IN':
      'Hold to talk with Maya. Choose everyday English or a situation.',
    'kn-IN':
      'Maya ಜೊತೆ ಮಾತನಾಡಲು ಬಟನ್ ಒತ್ತಿ ಹಿಡಿಯಿರಿ. ದಿನನಿತ್ಯದ ಇಂಗ್ಲಿಷ್ ಅಥವಾ ಒಂದು ಪರಿಸ್ಥಿತಿಯನ್ನು ಆಯ್ಕೆ ಮಾಡಿ.',
    'hi-IN':
      'Maya से बात करने के लिए बटन दबाए रखें। रोज़मर्रा की अंग्रेज़ी या एक स्थिति चुनें।',
    'ta-IN':
      'Maya உடன் பேச பொத்தானை அழுத்திப் பிடிக்கவும். அன்றாட ஆங்கிலம் அல்லது ஒரு சூழலைத் தேர்ந்தெடுக்கவும்.',
    'te-IN':
      'Mayaతో మాట్లాడటానికి బటన్ నొక్కి పట్టుకోండి. రోజువారీ ఆంగ్లం లేదా ఒక పరిస్థితిని ఎంచుకోండి.',
    'ml-IN':
      'Mayaയോട് സംസാരിക്കാൻ ബട്ടൺ അമർത്തിപ്പിടിക്കുക. ദൈനംദിന ഇംഗ്ലീഷ് അല്ലെങ്കിൽ ഒരു സാഹചര്യം തിരഞ്ഞെടുക്കുക.',
  },
  'page.speak.title': {
    'en-IN': 'Speak',
    'kn-IN': 'ಮಾತನಾಡಿ',
    'hi-IN': 'बोलें',
    'ta-IN': 'பேசு',
    'te-IN': 'మాట్లాడండి',
    'ml-IN': 'സംസാരിക്കുക',
  },
  'page.tuition.description': {
    'en-IN':
      'Request live speaking help and contact the institute. This is not a booking or a call in the app.',
    'kn-IN':
      'ನೇರ ಮಾತುಕತೆಯ ಸಹಾಯವನ್ನು ವಿನಂತಿಸಿ ಮತ್ತು ಸಂಸ್ಥೆಯನ್ನು ಸಂಪರ್ಕಿಸಿ. ಇದು ಬುಕಿಂಗ್ ಅಥವಾ ಅಪ್‌ನಲ್ಲಿನ ಕರೆ ಅಲ್ಲ.',
    'hi-IN':
      'सीधी बोलने की मदद का अनुरोध करें और संस्थान से संपर्क करें। यह बुकिंग या ऐप के अंदर कॉल नहीं है।',
    'ta-IN':
      'நேரடி பேச்சு உதவியைக் கோரி நிறுவனத்தைத் தொடர்பு கொள்ளுங்கள். இது முன்பதிவு அல்லது செயலியில் அழைப்பு அல்ல.',
    'te-IN':
      'ప్రత్యక్ష మాట్లాడే సహాయం అభ్యర్థించి సంస్థను సంప్రదించండి. ఇది బుకింగ్ లేదా యాప్‌లో కాల్ కాదు.',
    'ml-IN':
      'നേരിട്ടുള്ള സംസാര സഹായം അഭ്യർത്ഥിച്ച് സ്ഥാപനത്തെ ബന്ധപ്പെടുക. ഇത് ബുക്കിംഗോ ആപ്പിനുള്ളിലെ കോളോ അല്ല.',
  },
  'page.tuition.title': {
    'en-IN': '1:1',
    'kn-IN': '1:1',
    'hi-IN': '1:1',
    'ta-IN': '1:1',
    'te-IN': '1:1',
    'ml-IN': '1:1',
  },
  'tuition.contact_email': {
    'en-IN': 'Email the institute',
    'kn-IN': 'ಸಂಸ್ಥೆಗೆ ಇಮೇಲ್ ಮಾಡಿ',
    'hi-IN': 'संस्थान को ईमेल करें',
    'ta-IN': 'நிறுவனத்திற்கு மின்னஞ்சல் அனுப்புங்கள்',
    'te-IN': 'సంస్థకు ఇమెయిల్ చేయండి',
    'ml-IN': 'സ്ഥാപനത്തിന് ഇമെയിൽ അയയ്ക്കുക',
  },
  'tuition.contact_lead': {
    'en-IN':
      'You can also reach the institute directly. This is not a call inside the app.',
    'kn-IN':
      'ನೀವು ಸಂಸ್ಥೆಯನ್ನು ನೇರವಾಗಿ ಸಂಪರ್ಕಿಸಬಹುದು. ಇದು ಅಪ್‌ನಲ್ಲಿನ ಕರೆ ಅಲ್ಲ.',
    'hi-IN':
      'आप संस्थान से सीधे भी संपर्क कर सकते हैं। यह ऐप के अंदर कॉल नहीं है।',
    'ta-IN':
      'நிறுவனத்தை நேரடியாகவும் தொடர்பு கொள்ளலாம். இது செயலியில் அழைப்பு அல்ல.',
    'te-IN':
      'మీరు సంస్థను నేరుగా కూడా సంప్రదించవచ్చు. ఇది యాప్‌లో కాల్ కాదు.',
    'ml-IN':
      'നിങ്ങൾക്ക് സ്ഥാപനത്തെ നേരിട്ടും ബന്ധപ്പെടാം. ഇത് ആപ്പിനുള്ളിലെ കോൾ അല്ല.',
  },
  'tuition.contact_phone': {
    'en-IN': 'Call the institute',
    'kn-IN': 'ಸಂಸ್ಥೆಗೆ ಕರೆ ಮಾಡಿ',
    'hi-IN': 'संस्थान को कॉल करें',
    'ta-IN': 'நிறுவனத்தை அழைக்கவும்',
    'te-IN': 'సంస్థకు కాల్ చేయండి',
    'ml-IN': 'സ്ഥാപനത്തെ വിളിക്കുക',
  },
  'tuition.contact_whatsapp': {
    'en-IN': 'Message on WhatsApp',
    'kn-IN': 'WhatsApp ನಲ್ಲಿ ಸಂದೇಶ ಕಳುಹಿಸಿ',
    'hi-IN': 'WhatsApp पर संदेश भेजें',
    'ta-IN': 'WhatsApp-இல் செய்தி அனுப்புங்கள்',
    'te-IN': 'WhatsAppలో సందేశం పంపండి',
    'ml-IN': 'WhatsApp-ൽ സന്ദേശം അയയ്ക്കുക',
  },
}

const locales = ['en-IN', 'kn-IN', 'hi-IN', 'ta-IN', 'te-IN', 'ml-IN']

const hashes = {}

for (const locale of locales) {
  const path = join(catalogueDir, `${locale}.json`)
  const data = JSON.parse(readFileSync(path, 'utf8'))
  for (const [key, byLocale] of Object.entries(sharedUpdates)) {
    data[key] = byLocale[locale]
  }
  for (const [key, byLocale] of Object.entries(newKeys)) {
    data[key] = byLocale[locale]
  }
  writeFileSync(path, `${JSON.stringify(data, null, 2)}\n`)
  hashes[locale] = hashCatalogue(data)
}

const governancePath = join(catalogueDir, 'governance.json')
const governance = JSON.parse(readFileSync(governancePath, 'utf8'))
const sourceHash = hashes['en-IN']
const date = '2026-08-29.5'

for (const locale of locales) {
  const entry = governance.catalogues[locale]
  entry.revision = `ui-${locale}-${date}`
  entry.catalogueHash = hashes[locale]
  entry.sourceRevision = `ui-en-IN-${date}`
  entry.sourceHash = sourceHash
}

writeFileSync(governancePath, `${JSON.stringify(governance, null, 2)}\n`)
console.log(hashes)
