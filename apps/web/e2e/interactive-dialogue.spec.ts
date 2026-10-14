import AxeBuilder from '@axe-core/playwright'
import type { ConversationInteraction, ConversationSession, ConversationTurn, CreateConversationTurnRequest } from '@global-english/contracts'
import { resolveMessage, type MessageKey, type SupportedLocale } from '@global-english/localization'
import { expect, test, type Page } from '@playwright/test'

const id = (last: string) => `01J200000000000000000000${last.padStart(2, '0')}`
const opening = 'Good morning. How can I help you?'
const nextReply = 'What time is your appointment?'
const explanation = 'Use have after I. Try your sentence again.'
const kannadaCorrection = 'I ನಂತರ have ಬಳಸಿ. ನಿಮ್ಮ ವಾಕ್ಯವನ್ನು ಮತ್ತೊಮ್ಮೆ ಹೇಳಿ.'
const localAnswer = 'ಹಿಂದೆ ನಡೆದ ಕೆಲಸವನ್ನು ಹೇಳಲು ಭೂತಕಾಲವನ್ನು ಬಳಸುತ್ತೇವೆ.'
const message = (key: MessageKey, locale: SupportedLocale = 'en-IN') => resolveMessage(key, { policy: 'development-preview', requestedLocale: locale }).text

