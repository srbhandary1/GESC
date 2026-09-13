<?php

declare(strict_types=1);

namespace App\Curriculum\LessonPractice;

use InvalidArgumentException;

/**
 * Authored Levels 2–17 lesson-practice copy (APP-0065).
 *
 * English academic review is recorded under APP-0066. Kannada review of the
 * authored summaries is recorded under APP-0067. Wave 7.1 publishes these
 * blueprints for entitled Direct learners via DirectPathwayPublicationService.
 */
final class AuthoredFoundationLessons
{
    public const string APPROVAL = 'APP-0065';

    public const string ENGLISH_REVIEW_APPROVAL = 'APP-0066';

    public const string KANNADA_REVIEW_APPROVAL = 'APP-0067';

    /**
     * @param  array<string, array{
     *   media_asset_id: string,
     *   metadata_revision_id: string,
     *   metadata_content_hash: string,
     *   rights_revision_id?: string
     * }>  $assets
     * @return array<string, mixed>
     */
    public static function blueprint(
        int $levelNumber,
        string $conceptKey,
        array $assets,
    ): array {
        $lesson = self::lessons()[$conceptKey] ?? null;
        if ($lesson === null || (int) $lesson['level_number'] !== $levelNumber) {
            throw new InvalidArgumentException(
                'authored_lesson_missing:'.$levelNumber.':'.$conceptKey,
            );
        }

        $parts = [];
        $order = 1;
        foreach (['introduction', 'practice', 'conversation', 'interaction'] as $partId) {
            $asset = $assets[$partId] ?? null;
            if ($asset === null) {
                throw new InvalidArgumentException('authored_asset_missing:'.$partId);
            }
            $parts[] = [
                'order' => $order,
                'part_id' => $partId,
                'label' => ucfirst($partId),
                'media_asset_id' => $asset['media_asset_id'],
                'metadata_revision_id' => $asset['metadata_revision_id'],
                'metadata_content_hash' => $asset['metadata_content_hash'],
            ];
            $order++;
        }

        return [
            'schema' => 'global-english.lesson-practice-loop.v1',
            'blueprint_id' => sprintf(
                'lpl01-level%d-%s-app0065-authored-v1',
                $levelNumber,
                $conceptKey,
            ),
            'state' => 'published_direct_entitled_v1',
            'authorship' => 'app0065_authored_english_app0066_kannada_app0067',
            'approval_reference' => self::APPROVAL,
            'english_review_reference' => self::ENGLISH_REVIEW_APPROVAL,
            'kannada_review_reference' => self::KANNADA_REVIEW_APPROVAL,
            'lesson' => [
                'level_label' => 'Level '.$levelNumber,
                'concept_key' => $conceptKey,
                'objective' => $lesson['objective'],
                'prerequisite_objective_ids' => [],
                'communication_function' => $lesson['communication_function'],
                'target_patterns' => $lesson['target_patterns'],
                'misconception_codes' => $lesson['misconception_codes'],
            ],
            'ordered_parts' => $parts,
            'talk' => $lesson['talk'],
            'check' => $lesson['check'],
            'apply' => $lesson['apply'],
            'kannada_guidance' => [
                'summary' => $lesson['kannada_summary'],
                'independent_review' => true,
                'approval_reference' => self::KANNADA_REVIEW_APPROVAL,
            ],
            'review' => [
                'english_academic' => [
                    'status' => 'approved',
                    'authority' => 'APP-0066_human_english_academic_review',
                    'scope' => 'private_pilot_english_and_kannada_named_reviewed',
                ],
                'kannada' => [
                    'status' => 'approved',
                    'authority' => 'APP-0067_human_kannada_review',
                    'scope' => 'private_pilot_kannada_named_reviewed',
                ],
            ],
            'publication' => [
                'eligible' => true,
                'blockers' => [],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function conceptKeys(): array
    {
        return array_keys(self::lessons());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function lessons(): array
    {
        return [
            'should' => self::should(),
            'do_does' => self::doDoes(),
            'is_are_ing' => self::isAreIng(),
            'did' => self::did(),
            'was_were_ing' => self::wasWereIng(),
            'have_has' => self::haveHas(),
            'have_been_has_been' => self::haveBeenHasBeen(),
            'had' => self::had(),
            'will' => self::will(),
            'gonna_wanna' => self::gonnaWanna(),
            'would' => self::would(),
            'may' => self::may(),
            'if' => self::ifClause(),
            'must' => self::must(),
            'should_have' => self::shouldHave(),
            'used_to' => self::usedTo(),
        ];
    }

    /** @return array<string, mixed> */
    private static function should(): array
    {
        return [
            'level_number' => 2,
            'objective' => 'By the end of this lesson, the learner can use should and should not to give one everyday suggestion and ask for simple advice.',
            'communication_function' => 'advice_and_suggestion',
            'target_patterns' => [
                'I should + base verb.',
                'You should not + base verb.',
                'Should I + base verb?',
                'Yes, you should. / No, you should not.',
            ],
            'misconception_codes' => ['SHOULD_BASE_VERB', 'SHOULD_TO_INFINITIVE'],
            'talk' => [
                'scenario' => 'A colleague looks tired after work. Suggest one healthy action and ask one Should I…? question.',
                'elicitation_goals' => [
                    'Give one suggestion with You should + base verb.',
                    'Ask one Should I…? question.',
                    'Answer with Yes, you should or No, you should not.',
                ],
                'fallback_turns' => [
                    'You should drink some water.',
                    'Should I rest now?',
                    'Yes, you should.',
                ],
            ],
            'check' => self::check('should', [
                [
                    'Choose the correct suggestion.',
                    ['You should rest tonight.', 'You should to rest tonight.', 'You should resting tonight.'],
                    'Should is followed by the base verb rest.',
                ],
                [
                    'Choose the advice question.',
                    ['Should I call her now?', 'I should call her now?', 'Should I calling her now?'],
                    'Advice questions use Should I + base verb.',
                ],
                [
                    'Choose a suitable short reply to Should I wait?',
                    ['No, you should not.', 'No, you should not to.', 'No, you should not waiting.'],
                    'A short answer uses should not without to or -ing.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: give one should suggestion, ask one Should I…? question, and answer it.',
                [
                    'Uses one accurate should suggestion',
                    'Forms one Should I…? question',
                    'Gives a relevant yes/no advice answer',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ should ಮತ್ತು should not ಬಳಸಿ ಸರಳ ಸಲಹೆ ಕೊಡುವುದು ಮತ್ತು Should I…? ಎಂದು ಕೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. should ನಂತರ ಮೂಲ ಕ್ರಿಯಾಪದ ಬರುತ್ತದೆ.',
        ];
    }

    /** @return array<string, mixed> */
    private static function doDoes(): array
    {
        return [
            'level_number' => 3,
            'objective' => 'By the end of this lesson, the learner can use do and does to ask and answer simple present questions about everyday habits.',
            'communication_function' => 'present_simple_questions',
            'target_patterns' => [
                'Do you + base verb?',
                'Does he/she + base verb?',
                'Yes, I do. / No, I do not.',
                'Yes, she does. / No, he does not.',
            ],
            'misconception_codes' => ['DOES_BASE_VERB', 'DO_DOES_PERSON'],
            'talk' => [
                'scenario' => 'You meet a neighbour. Ask about one daily habit and answer one Do you…? question.',
                'elicitation_goals' => [
                    'Ask one Do you…? question.',
                    'Ask or answer one Does he/she…? question.',
                    'Give a short Yes, I do / No, I do not reply.',
                ],
                'fallback_turns' => [
                    'Do you drink tea in the morning?',
                    'Yes, I do.',
                    'Does she work nearby?',
                ],
            ],
            'check' => self::check('do_does', [
                [
                    'Choose the correct question.',
                    ['Do you live here?', 'Does you live here?', 'Do you lives here?'],
                    'With you, use Do + base verb.',
                ],
                [
                    'Choose the correct question about a colleague.',
                    ['Does she start at nine?', 'Do she start at nine?', 'Does she starts at nine?'],
                    'With she, use Does + base verb.',
                ],
                [
                    'Choose a suitable short reply to Do you cook at home?',
                    ['Yes, I do.', 'Yes, I does.', 'Yes, I do cooks.'],
                    'Short answers keep do or does without repeating the full verb form incorrectly.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: ask one Do you…? question, one Does he/she…? question, and give one short do/does answer.',
                [
                    'Uses Do you + base verb',
                    'Uses Does + he/she + base verb',
                    'Gives a short do/does answer',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ do ಮತ್ತು does ಬಳಸಿ ದಿನನಿತ್ಯದ ಅಭ್ಯಾಸಗಳ ಬಗ್ಗೆ ಪ್ರಶ್ನೆ ಕೇಳಿ ಉತ್ತರಿಸುವುದನ್ನು ಕಲಿಯಿರಿ. you ಜೊತೆ Do; he/she ಜೊತೆ Does.',
        ];
    }

    /** @return array<string, mixed> */
    private static function isAreIng(): array
    {
        return [
            'level_number' => 4,
            'objective' => 'By the end of this lesson, the learner can use am/is/are + -ing to say what someone is doing now and ask one present-continuous question.',
            'communication_function' => 'present_continuous_now',
            'target_patterns' => [
                'I am + -ing.',
                'She/He is + -ing.',
                'They are + -ing.',
                'Are you + -ing?',
            ],
            'misconception_codes' => ['BE_ING_MISSING', 'ING_WITH_DO'],
            'talk' => [
                'scenario' => 'You are in a busy office. Say what you are doing now and ask a colleague what they are doing.',
                'elicitation_goals' => [
                    'State one current action with am/is/are + -ing.',
                    'Ask one Are you…-ing? question.',
                    'Answer with Yes, I am or No, I am not.',
                ],
                'fallback_turns' => [
                    'I am waiting for a call.',
                    'Are you writing the report?',
                    'Yes, I am.',
                ],
            ],
            'check' => self::check('is_are_ing', [
                [
                    'Choose the sentence about now.',
                    ['She is talking to a customer.', 'She talks to a customer nowing.', 'She is talk to a customer.'],
                    'Present continuous uses is + talking.',
                ],
                [
                    'Choose the correct question.',
                    ['Are you waiting here?', 'Do you waiting here?', 'Is you waiting here?'],
                    'With you, use Are you + -ing.',
                ],
                [
                    'Choose a suitable short reply to Are you leaving?',
                    ['No, I am not.', 'No, I are not.', 'No, I not leaving am.'],
                    'Short answers keep am/is/are.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: say what you are doing now, ask one Are you…-ing? question, and answer it.',
                [
                    'Uses am/is/are + -ing for a current action',
                    'Forms one Are you…-ing? question',
                    'Gives a relevant short answer',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ ಈಗ ನಡೆಯುತ್ತಿರುವ ಕೆಲಸವನ್ನು am/is/are + -ing ರೂಪದಲ್ಲಿ ಹೇಳುವುದು ಮತ್ತು Are you…? ಎಂದು ಕೇಳುವುದನ್ನು ಕಲಿಯಿರಿ.',
        ];
    }

    /** @return array<string, mixed> */
    private static function did(): array
    {
        return [
            'level_number' => 5,
            'objective' => 'By the end of this lesson, the learner can use did to ask and answer simple past questions about yesterday’s actions.',
            'communication_function' => 'past_simple_questions',
            'target_patterns' => [
                'Did you + base verb?',
                'I + past verb yesterday.',
                'Yes, I did. / No, I did not.',
                'What did you + base verb?',
            ],
            'misconception_codes' => ['DID_PAST_VERB', 'DID_BASE_VERB'],
            'talk' => [
                'scenario' => 'A friend asks about yesterday. Talk about one past action and ask one Did you…? question.',
                'elicitation_goals' => [
                    'Ask one Did you + base verb? question.',
                    'Answer with Yes, I did or No, I did not.',
                    'State one finished action with a past verb.',
                ],
                'fallback_turns' => [
                    'Did you go to the market?',
                    'Yes, I did.',
                    'I cooked dinner.',
                ],
            ],
            'check' => self::check('did', [
                [
                    'Choose the correct past question.',
                    ['Did you finish the work?', 'Did you finished the work?', 'Do you finished the work?'],
                    'After did, use the base verb finish.',
                ],
                [
                    'Choose the correct statement about yesterday.',
                    ['I called my sister yesterday.', 'I did called my sister yesterday.', 'I call my sister yesterday.'],
                    'A past statement uses the past form, not did + past.',
                ],
                [
                    'Choose a suitable short reply to Did you rest?',
                    ['No, I did not.', 'No, I did not rested.', 'No, I not did.'],
                    'Short answers use did / did not.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: ask one Did you…? question, give a short did answer, and state one past action.',
                [
                    'Uses Did you + base verb',
                    'Gives a short did / did not answer',
                    'States one past action',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ ನಿನ್ನೆಯ ಕೆಲಸದ ಬಗ್ಗೆ Did you…? ಎಂದು ಕೇಳಿ, did / did not ಎಂದು ಉತ್ತರಿಸಿ, ಒಂದು ಭೂತಕಾಲದ ವಾಕ್ಯ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. did ನಂತರ ಮೂಲ ಕ್ರಿಯಾಪದ.',
        ];
    }

    /** @return array<string, mixed> */
    private static function wasWereIng(): array
    {
        return [
            'level_number' => 6,
            'objective' => 'By the end of this lesson, the learner can use was/were + -ing to say what was happening at a time in the past.',
            'communication_function' => 'past_continuous',
            'target_patterns' => [
                'I was + -ing.',
                'They were + -ing.',
                'Were you + -ing?',
                'I was not + -ing.',
            ],
            'misconception_codes' => ['WAS_WERE_PERSON', 'PAST_CONTINUOUS_ING'],
            'talk' => [
                'scenario' => 'A power cut happened at 8 p.m. Say what you were doing then and ask a partner what they were doing.',
                'elicitation_goals' => [
                    'State one past-in-progress action with was/were + -ing.',
                    'Ask one Were you…-ing? question.',
                    'Answer with Yes, I was or No, I was not.',
                ],
                'fallback_turns' => [
                    'I was cooking at eight.',
                    'Were you watching TV?',
                    'No, I was not.',
                ],
            ],
            'check' => self::check('was_were_ing', [
                [
                    'Choose the sentence about a past moment.',
                    ['She was waiting at the bus stop.', 'She were waiting at the bus stop.', 'She was wait at the bus stop.'],
                    'With she, use was + waiting.',
                ],
                [
                    'Choose the correct question.',
                    ['Were you working then?', 'Was you working then?', 'Were you work then?'],
                    'With you, use Were you + -ing.',
                ],
                [
                    'Choose a suitable short reply to Were you sleeping?',
                    ['Yes, I was.', 'Yes, I were.', 'Yes, I was sleepinging.'],
                    'Short answers keep was/were.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: say what you were doing at a past time, ask one Were you…-ing? question, and answer it.',
                [
                    'Uses was/were + -ing',
                    'Forms one Were you…-ing? question',
                    'Gives a relevant short answer',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ ಹಿಂದಿನ ಒಂದು ಸಮಯದಲ್ಲಿ ನಡೆಯುತ್ತಿದ್ದ ಕೆಲಸವನ್ನು was/were + -ing ರೂಪದಲ್ಲಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. I/he/she ಜೊತೆ was; you/we/they ಜೊತೆ were.',
        ];
    }

    /** @return array<string, mixed> */
    private static function haveHas(): array
    {
        return [
            'level_number' => 7,
            'objective' => 'By the end of this lesson, the learner can use have/has + past participle to talk about life experience and recent finished actions with a present result.',
            'communication_function' => 'present_perfect_experience',
            'target_patterns' => [
                'I have + past participle.',
                'She has + past participle.',
                'Have you + past participle?',
                'Yes, I have. / No, I have not.',
            ],
            'misconception_codes' => ['HAVE_PAST_SIMPLE', 'HAS_HAVE_PERSON'],
            'talk' => [
                'scenario' => 'You are talking about travel and work experience. Share one experience and ask Have you ever…?',
                'elicitation_goals' => [
                    'State one experience with I have + past participle.',
                    'Ask one Have you…? question.',
                    'Answer with Yes, I have or No, I have not.',
                ],
                'fallback_turns' => [
                    'I have visited Mysuru.',
                    'Have you tried this tea?',
                    'Yes, I have.',
                ],
            ],
            'check' => self::check('have_has', [
                [
                    'Choose the experience sentence.',
                    ['I have finished the form.', 'I have finish the form.', 'I has finished the form.'],
                    'With I, use have + past participle.',
                ],
                [
                    'Choose the correct question.',
                    ['Have you seen this message?', 'Has you seen this message?', 'Have you saw this message?'],
                    'Have you is followed by the past participle seen.',
                ],
                [
                    'Choose a suitable short reply to Have you eaten?',
                    ['No, I have not.', 'No, I has not.', 'No, I have not ate.'],
                    'Short answers use have / have not.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: state one I have… experience, ask Have you…?, and give a short have/have not answer.',
                [
                    'Uses have/has + past participle',
                    'Forms one Have you…? question',
                    'Gives a short have answer',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ ಅನುಭವ ಅಥವಾ ಈಗಲೂ ಸಂಬಂಧವಿರುವ ಕೆಲಸವನ್ನು have/has + past participle ರೂಪದಲ್ಲಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. I/you/we/they ಜೊತೆ have; he/she ಜೊತೆ has.',
        ];
    }

    /** @return array<string, mixed> */
    private static function haveBeenHasBeen(): array
    {
        return [
            'level_number' => 8,
            'objective' => 'By the end of this lesson, the learner can use have been / has been + -ing to say an action started in the past and is still continuing.',
            'communication_function' => 'present_perfect_continuous',
            'target_patterns' => [
                'I have been + -ing.',
                'She has been + -ing.',
                'Have you been + -ing?',
                'How long have you been + -ing?',
            ],
            'misconception_codes' => ['HAVE_BEEN_ING', 'SINCE_FOR_MIX'],
            'talk' => [
                'scenario' => 'A trainer asks how long you have been practising English. Answer with have been + -ing and ask one How long…? question.',
                'elicitation_goals' => [
                    'State a continuing action with have been + -ing.',
                    'Ask How long have you been…-ing?',
                    'Use for or since with a time.',
                ],
                'fallback_turns' => [
                    'I have been practising for two weeks.',
                    'How long have you been waiting?',
                    'She has been working since morning.',
                ],
            ],
            'check' => self::check('have_been_has_been', [
                [
                    'Choose the continuing-action sentence.',
                    ['I have been studying all morning.', 'I have studying all morning.', 'I has been studying all morning.'],
                    'Use have been + studying for a continuing action.',
                ],
                [
                    'Choose the correct question.',
                    ['How long have you been living here?', 'How long you have been live here?', 'How long has you been living here?'],
                    'How long have you been + -ing.',
                ],
                [
                    'Choose the clearer time phrase.',
                    ['I have been waiting since 9 a.m.', 'I have been waiting since two hours.', 'I have been waiting from I arrived only.'],
                    'Use since with a point in time.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: say how long you have been doing one activity, ask How long have you been…?, and answer.',
                [
                    'Uses have/has been + -ing',
                    'Asks How long have you been…?',
                    'Uses for or since with time',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ ಹಿಂದೆ ಶುರುವಾಗಿ ಈಗಲೂ ನಡೆಯುತ್ತಿರುವ ಕೆಲಸವನ್ನು have been / has been + -ing ರೂಪದಲ್ಲಿ ಹೇಳಿ, How long…? ಎಂದು ಕೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. for = ಅವಧಿ; since = ಆರಂಭದ ಸಮಯ.',
        ];
    }

    /** @return array<string, mixed> */
    private static function had(): array
    {
        return [
            'level_number' => 9,
            'objective' => 'By the end of this lesson, the learner can use had + past participle to say one action was already finished before another past time.',
            'communication_function' => 'past_perfect_sequence',
            'target_patterns' => [
                'I had + past participle before…',
                'She had already + past participle.',
                'Had you + past participle?',
                'Yes, I had. / No, I had not.',
            ],
            'misconception_codes' => ['HAD_PAST_SIMPLE', 'HAD_BASE_VERB'],
            'talk' => [
                'scenario' => 'You arrived at a meeting late. Explain what you had already done before you arrived.',
                'elicitation_goals' => [
                    'State one earlier past action with had + past participle.',
                    'Ask one Had you…? question.',
                    'Answer with Yes, I had or No, I had not.',
                ],
                'fallback_turns' => [
                    'I had sent the file before lunch.',
                    'Had you called them?',
                    'Yes, I had.',
                ],
            ],
            'check' => self::check('had', [
                [
                    'Choose the sentence about an earlier past action.',
                    ['She had left before I arrived.', 'She had leave before I arrived.', 'She has left before I arrived yesterday only.'],
                    'Had + left shows the earlier finished action.',
                ],
                [
                    'Choose the correct question.',
                    ['Had you finished before 6?', 'Had you finish before 6?', 'Did you had finished before 6?'],
                    'Had you + past participle.',
                ],
                [
                    'Choose a suitable short reply to Had you eaten?',
                    ['No, I had not.', 'No, I had not ate.', 'No, I have not had.'],
                    'Short answers use had / had not.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: say one thing you had already done before another past time, ask Had you…?, and answer.',
                [
                    'Uses had + past participle',
                    'Shows one action before another past time',
                    'Gives a short had answer',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ ಇನ್ನೊಂದು ಹಿಂದಿನ ಸಮಯಕ್ಕಿಂತ ಮೊದಲೇ ಮುಗಿದ ಕೆಲಸವನ್ನು had + past participle ರೂಪದಲ್ಲಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ.',
        ];
    }

    /** @return array<string, mixed> */
    private static function will(): array
    {
        return [
            'level_number' => 10,
            'objective' => 'By the end of this lesson, the learner can use will to make one future plan or promise and ask a simple Will you…? request.',
            'communication_function' => 'future_will',
            'target_patterns' => [
                'I will + base verb.',
                'I will not + base verb.',
                'Will you + base verb?',
                'Yes, I will. / No, I will not.',
            ],
            'misconception_codes' => ['WILL_BASE_VERB', 'WILL_TO_INFINITIVE'],
            'talk' => [
                'scenario' => 'You are planning tomorrow with a classmate. Make one promise with will and ask one Will you…? request.',
                'elicitation_goals' => [
                    'State one future action with I will + base verb.',
                    'Ask one Will you…? request.',
                    'Answer with Yes, I will or No, I will not.',
                ],
                'fallback_turns' => [
                    'I will join the class tomorrow.',
                    'Will you send the notes?',
                    'Yes, I will.',
                ],
            ],
            'check' => self::check('will', [
                [
                    'Choose the future sentence.',
                    ['I will call you tonight.', 'I will to call you tonight.', 'I will calling you tonight.'],
                    'Will is followed by the base verb call.',
                ],
                [
                    'Choose the polite request.',
                    ['Will you wait a minute?', 'Will you waiting a minute?', 'You will wait a minute?'],
                    'Requests often use Will you + base verb.',
                ],
                [
                    'Choose a suitable short reply to Will you help?',
                    ['Yes, I will.', 'Yes, I will to.', 'Yes, I will helping.'],
                    'Short answers use will / will not.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: make one I will… plan, ask Will you…?, and answer it.',
                [
                    'Uses I will + base verb',
                    'Forms one Will you…? request',
                    'Gives a short will answer',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ ನಾಳೆಯ ಯೋಜನೆ ಅಥವಾ ವಾಗ್ದಾನವನ್ನು I will + ಮೂಲ ಕ್ರಿಯಾಪದ ರೂಪದಲ್ಲಿ ಹೇಳಿ, Will you…? ಎಂದು ವಿನಂತಿಸುವುದನ್ನು ಕಲಿಯಿರಿ.',
        ];
    }

    /** @return array<string, mixed> */
    private static function gonnaWanna(): array
    {
        return [
            'level_number' => 11,
            'objective' => 'By the end of this lesson, the learner can recognise gonna and wanna in informal speech and reply with a clear going to or want to sentence.',
            'communication_function' => 'informal_future_and_want',
            'target_patterns' => [
                'I am going to + base verb.',
                'I want to + base verb.',
                'What are you going to + base verb?',
                'I do not want to + base verb.',
            ],
            'misconception_codes' => ['GONNA_IN_FORMAL_WRITING', 'WANNA_BASE_VERB'],
            'talk' => [
                'scenario' => 'A friend uses informal speech: I’m gonna rest. Reply in clear English with going to or want to.',
                'elicitation_goals' => [
                    'Say one plan with I am going to + base verb.',
                    'Say one wish with I want to + base verb.',
                    'Ask What are you going to…?',
                ],
                'fallback_turns' => [
                    'I am going to practise tonight.',
                    'I want to drink water.',
                    'What are you going to do now?',
                ],
            ],
            'check' => self::check('gonna_wanna', [
                [
                    'Choose the clearer classroom sentence.',
                    ['I am going to start now.', 'I gonna start now in this class.', 'I going start now.'],
                    'In class, use going to, not gonna.',
                ],
                [
                    'Choose the correct want sentence.',
                    ['I want to sit here.', 'I wanna to sit here.', 'I want sitting here.'],
                    'Want to is followed by the base verb sit.',
                ],
                [
                    'Choose the question about a plan.',
                    ['What are you going to cook?', 'What you gonna cooking?', 'What are you going cook?'],
                    'Use What are you going to + base verb.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: say one going-to plan, one want-to wish, and ask What are you going to…?',
                [
                    'Uses I am going to + base verb',
                    'Uses I want to + base verb',
                    'Asks about a partner’s plan',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ ಮಾತಿನ gonna/wanna ಅನ್ನು ಗುರುತಿಸಿ, ತರಗತಿಯಲ್ಲಿ going to ಮತ್ತು want to ಎಂದು ಸ್ಪಷ್ಟವಾಗಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ.',
        ];
    }

    /** @return array<string, mixed> */
    private static function would(): array
    {
        return [
            'level_number' => 12,
            'objective' => 'By the end of this lesson, the learner can use would like and Would you…? to make polite offers, requests and preferences.',
            'communication_function' => 'polite_would',
            'target_patterns' => [
                'I would like + noun / to + base verb.',
                'Would you like…?',
                'Would you + base verb, please?',
                'I would not like…',
            ],
            'misconception_codes' => ['WOULD_LIKE_TO', 'WOULD_WANT_MIX'],
            'talk' => [
                'scenario' => 'You are ordering tea with a colleague. Make one polite preference and one Would you like…? offer.',
                'elicitation_goals' => [
                    'State a preference with I would like…',
                    'Offer with Would you like…?',
                    'Make one polite Would you…, please? request.',
                ],
                'fallback_turns' => [
                    'I would like black tea.',
                    'Would you like some water?',
                    'Would you pass the sugar, please?',
                ],
            ],
            'check' => self::check('would', [
                [
                    'Choose the polite preference.',
                    ['I would like a window seat.', 'I would liking a window seat.', 'I would like to a window seat.'],
                    'Would like + noun is a polite preference.',
                ],
                [
                    'Choose the polite offer.',
                    ['Would you like some help?', 'Would you liking some help?', 'You would like some help?'],
                    'Offers often use Would you like…?',
                ],
                [
                    'Choose a suitable short reply to Would you like tea?',
                    ['Yes, I would.', 'Yes, I would to.', 'Yes, I would likeing.'],
                    'Short answers use would / would not.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: say I would like…, offer Would you like…?, and make one Would you…, please? request.',
                [
                    'Uses I would like',
                    'Uses Would you like…?',
                    'Makes one polite request',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ ವಿನಯದಿಂದ ಇಷ್ಟ, ಕೊಡುಗೆ ಮತ್ತು ವಿನಂತಿಯನ್ನು I would like ಮತ್ತು Would you…? ರೂಪದಲ್ಲಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ.',
        ];
    }

    /** @return array<string, mixed> */
    private static function may(): array
    {
        return [
            'level_number' => 13,
            'objective' => 'By the end of this lesson, the learner can use may for polite permission and for a careful possibility.',
            'communication_function' => 'permission_and_possibility',
            'target_patterns' => [
                'May I + base verb?',
                'Yes, you may. / No, you may not.',
                'It may + base verb.',
                'I may not + base verb.',
            ],
            'misconception_codes' => ['MAY_CAN_PERMISSION', 'MAY_BASE_VERB'],
            'talk' => [
                'scenario' => 'You are in a quiet office. Ask polite permission with May I…? and say one possible plan with It may…',
                'elicitation_goals' => [
                    'Ask one May I…? permission question.',
                    'Answer with Yes, you may or No, you may not.',
                    'State one possibility with may + base verb.',
                ],
                'fallback_turns' => [
                    'May I sit here?',
                    'Yes, you may.',
                    'It may rain later.',
                ],
            ],
            'check' => self::check('may', [
                [
                    'Choose the polite permission question.',
                    ['May I open the window?', 'May I opening the window?', 'May I to open the window?'],
                    'May I + base verb asks permission.',
                ],
                [
                    'Choose the possibility sentence.',
                    ['She may come after lunch.', 'She may comes after lunch.', 'She may to come after lunch.'],
                    'May + base verb shows possibility.',
                ],
                [
                    'Choose a suitable short reply to May I start?',
                    ['Yes, you may.', 'Yes, you may to.', 'Yes, you may starting.'],
                    'Short permission answers use may / may not.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: ask May I…?, answer it, and state one It may… possibility.',
                [
                    'Uses May I + base verb',
                    'Gives a may / may not answer',
                    'States one possibility with may',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ ವಿನಯದ ಅನುಮತಿಗೆ May I…? ಮತ್ತು ಸಾಧ್ಯತೆಗೆ may + ಮೂಲ ಕ್ರಿಯಾಪದ ಬಳಸುವುದನ್ನು ಕಲಿಯಿರಿ.',
        ];
    }

    /** @return array<string, mixed> */
    private static function ifClause(): array
    {
        return [
            'level_number' => 14,
            'objective' => 'By the end of this lesson, the learner can use a simple if clause to talk about a real everyday condition and its result.',
            'communication_function' => 'real_conditional',
            'target_patterns' => [
                'If + present, will + base verb.',
                'If I + present, I will + base verb.',
                'What will you do if…?',
                'If it rains, we will + base verb.',
            ],
            'misconception_codes' => ['IF_WILL_IN_IF_CLAUSE', 'IF_PRESENT_RESULT'],
            'talk' => [
                'scenario' => 'You are planning a weekend outing. Use one if sentence about weather or time and ask What will you do if…?',
                'elicitation_goals' => [
                    'Make one If + present, will… sentence.',
                    'Ask What will you do if…?',
                    'Keep will out of the if-clause.',
                ],
                'fallback_turns' => [
                    'If it rains, we will stay inside.',
                    'If I finish early, I will call you.',
                    'What will you do if the bus is late?',
                ],
            ],
            'check' => self::check('if', [
                [
                    'Choose the correct real-condition sentence.',
                    ['If I have time, I will join.', 'If I will have time, I will join.', 'If I have time, I joining.'],
                    'The if-clause uses present have, not will have.',
                ],
                [
                    'Choose the question.',
                    ['What will you do if she is busy?', 'What will you do if she will be busy?', 'What you do if she is busy?'],
                    'Ask What will you do if + present.',
                ],
                [
                    'Choose the clearer result clause.',
                    ['If the shop is closed, we will wait.', 'If the shop is closed, we wait will.', 'If the shop closed, we will waiting.'],
                    'The result uses will + base verb.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: say one If…, I will… sentence and ask What will you do if…?',
                [
                    'Uses If + present',
                    'Uses will + base verb in the result',
                    'Asks one if-question',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ ನಿಜವಾದ ಷರತ್ತು ಮತ್ತು ಫಲಿತಾಂಶವನ್ನು If + ವರ್ತಮಾನ, will + ಮೂಲ ಕ್ರಿಯಾಪದ ರೂಪದಲ್ಲಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. if ವಾಕ್ಯದಲ್ಲಿ will ಬೇಡ.',
        ];
    }

    /** @return array<string, mixed> */
    private static function must(): array
    {
        return [
            'level_number' => 15,
            'objective' => 'By the end of this lesson, the learner can use must and must not for strong everyday rules and obligations.',
            'communication_function' => 'obligation_and_prohibition',
            'target_patterns' => [
                'I must + base verb.',
                'You must not + base verb.',
                'Must I + base verb?',
                'Yes, you must. / No, you must not.',
            ],
            'misconception_codes' => ['MUST_BASE_VERB', 'MUST_TO_INFINITIVE'],
            'talk' => [
                'scenario' => 'You are explaining a workplace or classroom rule. State one must obligation and one must not prohibition.',
                'elicitation_goals' => [
                    'State one obligation with must + base verb.',
                    'State one prohibition with must not + base verb.',
                    'Ask one Must I…? question.',
                ],
                'fallback_turns' => [
                    'You must wear your badge.',
                    'You must not shout here.',
                    'Must I sign this form?',
                ],
            ],
            'check' => self::check('must', [
                [
                    'Choose the obligation sentence.',
                    ['You must show your ticket.', 'You must to show your ticket.', 'You must showing your ticket.'],
                    'Must is followed by the base verb show.',
                ],
                [
                    'Choose the prohibition.',
                    ['You must not park here.', 'You must not to park here.', 'You must not parking here.'],
                    'Must not + base verb forbids the action.',
                ],
                [
                    'Choose a suitable short reply to Must I wait?',
                    ['Yes, you must.', 'Yes, you must to.', 'Yes, you must waiting.'],
                    'Short answers use must / must not.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: give one must rule, one must not rule, and ask Must I…?',
                [
                    'Uses must + base verb',
                    'Uses must not + base verb',
                    'Asks one Must I…? question',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ ಕಡ್ಡಾಯ ನಿಯಮಕ್ಕೆ must ಮತ್ತು ನಿಷೇಧಕ್ಕೆ must not ಬಳಸುವುದನ್ನು ಕಲಿಯಿರಿ. must ನಂತರ ಮೂಲ ಕ್ರಿಯಾಪದ.',
        ];
    }

    /** @return array<string, mixed> */
    private static function shouldHave(): array
    {
        return [
            'level_number' => 16,
            'objective' => 'By the end of this lesson, the learner can use should have + past participle to talk about a better past choice without blaming harshly.',
            'communication_function' => 'past_regret_advice',
            'target_patterns' => [
                'I should have + past participle.',
                'You should not have + past participle.',
                'Should I have + past participle?',
                'Yes, you should have. / No, you should not have.',
            ],
            'misconception_codes' => ['SHOULD_HAVE_PP', 'SHOULD_HAVE_PAST_SIMPLE'],
            'talk' => [
                'scenario' => 'A meeting started without the notes. Talk about one better past action with should have.',
                'elicitation_goals' => [
                    'State one past regret with I should have + past participle.',
                    'Give gentle advice with You should have…',
                    'Ask Should I have…?',
                ],
                'fallback_turns' => [
                    'I should have left earlier.',
                    'You should have told me.',
                    'Should I have called first?',
                ],
            ],
            'check' => self::check('should_have', [
                [
                    'Choose the past-advice sentence.',
                    ['I should have checked the time.', 'I should have check the time.', 'I should had checked the time.'],
                    'Should have + past participle checked.',
                ],
                [
                    'Choose the gentler correction.',
                    ['You should not have waited so long.', 'You should not have wait so long.', 'You should not had waited so long.'],
                    'Should not have + past participle.',
                ],
                [
                    'Choose a suitable short reply to Should I have asked?',
                    ['Yes, you should have.', 'Yes, you should have to.', 'Yes, you should had.'],
                    'Short answers keep should have.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: say I should have…, give one You should have…, and ask Should I have…?',
                [
                    'Uses should have + past participle',
                    'Uses should not have or Should I have…?',
                    'Talks about a past choice',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ ಹಿಂದಿನ ಉತ್ತಮ ಆಯ್ಕೆಯನ್ನು should have + past participle ರೂಪದಲ್ಲಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. ದೂಷಣೆಯಿಲ್ಲದೆ ನಿಧಾನ ಸಲಹೆ.',
        ];
    }

    /** @return array<string, mixed> */
    private static function usedTo(): array
    {
        return [
            'level_number' => 17,
            'objective' => 'By the end of this lesson, the learner can use used to + base verb to talk about a past habit that is not true now.',
            'communication_function' => 'past_habit',
            'target_patterns' => [
                'I used to + base verb.',
                'I did not use to + base verb.',
                'Did you use to + base verb?',
                'Yes, I did. / No, I did not.',
            ],
            'misconception_codes' => ['USED_TO_BASE_VERB', 'USED_TO_STILL_TRUE'],
            'talk' => [
                'scenario' => 'You are talking about school days versus now. Share one old habit with used to and ask Did you use to…?',
                'elicitation_goals' => [
                    'State one past habit with I used to + base verb.',
                    'Ask Did you use to…?',
                    'Contrast with now using I do not… now or I … now.',
                ],
                'fallback_turns' => [
                    'I used to walk to school.',
                    'Did you use to play cricket?',
                    'I do not walk now. I take the bus.',
                ],
            ],
            'check' => self::check('used_to', [
                [
                    'Choose the past-habit sentence.',
                    ['I used to drink more tea.', 'I used to drinking more tea.', 'I use to drink more tea then.'],
                    'Used to + base verb for a past habit.',
                ],
                [
                    'Choose the question.',
                    ['Did you use to live here?', 'Did you used to live here?', 'Did you use to lived here?'],
                    'Questions use Did you use to + base verb.',
                ],
                [
                    'Choose the clearer contrast with now.',
                    ['I used to cook every day. Now I cook on Sundays.', 'I used to cook every day and I still used to.', 'I used cook every day now.'],
                    'Used to is for a habit that is not the same now.',
                ],
            ]),
            'apply' => self::apply(
                'Record a 20–30 second exchange: say one I used to… habit, ask Did you use to…?, and contrast with now.',
                [
                    'Uses I used to + base verb',
                    'Asks Did you use to…?',
                    'Contrasts with now',
                    'Speech is understandable',
                ],
            ),
            'kannada_summary' => 'ಈ ಪಾಠದಲ್ಲಿ ಹಿಂದೆ ಇದ್ದ ಆದರೆ ಈಗ ಇಲ್ಲದ ಅಭ್ಯಾಸವನ್ನು used to + ಮೂಲ ಕ್ರಿಯಾಪದ ರೂಪದಲ್ಲಿ ಹೇಳುವುದನ್ನು ಕಲಿಯಿರಿ. ಪ್ರಶ್ನೆ: Did you use to…?',
        ];
    }

    /**
     * @param  list<array{0: string, 1: list<string>, 2: string}>  $items
     * @return array{pass_threshold: int, items: list<array<string, mixed>>}
     */
    private static function check(string $conceptKey, array $items): array
    {
        $weights = [35, 30, 35];
        $encoded = [];
        foreach ($items as $index => $item) {
            $encoded[] = [
                'id' => $conceptKey.'-0'.($index + 1),
                'weight' => $weights[$index],
                'prompt' => $item[0],
                'options' => $item[1],
                'correct_option' => 0,
                'rationale' => $item[2],
            ];
        }

        return [
            'pass_threshold' => 80,
            'items' => $encoded,
        ];
    }

    /**
     * @param  list<string>  $rubric
     * @return array{task: string, rubric: list<string>, evidence_state: string}
     */
    private static function apply(string $task, array $rubric): array
    {
        return [
            'task' => $task,
            'rubric' => $rubric,
            'evidence_state' => 'not_recorded',
        ];
    }
}
