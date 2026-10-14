<?php

declare(strict_types=1);

namespace App\Conversation\Dialogue;

use App\Conversation\Orchestration\PriorityCorrection;
use App\Conversation\Providers\ConversationModelRequest;
use App\Conversation\Providers\ConversationModelResult;
use App\Conversation\Providers\ProviderUsage;
use App\Conversation\Support\SyntheticL1Explanations;

/** Locked synthetic fixtures only, never selected as a live grammatical assessor. */
final class FakeDialogueResponder
{
    public function respond(ConversationModelRequest $request): ConversationModelResult
    {
        $help = ($request->dialogue['flow'] ?? '') === 'question_help';
        $local = ($request->dialogue['action'] ?? '') === 'accept_explanation';
        $locale = $local ? $request->uiLocale : 'en-IN';
        $assessment = 'unassessed';
        $correction = null;
        $evidence = [];
        $intent = $help ? 'question_answer' : 'roleplay_response';
        $text = mb_strtolower($request->transcript);
        $reply = 'We use the past tense to talk about something that happened before now.';
        if (($request->dialogue['flow'] ?? '') === 'free_speech' && ! $local) {
            $intent = 'everyday_conversation';
            $reply = 'That sounds interesting. What do you enjoy about it?';
        } elseif ($local) {
            $reply = SyntheticL1Explanations::for([
                'en-IN' => 'Past tense describes something that already happened.',
                'kn-IN' => 'ಈಗಾಗಲೇ ನಡೆದ ಕೆಲಸವನ್ನು ಹೇಳಲು ಭೂತಕಾಲವನ್ನು ಬಳಸುತ್ತೇವೆ.',
                'hi-IN' => 'जो काम पहले हो चुका है उसे बताने के लिए भूतकाल का उपयोग करते हैं।',
                'ml-IN' => 'നേരത്തെ നടന്ന കാര്യങ്ങൾ പറയാൻ ഭൂതകാലം ഉപയോഗിക്കുന്നു.',
                'ta-IN' => 'ஏற்கனவே நடந்த செயலைச் சொல்ல இறந்தகாலத்தைப் பயன்படுத்துகிறோம்.',
                'te-IN' => 'ఇప్పటికే జరిగిన పనిని చెప్పడానికి భూతకాలాన్ని ఉపయోగిస్తాము.',
            ], $locale);
        }
        if ($help && ! $local && is_array($request->dialogue['lesson'] ?? null)) {
            $reply = (string) $request->dialogue['lesson']['objective'];
        }
        if (! $help && ($request->dialogue['flow'] ?? '') !== 'free_speech') {
            if (str_contains($text, 'ignore previous') || str_contains($text, 'unsafe-fixture') || str_contains($text, 'banana spaceship')) {
                $reply = 'Please answer the current partner question in your own words.';
            } elseif (str_contains($text, 'i has')) {
                $assessment = 'retry_required';
                $reply = preg_replace('/\bi has\b/i', 'I have', $request->transcript) ?? 'I have an appointment.';
                $correction = new PriorityCorrection(
                    errorCategory: 'grammar.subject_verb', correctionEn: $reply,
                    explanationL1: SyntheticL1Explanations::for([
                        'en-IN' => 'Use have with I and try again.',
                        'kn-IN' => 'I ಜೊತೆಗೆ have ಬಳಸಿ ಮತ್ತೆ ಹೇಳಿ.',
                        'hi-IN' => 'I के साथ have लगाकर फिर कहें।',
                        'ml-IN' => 'I എന്നതിന് have ഉപയോഗിച്ച് വീണ്ടും പറയുക.',
                        'ta-IN' => 'I உடன் have பயன்படுத்தி மீண்டும் சொல்லவும்.',
                        'te-IN' => 'I తో have వాడి మళ్లీ చెప్పండి.',
                    ], $request->uiLocale), explanationLocale: $request->uiLocale, retryRecommended: true,
                );
            } else {
                $assessment = 'accepted';
                $reply = 'May I have your name, please?';
                $objectives = $request->dialogue['objectives'] ?? [];
                foreach (is_array($objectives) ? $objectives : [] as $objective) {
                    $key = is_array($objective) ? ($objective['key'] ?? '') : '';
                    if (is_string($key) && ((str_starts_with($key, 'greet-') && preg_match('/hello|good morning|good afternoon/i', $text))
                        || ($key === 'state-appointment-reason' && str_contains($text, 'appointment')))) {
                        $evidence[] = ['objective_key' => $key, 'state' => 'met'];
                    }
                }
            }
        }

        return new ConversationModelResult($intent, $reply,
            new ProviderUsage('deterministic-fake', 'fake-dialogue-v1', 1, 1, 1, 0, dispatchKind: 'local_execution'),
            priorityCorrection: $correction, replyLocale: $locale,
            dialogue: ['assessment' => $assessment, 'complete' => false, 'objective_evidence' => $evidence]);
    }
}