async function setup(page: Page, locale: SupportedLocale = 'en-IN', roleplayAllowed = true, initialProviderGrant = true, initialUploadGrant = false) {
  let current: ConversationSession | null = null
  let latest: ConversationTurn | null = null
  let providerGranted = initialProviderGrant
  const requests: { path: string; body: Record<string, unknown>; key: string | undefined }[] = []
  await page.context().addCookies([{ name: 'XSRF-TOKEN', value: 'synthetic-csrf', url: 'http://127.0.0.1:4173' }])
  await page.route('**/sanctum/csrf-cookie', (route) => route.fulfill({ status: 204 }))
  await page.route('**/api/v1/**', async (route) => {
    const request = route.request()
    const path = new URL(request.url()).pathname
    const body = request.postDataJSON() as Record<string, unknown> | null
    const json = (data: unknown, status = 200) => route.fulfill({ status, json: data, headers: { 'Cache-Control': 'private, no-store' } })
    if (request.method() === 'POST') requests.push({ path, body: body ?? {}, key: request.headers()['idempotency-key'] })
    if (path === '/api/v1/auth/password/status') return json({ credential_ready: true, mode: 'password_only', password_confirmed_at: null, username: 'synthetic.student' })
    if (path === '/api/v1/auth/sessions') return json({ data: [] })
    if (path === '/api/v1/me') return json({ current_membership: { id: id('01'), membership_type: 'learner', organization: { id: id('02'), kind: 'platform_direct', name: 'Synthetic pilot' }, role_keys: ['institution-learner'], status: 'active' }, memberships: [], user: { id: id('09') } })
    if (path === '/api/v1/me/entitlement') return json({ applies: true, entitlement_id: id('10'), plan_code: 'monthly_modules_roleplay', plan_name: 'Premium', status: 'active', allows_roleplay: roleplayAllowed, allows_free_speech: true, allowed_session_parts: ['introduction', 'practice', 'conversation', 'interaction'], max_level_number: null, is_demo: false, expires_at: '2026-10-05T00:00:00Z', live_ai_seconds_remaining: 4920, live_ai_cap_seconds: 5400, gstin_is_placeholder: false })
    if (path === '/api/v1/voice-consents' && request.method() === 'POST') {
      expect(body?.purpose).toBe('conversation_provider_processing')
      providerGranted = true
      return json({ id: id('13'), purpose: 'conversation_provider_processing', state: 'granted', replayed: false, requested_locale: locale, resolved_locale: locale, notice_revision: 'voice-processing-notice-2026-09-01', notice_sha256: 'c'.repeat(64), policy_version: 'recording-policy-v1' }, 201)
    }
    if (path === '/api/v1/voice-consents') return json({ consents: ['voice_upload_and_transcription', 'conversation_provider_processing', 'transcript_history'].map((purpose) => ({ purpose, state: (purpose === 'conversation_provider_processing' && providerGranted) || (purpose === 'voice_upload_and_transcription' && initialUploadGrant) ? 'granted' : 'not_granted', effective_at: purpose === 'conversation_provider_processing' && providerGranted ? '2026-09-25T15:00:00Z' : null })), notice: { history_optional: true, notice: 'Synthetic private processing notice.', notice_revision: 'voice-processing-notice-2026-09-01', notice_sha256: 'c'.repeat(64), policy_version: 'recording-policy-v1', purposes: ['voice_upload_and_transcription', 'conversation_provider_processing', 'transcript_history'], requested_locale: locale, resolved_locale: locale, review_status: 'implementation_draft' } })
    if (path === '/api/v1/conversation-sessions' || /^\/api\/v1\/me\/levels\/\d+\/sessions\/(introduction|practice)\/conversation-sessions$/u.test(path)) {
      const roleplay = body?.interaction_flow === 'roleplay_dialogue'
      const freeSpeech = body?.interaction_flow === 'free_speech'
      expect(body?.recovery_notice_accepted).toBe(true)
      expect(body?.recovery_notice_version).toBe('conversation-recovery-2026-09-25')
      current = {
        id: id('07'), status: 'active', mode: roleplay ? 'roleplay' : 'friend', coaching_mode: 'gentle', ui_locale: locale, stage: roleplay ? 'perform' : null, replayed: false,
        persona: { id: id('03'), key: roleplay ? 'clinic-reception-roleplay-prototype' : 'patient-friend-prototype', version_id: id('04'), version: 1, display_name: 'Swathi', disclosure: 'Swathi is AI.', definition_hash: 'a'.repeat(64), review_status: 'synthetic_unreviewed' },
        scenario: { id: id('05'), key: roleplay ? 'clinic-reception-roleplay-scenario' : 'everyday-friend-prototype', version_id: id('06'), version: 1, topic_code: roleplay ? 'clinic-reception' : 'everyday-conversation', definition_hash: 'b'.repeat(64), review_status: 'synthetic_unreviewed' },
        objectives: roleplay ? [{ id: id('11'), key: 'appointment', title: 'State the appointment reason', completion_criteria_code: 'appointment' }, { id: id('12'), key: 'greeting', title: 'Greet the receptionist', completion_criteria_code: 'greeting' }] : [], feedback: [],
        interaction: { flow: roleplay ? 'roleplay_dialogue' : freeSpeech ? 'free_speech' : 'question_help', state: roleplay || freeSpeech ? 'awaiting_reply' : 'awaiting_question', revision: 0, opportunity: 1, ai_role: roleplay ? 'Receptionist' : 'Swathi', learner_role: roleplay ? 'Patient' : 'Learner', opening_reply: roleplay || freeSpeech ? { text: opening, locale: 'en-IN' } : null, recovery_expires_at: new Date(Date.now() + 15 * 60_000).toISOString() },
      }
      return json(current, 201)
    }
    if (path.endsWith('/speech')) return json({ playback_available: false, rendering_mode: 'deterministic-fake-text-only' })
    if (path.endsWith('/turns') && current?.interaction) {
      const input = body as CreateConversationTurnRequest
      expect(input.expected_revision).toBe(current.interaction.revision)
      const roleplay = current.interaction.flow === 'roleplay_dialogue'
      const wrong = roleplay && input.prototype_utterance === 'I has an appointment.'
      const confused = input.action === 'confused' || input.prototype_utterance === 'ನನಗೆ ಅರ್ಥವಾಗಲಿಲ್ಲ'
      const acceptedHelp = input.action === 'accept_explanation'
      if (current.interaction.flow === 'question_help' && (acceptedHelp || input.action === 'decline_explanation')) expect(current.interaction.state).toBe('awaiting_explanation_permission')
      const interaction: ConversationInteraction = { ...current.interaction, revision: current.interaction.revision + 1, opportunity: current.interaction.opportunity + (roleplay && !wrong ? 1 : 0), state: roleplay ? wrong ? 'retry_required' : 'awaiting_reply' : confused ? 'awaiting_explanation_permission' : 'awaiting_question' }
      const reply = { text: roleplay ? wrong ? 'I have an appointment.' : nextReply : confused ? 'Would you like me to explain in your language?' : acceptedHelp ? localAnswer : 'We use the past tense for actions that already happened.', locale: acceptedHelp ? locale : 'en-IN' as SupportedLocale }
      latest = { id: id(String(20 + interaction.revision)), conversation_session_id: current.id, turn_number: interaction.revision, status: 'completed', transcript: input.prototype_utterance, understood_intent: confused ? 'help_confused' : 'synthetic_reply', assistant_reply: reply.locale === 'en-IN' ? reply.text : null, reply, priority_correction: wrong ? { needed: true, error_category: 'grammar.subject_verb_agreement', correction_en: 'I have an appointment.', explanation_l1: locale === 'kn-IN' ? kannadaCorrection : explanation, explanation_locale: locale, retry_recommended: true } : null, safety_state: 'ok', speech: { playback_available: false, rendering_mode: 'deterministic-fake-text-only' }, usage: { estimated_cost_microunits: 0, provider_attempts: 1, total_latency_ms: 1 }, events_url: `/api/v1/conversation-turns/${id('08')}/events`, replayed: false, interaction, assessment: roleplay ? wrong ? 'retry_required' : 'accepted' : 'unassessed', original_turn_id: wrong ? id('21') : null }
      if (input.explanation_target) { latest.explanation_target = input.explanation_target; latest.explanation_source = { text: opening, locale: 'en-IN' } }
      current = { ...current, interaction, latest_turn: latest }
      return json(latest, 201)
    }
    if (path.endsWith('/exit') && current?.interaction) {
      const evidenceTurn = latest?.assessment === 'accepted' && latest.transcript === 'My appointment is at ten.' ? latest : null
      current = { ...current, stage: current.mode === 'roleplay' ? 'reflect' : null, interaction: { ...current.interaction, state: 'closed', opening_reply: null, recovery_expires_at: null }, latest_turn: null, feedback: current.objectives?.map((objective) => ({ id: objective.id, scenario_objective_id: objective.id, objective_key: objective.key, completion_state: objective.key === 'appointment' && evidenceTurn ? 'met' : 'unassessed', evidence_digest: 'd'.repeat(64), source_turn_id: objective.key === 'appointment' ? evidenceTurn?.id ?? null : null, advisory: true })) }
      return json(current)
    }
    if (path === `/api/v1/conversation-sessions/${id('07')}`) return json(current)
    return route.abort('failed')
  })
  await page.goto('/#speak')
  await page.locator('#interface-locale').selectOption(locale)
  return { requests, getSession: () => current }
}

