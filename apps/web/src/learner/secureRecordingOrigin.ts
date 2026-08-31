export const SOFT_LAUNCH_RECORDING_HTTPS_PORT = 8443

const LOOPBACK_HOSTS = new Set(['localhost', '127.0.0.1', '[::1]'])

export function lanHttpsRecordingHref(
  location: Pick<Location, 'protocol' | 'hostname' | 'pathname' | 'hash'>,
): string | null {
  if (location.protocol === 'https:') {
    return null
  }

  if (LOOPBACK_HOSTS.has(location.hostname)) {
    return null
  }

  return `https://${location.hostname}:${SOFT_LAUNCH_RECORDING_HTTPS_PORT}${location.pathname}${location.hash}`
}
