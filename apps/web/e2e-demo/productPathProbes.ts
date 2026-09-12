/** Same-origin LessonPractice probes when the Laravel API is down. */
export function isLessonPracticeProbe(url: string): boolean {
  const path = new URL(url).pathname
  return (
    path === '/sanctum/csrf-cookie' ||
    path === '/api/v1/me/levels' ||
    /^\/api\/v1\/me\/levels\/\d+(?:\/sessions\/[\w-]+(?:\/(?:audio|completions|check-submissions|conversation-sessions))?)?$/.test(
      path,
    )
  )
}