test('lesson tutor starts with the selected level endpoint and returns to that lesson', async ({ page }) => {
  const { requests } = await setup(page)
  await page.route('**/api/v1/me/levels', route => route.fulfill({ json: { levels: [{ level_number: 2, concept_key: 'should', availability: 'available', entitlement_access: 'allowed', progress_access: 'allowed', blueprint_id: id('31'), session_part_ids: ['introduction', 'practice', 'conversation', 'interaction'] }] } }))
  await page.route('**/api/v1/me/levels/2/sessions', route => route.fulfill({ json: { level_number: 2, concept_key: 'should', blueprint_id: id('31'), blueprint_content_hash: 'a'.repeat(64), sessions: [{ part_id: 'introduction', part_order: 1, completed: false, media_asset_id: id('32') }] } }))
  await page.route('**/api/v1/me/levels/2/sessions/introduction', route => route.fulfill({ json: { level_number: 2, part_id: 'introduction', part_order: 1, completed: false, media_asset_id: id('32'), audio_stream_path: null, objective: 'Use should to give advice.', target_patterns: ['You should rest.'], kannada_guidance: { summary: '' }, activities: [], check: null, talk: null, apply: null, conversation_binding: null } }))
  await page.locator('#primary-navigation-learn').click()
  await page.locator('#level-path-level-2').click()
  await page.getByRole('button', { name: 'Ask AI Tutor', exact: true }).click()
  await expect(page.getByText('Use should to give advice.', { exact: true })).toBeVisible()
  await page.getByRole('checkbox', { name: message('conversation.interactive.accept_notice') }).check()
  await page.getByRole('button', { name: 'Start question help' }).click()
  await expect(page.locator('[data-mode="question_help"]')).toBeVisible()
  const starts = requests.filter(request => request.path.endsWith('/conversation-sessions'))
  expect(starts).toHaveLength(1)
  expect(starts[0]?.path).toBe('/api/v1/me/levels/2/sessions/introduction/conversation-sessions')
  expect(starts[0]?.body).toMatchObject({ purpose: 'question_help', ui_locale: 'en-IN', recovery_notice_accepted: true })
  expect(starts[0]?.body).not.toHaveProperty('lesson')
  await page.getByRole('button', { name: 'Back', exact: true }).click()
  await expect(page.locator('#level-2-part-introduction')).toBeVisible()
})

