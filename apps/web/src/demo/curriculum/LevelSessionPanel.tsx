import type {
  MessageKey,
  MessageParameters,
  ResolutionPolicy,
  SupportedLocale,
} from '@global-english/localization'
import { useEffect, useMemo, useState } from 'react'
import { ConversationJourney } from '../../conversation/ConversationJourney'
import type {
  ConversationJourneyApi,
  FriendSession,
} from '../../conversation/ConversationJourneyApi'
import { LocalizedMessage } from '../../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../../localization/interfaceLocalization'
import { isDemoSpeechUrl } from '../browserSpeech'
import { DemoSpeechPlayer } from '../DemoSpeechPlayer'
import {
  bindChapterPractice,
  chapterFromCurriculum,
  chapterListenLines,
  chapterOpeningLine,
} from '../chapterPractice'
import { speakDemoText, stopDemoSpeech } from '../demoTts'
import { ModelAudioPlayer } from '../../learner/ModelAudioPlayer'
import type {
  LevelSessionDetail,
  LevelSessionsApi,
} from '../../learner/LevelSessionsApi'
import { createClientId as createRandomClientId } from '../../shared/createClientId'
import {
  MISSING_LEVEL_NOTICE,
  type LevelCurriculum,
} from './levelCurriculum'

export type LevelSessionMode =
  | 'audio'
  | 'content'
  | 'activity'
  | 'ai-teacher'
  | 'conversation'

export interface LevelSessionPanelProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly roomTitle: ReturnType<typeof resolveInterfaceMessage>
  readonly session: LevelCurriculum
  readonly mode?: LevelSessionMode
  readonly conversationApi?: ConversationJourneyApi
  readonly demoMode?: boolean
  readonly levelSessionsApi?: LevelSessionsApi
  readonly createClientId?: () => string
}

function lessonAudioLabels(
  message: (key: MessageKey, parameters?: MessageParameters) => {
    readonly text: string
  },
) {
  return {
    endedStatus: message('learner.audio.ended_status').text,
    error: message('learner.audio.error').text,
    errorStatus: message('learner.audio.error_status').text,
    loading: message('learner.audio.loading').text,
    loadingStatus: message('learner.audio.loading_status').text,
    pause: message('learner.audio.pause').text,
    pausedStatus: message('learner.audio.paused_status').text,
    play: message('learner.audio.play').text,
    playingStatus: message('learner.audio.playing_status').text,
    readyStatus: message('learner.audio.ready_status').text,
    region: message('learner.audio.region').text,
    replay: message('learner.audio.replay').text,
    resume: message('learner.audio.resume').text,
    unavailable: message('learner.audio.unavailable').text,
    unavailableStatus: message('learner.audio.unavailable_status').text,
  }
}

