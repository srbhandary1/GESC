import type { MessageKey } from '@global-english/localization'

/** Static interface labels only; never substitute curriculum or assessment text. */
export const CONCEPT_MESSAGE_KEYS: Readonly<Record<string, MessageKey>> = {
  can: 'level.concept.can',
  should: 'level.concept.should',
  do_does: 'level.concept.do_does',
  is_are_ing: 'level.concept.is_are_ing',
  did: 'level.concept.did',
  was_were_ing: 'level.concept.was_were_ing',
  have_has: 'level.concept.have_has',
  have_been_has_been: 'level.concept.have_been_has_been',
  had: 'level.concept.had',
  will: 'level.concept.will',
  gonna_wanna: 'level.concept.gonna_wanna',
  would: 'level.concept.would',
  may: 'level.concept.may',
  if: 'level.concept.if',
  must: 'level.concept.must',
  should_have: 'level.concept.should_have',
  used_to: 'level.concept.used_to',
}