test('Kannada roleplay labels stay localized before and after starting while English practice stays English', async ({ page }) => {
  await setup(page, 'kn-IN')
  await page.getByRole('tab', { name: message('speak.mode.situation', 'kn-IN'), exact: true }).click()
  await expect(page.getByRole('button', { name: /ಚಿಕಿತ್ಸಾಲಯದ ಸ್ವಾಗತ ಕಚೇರಿ/ })).toBeVisible()
  await expect(page.getByText('ಸ್ವಾಗತಕಾರರು / ರೋಗಿ', { exact: true })).toHaveAttribute('lang', 'kn-IN')
  await expect(page.getByRole('button', { name: /Clinic reception/ })).toHaveCount(0)
  await page.getByRole('checkbox', { name: message('conversation.interactive.accept_notice', 'kn-IN') }).check()
  await page.getByRole('button', { name: message('conversation.roleplay.start', 'kn-IN'), exact: true }).click()
  const dialogue = page.locator('[data-mode="roleplay_dialogue"]')
  await expect(dialogue.getByText('ಸ್ವಾಗತಕಾರರು / ರೋಗಿ', { exact: true })).toHaveAttribute('lang', 'kn-IN')
  await expect(dialogue.getByText(opening, { exact: true })).toBeVisible()
})

test('AI-first roleplay corrects then accepts an alternative and shows honest reflection', async ({ page }, testInfo) => {
  const { requests, getSession } = await setup(page)
  await page.getByRole('tab', { name: 'A situation' }).click()
  await expect(page.getByRole('button', { name: 'Start this situation' })).toBeDisabled()
  await page.getByRole('checkbox', { name: message('conversation.interactive.accept_notice') }).check()
  await page.getByRole('button', { name: 'Start this situation' }).click()
  const dialogue = page.locator('[data-mode="roleplay_dialogue"]')
  await expect(dialogue.getByText(opening, { exact: true })).toBeVisible()
  await expect(dialogue.getByText(/^You:/)).toHaveCount(0)
  await page.getByRole('button', { name: 'Type instead', exact: true }).click()
  await page.locator('#interactive-utterance').fill('I has an appointment.')
  await page.getByRole('button', { name: 'Send turn', exact: true }).click()
  await expect(dialogue.getByText(explanation, { exact: true })).toBeVisible()
  await expect(dialogue.getByRole('heading', { name: 'Try the same response again' })).toBeVisible()
  await expect(dialogue.getByText(nextReply)).toHaveCount(0)
  await page.locator('.ge-application-frame').evaluate((frame) => frame.classList.add('ge-application-frame--native-android'))
  await page.setViewportSize({ width: 320, height: 740 })
  await page.evaluate(() => { document.documentElement.style.fontSize = '200%' })
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320)
  expect((await new AxeBuilder({ page }).include('[data-mode="roleplay_dialogue"]').analyze()).violations).toEqual([])
  await page.screenshot({ path: testInfo.outputPath('roleplay-correction-320.png'), fullPage: true })
  await dialogue.locator('.conversation-journey__correction').screenshot({ path: testInfo.outputPath('roleplay-correction-card-320.png') })
  await page.locator('#interactive-utterance').fill('My appointment is at ten.')
  await page.getByRole('button', { name: 'Send turn', exact: true }).click()
  await expect(dialogue.getByText(nextReply)).toBeVisible()
  const turns = requests.filter((request) => request.path.endsWith('/turns'))
  expect(turns.map((request) => request.body.action)).toEqual(['reply', 'retry'])
  expect(turns.map((request) => request.body.expected_revision)).toEqual([0, 1])
  expect(requests.some((request) => request.path.endsWith('/stages'))).toBe(false)
  await dialogue.locator('.conversation-interactive__options > summary').click()
  await dialogue.getByRole('button', { name: message('conversation.interactive.exit') }).click()
  await expect(dialogue.getByText('Not assessed', { exact: true })).toBeVisible()
  await expect(dialogue.getByRole('heading', { name: message('conversation.interactive.complete') })).toBeVisible()
  expect(getSession()?.feedback?.find((item) => item.objective_key === 'appointment')).toMatchObject({ completion_state: 'met', source_turn_id: id('22'), advisory: true })
  expect(getSession()?.feedback?.find((item) => item.objective_key === 'greeting')).toMatchObject({ completion_state: 'unassessed', source_turn_id: null })
  await expect(dialogue.getByText('I has an appointment.', { exact: true })).toHaveCount(0)
  expect(await page.evaluate(() => localStorage.length)).toBe(0)
})

