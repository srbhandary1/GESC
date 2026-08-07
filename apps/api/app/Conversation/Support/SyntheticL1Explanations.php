<?php

declare(strict_types=1);

namespace App\Conversation\Support;

/**
 * Deterministic synthetic L1 explanations for fake providers and curated fallback.
 * Not approved curriculum; disclosed synthetic_unreviewed content.
 */
final class SyntheticL1Explanations
{
    /**
     * @return array<string, string>
     */
    public static function gentleClearerSentence(): array
    {
        return [
            'en-IN' => 'Say it more clearly as: "I enjoy learning English after work."',
            'kn-IN' => 'ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದಾದರೆ: "I enjoy learning English after work."',
            'hi-IN' => 'स्पष्ट रूप से कहें: "I enjoy learning English after work."',
            'ml-IN' => 'വ്യക്തമായി പറയുക: "I enjoy learning English after work."',
            'ta-IN' => 'தெளிவாகச் சொல்லுங்கள்: "I enjoy learning English after work."',
            'te-IN' => 'స్పష్టంగా చెప్పండి: "I enjoy learning English after work."',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tutorSaySentence(): array
    {
        return [
            'en-IN' => 'Say: "I enjoy learning English after work."',
            'kn-IN' => 'ಹೀಗೆ ಹೇಳಿ: "I enjoy learning English after work."',
            'hi-IN' => 'इस तरह कहें: "I enjoy learning English after work."',
            'ml-IN' => 'ഇങ്ങനെ പറയുക: "I enjoy learning English after work."',
            'ta-IN' => 'இப்படிச் சொல்லுங்கள்: "I enjoy learning English after work."',
            'te-IN' => 'ఇలా చెప్పండి: "I enjoy learning English after work."',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function fluencyOneIdea(): array
    {
        return [
            'en-IN' => 'Keep one idea per sentence so the flow stays smooth.',
            'kn-IN' => 'ಹರಿವು ಸುಗಮವಾಗಿರಲು ಒಂದು ವಾಕ್ಯದಲ್ಲಿ ಒಂದೇ ಆಲೋಚನೆ ಇರಿಸಿ.',
            'hi-IN' => 'प्रवाह सहज रखने के लिए एक वाक्य में एक ही विचार रखें।',
            'ml-IN' => 'ഒഴുക്ക് സുഗമമാക്കാൻ ഒരു വാക്യത്തിൽ ഒരു ആശയം മാത്രം വയ്ക്കുക.',
            'ta-IN' => 'ஓட்டம் இயல்பாக இருக்க ஒரு வாக்கியத்தில் ஒரு எண்ணமே இருக்கட்டும்.',
            'te-IN' => 'ప్రవాహం సాఫీగా ఉండేలా ఒక వాక్యంలో ఒకే ఆలోచన ఉంచండి.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function safetyFallback(): array
    {
        return [
            'en-IN' => 'Please try one short, clear English sentence.',
            'kn-IN' => 'ದಯವಿಟ್ಟು ಒಂದು ಸಣ್ಣ, ಸ್ಪಷ್ಟ ಇಂಗ್ಲಿಷ್ ವಾಕ್ಯ ಹೇಳಿ.',
            'hi-IN' => 'कृपया एक छोटा, स्पष्ट अंग्रेज़ी वाक्य बोलें।',
            'ml-IN' => 'ദയവായി ഒരു ചെറിയ, വ്യക്തമായ ഇംഗ്ലീഷ് വാക്യം പറയുക.',
            'ta-IN' => 'தயவுசெய்து ஒரு சிறிய, தெளிவான ஆங்கில வாக்கியம் சொல்லுங்கள்.',
            'te-IN' => 'దయచేసి ఒక చిన్న, స్పష్టమైన ఇంగ్లీష్ వాక్యం చెప్పండి.',
        ];
    }

    /**
     * @param  array<string, string>  $byLocale
     */
    public static function for(
        array $byLocale,
        string $uiLocale,
    ): string {
        return $byLocale[$uiLocale]
            ?? $byLocale[ConversationUiLocales::default()]
            ?? $byLocale['en-IN']
            ?? 'Please try one short, clear English sentence.';
    }
}
