import { useEffect, useRef, useState } from 'react'
import type { MessageKey } from '@global-english/localization'
import { clearDeviceHistory, historyAvailable, historyEnabled, historyFailed, HISTORY_EVENT, listDeviceHistory, readDeviceHistory, removeDeviceHistory, setDeviceHistoryEnabled, type HistoryItem, type SavedAudio, type SavedConversationTurn } from './deviceConversationHistory'
import { playBase64Audio } from './playBase64Audio'

export function DeviceHistorySaveStatus({ text }: { readonly text: (key: MessageKey) => string }) {
  const [failed, setFailed] = useState(historyFailed)
  useEffect(() => { const update = () => setFailed(historyFailed()); window.addEventListener(HISTORY_EVENT, update); return () => window.removeEventListener(HISTORY_EVENT, update) }, [])
  return failed ? <p role="status">{text('conversation.history.error')}</p> : null
}

export function DeviceConversationHistoryPanel({ text }: { readonly text: (key: MessageKey) => string }) {
  const [enabled, setEnabled] = useState(historyEnabled)
  const [items, setItems] = useState<HistoryItem[]>([])
  const [selected, setSelected] = useState<SavedConversationTurn | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(false)
  const [confirmClear, setConfirmClear] = useState(false)
  const playback = useRef<AbortController | null>(null)
  const epoch = useRef(0)
  useEffect(() => {
    let disposed = false
    const refresh = () => {
      const expected = ++epoch.current
      playback.current?.abort(); setSelected(null); setEnabled(historyEnabled())
      void listDeviceHistory().then(value => { if (!disposed && epoch.current === expected) setItems(value) }).catch(() => { if (!disposed && epoch.current === expected) setError(true) })
    }
    refresh(); window.addEventListener(HISTORY_EVENT, refresh)
    return () => { disposed = true; playback.current?.abort(); window.removeEventListener(HISTORY_EVENT, refresh) }
  }, [])
  if (!historyAvailable()) return null
  const run = async (work: () => Promise<void>) => {
    if (busy) return
    setBusy(true); setError(false)
    try { await work() } catch { setError(true) } finally { setBusy(false) }
  }
  const play = async (audio: SavedAudio) => {
    playback.current?.abort(); const controller = new AbortController(); playback.current = controller
    try { await playBase64Audio(audio.base64, audio.mimeType, { signal: controller.signal }) }
    catch { if (!controller.signal.aborted) setError(true) }
  }
  return <section aria-labelledby="device-history-title">
    <h3 id="device-history-title">{text('conversation.history.title')}</h3>
    <p>{text('conversation.history.notice')}</p>
    <label><input checked={enabled} disabled={busy} onChange={event => void run(() => setDeviceHistoryEnabled(event.target.checked))} type="checkbox" />{text('conversation.history.enable')}</label>
    <p>{text('conversation.history.replay_free')}</p>
    {items.length ? <ul>{items.map(item => {
      const metadata = JSON.parse(item.metadata) as { savedAt: string; preview: string }
      return <li key={item.id}><button className="ge-button ge-button--ghost" disabled={busy} onClick={() => void run(async () => { playback.current?.abort(); setSelected(await readDeviceHistory(item.id)) })} type="button">{new Date(metadata.savedAt).toLocaleString()} — {metadata.preview}</button></li>
    })}</ul> : <p>{text('conversation.history.empty')}</p>}
    {selected ? <article className="conversation-journey__bubble">
      {selected.learner ? <p>{selected.learner}</p> : null}
      <p lang={selected.replyLocale}>{selected.reply}</p>
      {selected.correction ? <p lang="en-IN">{selected.correction}</p> : null}
      {selected.explanation ? <p>{selected.explanation}</p> : null}
      {selected.learnerAudio ? <button className="ge-button" onClick={() => void play(selected.learnerAudio!)} type="button">{text('conversation.history.play_mine')}</button> : null}
      {selected.replyAudio ? <button className="ge-button" onClick={() => void play(selected.replyAudio!)} type="button">{text('conversation.interactive.replay')}</button> : null}
      {selected.guidanceAudio ? <button className="ge-button" onClick={() => void play(selected.guidanceAudio!)} type="button">{text('conversation.history.play_guidance')}</button> : null}
      <button className="ge-button" disabled={busy} onClick={() => void run(async () => { playback.current?.abort(); await removeDeviceHistory(selected.id); setSelected(null) })} type="button">{text('conversation.history.delete_one')}</button>
    </article> : null}
    {items.length ? <button className="ge-button" disabled={busy} onClick={() => setConfirmClear(true)} type="button">{text('conversation.history.delete_all')}</button> : null}
    {confirmClear ? <div role="group" aria-label={text('conversation.history.delete_all')}>
      <p>{text('conversation.history.delete_confirm')}</p>
      <button className="ge-button" disabled={busy} onClick={() => void run(async () => { playback.current?.abort(); await clearDeviceHistory(); setConfirmClear(false) })} type="button">{text('conversation.history.delete_all')}</button>
      <button className="ge-button" disabled={busy} onClick={() => setConfirmClear(false)} type="button">{text('learner.recording.cancel')}</button>
    </div> : null}
    {error ? <p role="alert">{text('conversation.history.error')}</p> : null}
  </section>
}