test('Kannada question help asks permission, honors Yes and No, and resets English first', async ({ page }, testInfo) => {
  const locale = 'kn-IN'
  const { requests } = await setup(page, locale, false)
  await page.getByRole('tab').first().focus()
  await page.keyboard.press('End')
  await expect(page.getByRole('tab', { name: message('conversation.help.title', locale), exact: true })).toHaveAttribute('aria-selected', 'true')
  await page.getByRole('checkbox', { name: message('conversation.interactive.accept_notice', locale) }).check()
  await page.getByRole('button', { name: message('conversation.help.start', locale) }).click()
  const dialogue = page.locator('[data-mode="question_help"]')
  await page.getByRole('button', { name: message('conversation.confidence.type_instead', locale), exact: true }).click()
  const send = async (value: string) => {
    await page.locator('#interactive-utterance').fill(value)
    await page.getByRole('button', { name: message('conversation.send', locale), exact: true }).click()
  }
  await send('ಭೂತಕಾಲ ಎಂದರೇನು?')
  await expect(dialogue.locator('[lang="en-IN"]').getByText('We use the past tense for actions that already happened.')).toBeVisible()
  await expect(dialogue.getByText(localAnswer)).toHaveCount(0)
  await send('ನನಗೆ ಅರ್ಥವಾಗಲಿಲ್ಲ')
  await expect(dialogue.getByRole('button', { name: message('conversation.help.yes', locale) })).toBeVisible()
  await expect(dialogue.getByText(localAnswer)).toHaveCount(0)
  await dialogue.getByRole('button', { name: message('conversation.help.yes', locale) }).click()
  await expect(dialogue.locator('[lang="kn-IN"]').getByText(localAnswer)).toBeVisible()
  await send('Can you give another example?')
  await expect(dialogue.locator('.conversation-journey__bubble--teacher').last().locator('p')).toHaveAttribute('lang', 'en-IN')
  await dialogue.getByRole('button', { name: message('conversation.help.confused', locale), exact: true }).click()
  await dialogue.getByRole('button', { name: message('conversation.help.no', locale) }).click()
  await expect(dialogue.locator('.conversation-journey__bubble--teacher').last().locator('p')).toHaveAttribute('lang', 'en-IN')
  expect(requests.filter((request) => request.path.endsWith('/turns')).map((request) => request.body.action)).toEqual(['question', 'question', 'accept_explanation', 'question', 'confused', 'decline_explanation'])
  await page.setViewportSize({ width: 320, height: 740 })
  expect((await new AxeBuilder({ page }).include('[data-mode="question_help"]').analyze()).violations).toEqual([])
  await page.screenshot({ path: testInfo.outputPath('question-help-kannada-320.png'), fullPage: true })
  expect(await page.evaluate(() => localStorage.length)).toBe(0)
})

test('existing microphone consent does not crash Kannada dialogue or cancel a typed turn', async ({ page }) => {
  const locale = 'kn-IN'
  await setup(page, locale, true, true, true)
  await page.getByRole('tab', { name: message('speak.mode.situation', locale), exact: true }).click()
  await page.getByRole('checkbox', { name: message('conversation.interactive.accept_notice', locale) }).check()
  await page.getByRole('button', { name: message('conversation.roleplay.start', locale), exact: true }).click()
  await page.getByRole('button', { name: message('conversation.confidence.type_instead', locale), exact: true }).click()
  await page.locator('#interactive-utterance').fill('I has an appointment.')
  await page.getByRole('button', { name: message('conversation.send', locale), exact: true }).click()
  await expect(page.getByRole('heading', { name: message('conversation.interactive.retry', locale) })).toBeVisible()
  await expect(page.locator('#application-error-title')).toHaveCount(0)
})

