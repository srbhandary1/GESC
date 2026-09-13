const CONCEPT_LABELS: Record<string, string> = {
  can: 'Can — ability and permission',
  should: 'Should — advice and suggestions',
  do_does: 'Do / Does — present habits',
  is_are_ing: 'Am / Is / Are + -ing — happening now',
  did: 'Did — past actions',
  was_were_ing: 'Was / Were + -ing — past continuous',
  have_has: 'Have / Has — experience and results',
  have_been_has_been: 'Have been / Has been + -ing — still continuing',
  had: 'Had — earlier past',
  will: 'Will — future plans',
  gonna_wanna: 'Gonna / Wanna — informal speech',
  would: 'Would — polite requests',
  may: 'May — permission and possibility',
  if: 'If — real conditions',
  must: 'Must — rules and obligations',
  should_have: 'Should have — past advice',
  used_to: 'Used to — past habits',
}

export function conceptLabel(conceptKey: string | null | undefined): string | null {
  if (conceptKey === null || conceptKey === undefined || conceptKey === '') {
    return null
  }

  return CONCEPT_LABELS[conceptKey] ?? conceptKey.replaceAll('_', ' ')
}
