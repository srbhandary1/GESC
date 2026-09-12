import type { MessageKey, MessageParameters, ResolutionPolicy, SupportedLocale } from '@global-english/localization'
import { useState } from 'react'
import { LocalizedMessage } from '../../localization/LocalizedMessage'
import { resolveInterfaceMessage } from '../../localization/interfaceLocalization'
import { speakDemoText, stopDemoSpeech } from '../demoTts'
import {
  MISSING_LEVEL_NOTICE,
  type LevelCurriculum,
} from './levelCurriculum'

export interface LevelSessionPanelProps {
  readonly locale: SupportedLocale
  readonly localizationPolicy: ResolutionPolicy
  readonly roomTitle: ReturnType<typeof resolveInterfaceMessage>
  readonly session: LevelCurriculum
}

export function LevelSessionPanel({
  locale,
  localizationPolicy,
  roomTitle,
  session,
}: LevelSessionPanelProps) {
  const message = (key: MessageKey, parameters: MessageParameters = {}) =>
    resolveInterfaceMessage(key, locale, localizationPolicy, parameters)
  const [answers, setAnswers] = useState<Record<string, number>>({})
  const [submitted, setSubmitted] = useState(false)
  const [speaking, setSpeaking] = useState(false)

  const playModel = () => {
    if (session.modelLine === null) {
      return
    }
    stopDemoSpeech()
    setSpeaking(true)
    void speakDemoText(session.modelLine, 'en-IN').finally(() => {
      setSpeaking(false)
    })
  }

  const score = submitted
    ? session.questions.reduce(
        (total, question) =>
          answers[question.id] === question.correctIndex ? total + 1 : total,
        0,
      )
    : null

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
          {session.questions.length === 0 ? null : (
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
          )}
          {session.applyTask === null ? null : (
            <p lang="en-IN">{session.applyTask}</p>
          )}
        </>
      )}
    </section>
  )
}