test('temporary recovery acknowledgement never grants provider or microphone consent', async ({ page }) => {
  const { requests } = await setup(page, 'en-IN', true, false)
  await page.getByRole('tab', { name: 'A situation' }).click()
  await page.getByRole('checkbox', { name: message('conversation.interactive.accept_notice') }).check()
  await expect(page.getByRole('button', { name: 'Start this situation' })).toBeDisabled()
  expect(requests).toHaveLength(0)
  await page.getByRole('button', { name: 'Allow automated conversation processing', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Start this situation' })).toBeEnabled()
  expect(requests.filter((request) => request.path.endsWith('/voice-consents')).map((request) => request.body.purpose)).toEqual(['conversation_provider_processing'])
  await page.getByRole('button', { name: 'Start this situation' }).click()
  await expect(page.getByText(opening, { exact: true })).toBeVisible()
  expect(requests.some((request) => request.body.purpose === 'voice_upload_and_transcription')).toBe(false)
  expect(requests.some((request) => /recordings|upload/.test(request.path))).toBe(false)
})

test('Kannada roleplay explains a correction locally without advancing the AI partner', async ({ page }, testInfo) => {
  const locale = 'kn-IN'
  const { requests } = await setup(page, locale)
  await page.getByRole('tab', { name: message('speak.mode.situation', locale), exact: true }).click()
  await page.getByRole('checkbox', { name: message('conversation.interactive.accept_notice', locale) }).check()
  await page.getByRole('button', { name: message('conversation.roleplay.start', locale), exact: true }).click()
  const dialogue = page.locator('[data-mode="roleplay_dialogue"]')
  await expect(dialogue.getByText(opening, { exact: true })).toHaveAttribute('lang', 'en-IN')
  await page.getByRole('button', { name: message('conversation.confidence.type_instead', locale), exact: true }).click()
  await page.locator('#interactive-utterance').fill('I has an appointment.')
  await page.getByRole('button', { name: message('conversation.send', locale), exact: true }).click()
  const correction = dialogue.locator('.conversation-journey__correction')
  await expect(correction.getByText('I have an appointment.', { exact: true })).toHaveAttribute('lang', 'en-IN')
  await expect(correction.getByText(kannadaCorrection, { exact: true })).toHaveAttribute('lang', locale)
  await expect(dialogue.getByText(nextReply)).toHaveCount(0)
  await expect(dialogue.getByRole('heading', { name: message('conversation.interactive.retry', locale) })).toBeVisible()
  await page.setViewportSize({ width: 320, height: 740 })
  await page.evaluate(() => { document.documentElement.style.fontSize = '200%' })
  expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320)
  expect((await new AxeBuilder({ page }).include('[data-mode="roleplay_dialogue"]').analyze()).violations).toEqual([])
  await correction.screenshot({ path: testInfo.outputPath('roleplay-kannada-correction-320.png') })
  expect(requests.filter((request) => request.path.endsWith('/turns'))).toHaveLength(1)
})

for (const theme of ['dark', 'light']) test(`checks ${theme} contrast through all Speak modes and optional reading`, async ({ page }, testInfo) => {
  const locale = 'kn-IN'
  await page.emulateMedia({ reducedMotion: 'reduce' })
  const { requests } = await setup(page, locale)
  await page.setViewportSize({ width: 390, height: 844 })
  await page.locator('#interface-theme').selectOption(theme)
  await expect(page.locator('html')).toHaveAttribute('data-ge-theme', theme)
  const contrast = async (step: string) => {
    await expect(page.locator('#application-error-title')).toHaveCount(0)
    const result = await new AxeBuilder({ page }).setLegacyMode().withRules(['color-contrast']).analyze()
    expect(result.violations, JSON.stringify(result.violations.map(v => v.nodes.map(n => ({target:n.target, summary:n.failureSummary}))))).toEqual([])
    await page.screenshot({ path: testInfo.outputPath(`${theme}-${step}.png`), fullPage: true })
  }
  for (const mode of ['situation', 'question_help', 'everyday']) {
    await page.locator(`#speak-tab-${mode}`).click()
    await contrast(mode)
  }
  await page.getByRole('checkbox', { name: message('conversation.interactive.accept_notice', locale) }).check()
  await page.getByRole('button', { name: message('conversation.start', locale), exact: true }).click()
  await expect(page.getByText(opening, { exact: true })).toBeVisible()
  await contrast('free-chat')
  await page.getByRole('button', { name: message('conversation.interactive.read_translation', locale), exact: true }).click()
  await expect(page.getByText(localAnswer, { exact: true })).toHaveAttribute('lang', locale)
  await expect(page.getByText(opening, { exact: true })).toBeVisible()
  await contrast('reading-aid')
  expect(requests.filter(r => r.body.action === 'accept_explanation').map(r => r.body.explanation_target)).toEqual(['opening'])
  expect(requests.filter(r => r.path.endsWith('/speech'))).toHaveLength(1)
})