export function LevelSessionPanel({
  locale,
  localizationPolicy,
  roomTitle,
  session,
  mode = 'audio',
  conversationApi,
  demoMode = false,
  levelSessionsApi,
  createClientId = createRandomClientId,
}: LevelSessionPanelProps) {
  const message = (key: MessageKey, parameters: MessageParameters = {}) =>
    resolveInterfaceMessage(key, locale, localizationPolicy, parameters)
  const [answers, setAnswers] = useState<Record<string, number>>({})
  const [submitted, setSubmitted] = useState(false)
  const [speaking, setSpeaking] = useState(false)
  const [apiDetail, setApiDetail] = useState<LevelSessionDetail | null>(null)
  const [boundSession, setBoundSession] = useState<FriendSession | null>(null)
  const chapter = useMemo(() => chapterFromCurriculum(session), [session])
  const apiLevelNumber = session.kind === 'level' ? session.number : null

  useEffect(() => {
    bindChapterPractice(chapter)
    return () => {
      bindChapterPractice(null)
      stopDemoSpeech()
    }
  }, [chapter])

  useEffect(() => {
    setApiDetail(null)
    setBoundSession(null)
    if (levelSessionsApi === undefined || apiLevelNumber === null) {
      return
    }
    const controller = new AbortController()
    void levelSessionsApi
      .sessionDetail(apiLevelNumber, 'introduction', controller.signal)
      .then((detail) => {
        if (!controller.signal.aborted) {
          setApiDetail(detail)
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          setApiDetail(null)
        }
      })
    return () => {
      controller.abort()
    }
  }, [apiLevelNumber, levelSessionsApi])

  useEffect(() => {
    if (
      levelSessionsApi === undefined ||
      apiLevelNumber === null ||
      conversationApi === undefined
    ) {
      return
    }
    const controller = new AbortController()
    void levelSessionsApi
      .startConversation(
        apiLevelNumber,
        'introduction',
        locale,
        createClientId(),
        controller.signal,
      )
      .then((started) => {
        if (!controller.signal.aborted) {
          setBoundSession(started)
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          setBoundSession(null)
        }
      })
    return () => {
      controller.abort()
    }
  }, [
    apiLevelNumber,
    conversationApi,
    createClientId,
    levelSessionsApi,
    locale,
  ])

  const streamPath =
    apiDetail?.audio_stream_path !== undefined &&
    apiDetail.audio_stream_path !== ''
      ? apiDetail.audio_stream_path
      : null

  const playModel = () => {
    if (session.modelLine === null) {
      return
    }
    stopDemoSpeech()
    setSpeaking(true)
    void speakDemoText(session.modelLine, 'en-IN')
      .catch(() => undefined)
      .finally(() => {
        setSpeaking(false)
      })
  }

  useEffect(() => {
    if (
      chapter === null ||
      (mode !== 'audio' && mode !== 'content') ||
      streamPath !== null
    ) {
      return
    }
    stopDemoSpeech()
    setSpeaking(true)
    void speakDemoText(chapter.modelLine, 'en-IN')
      .catch(() => undefined)
      .finally(() => {
        setSpeaking(false)
      })
  }, [chapter, mode, streamPath])

  const score = submitted
    ? session.questions.reduce(
        (total, question) =>
          answers[question.id] === question.correctIndex ? total + 1 : total,
        0,
      )
    : null

  const showQuiz = mode !== 'conversation' && session.questions.length > 0
  const showTalk =
    conversationApi !== undefined &&
    chapter !== null &&
    (mode === 'audio' ||
      mode === 'ai-teacher' ||
      mode === 'conversation' ||
      mode === 'activity' ||
      mode === 'content')

  return (
    <section className="phase1-panel phase1-daily" data-curriculum={session.id}>
      <p className="phase1-muted">
        <span lang={roomTitle.resolvedLocale}>{roomTitle.text}</span>
      </p>
      <h2 lang="en-IN">{session.title}</h2>
      {session.availability === 'missing' || session.modelLine === null ? (
        <p role="status">{MISSING_LEVEL_NOTICE}</p>
      ) : (
        <>
          {session.objective === null ? null : (
            <p lang="en-IN">{session.objective}</p>
          )}
          <p lang="en-IN">{session.modelLine}</p>
          {session.kannadaSummary === null ? null : (
            <p lang="kn-IN">{session.kannadaSummary}</p>
          )}
          {session.targetPatterns.length === 0 ? null : (
            <ul lang="en-IN">
              {session.targetPatterns.map((pattern) => (
                <li key={pattern}>{pattern}</li>
              ))}
            </ul>
          )}
          {streamPath !== null ? (
            isDemoSpeechUrl(streamPath) ? (
              <DemoSpeechPlayer
                labels={lessonAudioLabels(message)}
                source={streamPath}
              />
            ) : (
              <ModelAudioPlayer
                labels={lessonAudioLabels(message)}
                loadSource={levelSessionsApi?.downloadAudio}
                mimeType="audio/mpeg"
                onEnded={() => setSpeaking(false)}
                onPlaying={() => setSpeaking(true)}
                source={streamPath}
              />
            )
          ) : (
            <button
              type="button"
              className="ge-button ge-button--primary"
              onClick={playModel}
              disabled={speaking}
              aria-busy={speaking}
            >
              <LocalizedMessage
                resolution={message('demo.phase1.daily.audio_play')}
              />
            </button>
          )}
          {showQuiz ? (
            <article className="phase1-subpanel">
              <h3>
                <LocalizedMessage
                  resolution={message('demo.phase1.daily.quiz_title')}
                />
              </h3>
              <ol className="phase1-quiz" lang="en-IN">
                {session.questions.map((question) => (
                  <li key={question.id}>
                    <p>{question.prompt}</p>
                    <div className="phase1-quiz__options">
                      {question.options.map((option, index) => (
                        <label key={option}>
                          <input
                            type="radio"
                            name={question.id}
                            checked={answers[question.id] === index}
                            disabled={submitted}
                            onChange={() =>
                              setAnswers((current) => ({
                                ...current,
                                [question.id]: index,
                              }))
                            }
                          />
                          <span>{option}</span>
                        </label>
                      ))}
                    </div>
                  </li>
                ))}
              </ol>
              <button
                type="button"
                className="ge-button ge-button--primary"
                disabled={
                  submitted ||
                  session.questions.some(
                    (question) => answers[question.id] === undefined,
                  )
                }
                onClick={() => setSubmitted(true)}
              >
                <LocalizedMessage
                  resolution={message('demo.phase1.daily.submit_quiz')}
                />
              </button>
              {score === null ? null : (
                <p className="phase1-quiz-result" role="status">
                  <LocalizedMessage
                    resolution={message('demo.phase1.daily.quiz_result')}
                  />{' '}
                  {score}/{session.questions.length}
                </p>
              )}
            </article>
          ) : null}
          {session.applyTask === null ? null : (
            <p lang="en-IN">{session.applyTask}</p>
          )}
          {showTalk && conversationApi !== undefined && chapter !== null ? (
            <ConversationJourney
              api={conversationApi}
              autoStart={boundSession === null}
              demoMode={demoMode}
              embedded
              initialSession={boundSession ?? undefined}
              key={boundSession?.id ?? session.id}
              listenLines={chapterListenLines(chapter)}
              locale={locale}
              localizationPolicy={localizationPolicy}
              openingLine={chapterOpeningLine(chapter)}
              speakOpening={mode === 'ai-teacher' || mode === 'conversation'}
            />
          ) : null}
        </>
      )}
    </section>
  )
}
