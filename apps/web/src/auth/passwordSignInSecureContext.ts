export interface PasswordSignInLocation {
  readonly hostname: string
  readonly protocol: string
  readonly port?: string
}

function isLoopbackHost(host: string): boolean {
  return host === '127.0.0.1' || host === 'localhost' || host === '::1'
}

function effectivePort(location: PasswordSignInLocation): string {
  if (location.port !== undefined && location.port !== '') {
    return location.port
  }

  return location.protocol === 'https:' ? '443' : '80'
}

function isStaffClosedBetaHttp(location: PasswordSignInLocation): boolean {
  return (
    location.protocol === 'http:' &&
    location.hostname === '117.254.108.143' &&
    effectivePort(location) === '8080'
  )
}

function isPrivateIpv4Host(host: string): boolean {
  const match = /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/.exec(host)
  if (match === null) {
    return false
  }

  const octets = match.slice(1, 5).map(Number)
  if (octets.some((octet) => octet > 255)) {
    return false
  }

  const first = octets[0]
  const second = octets[1]

  if (first === undefined || second === undefined) {
    return false
  }

  if (first === 10) {
    return true
  }

  if (first === 172 && second >= 16 && second <= 31) {
    return true
  }

  if (first === 192 && second === 168) {
    return true
  }

  // Tailscale and other RFC6598 shared address space.
  if (first === 100 && second >= 64 && second <= 127) {
    return true
  }

  return false
}

export function isPasswordSignInSecureContext(
  location: PasswordSignInLocation = window.location,
): boolean {
  if (location.protocol === 'https:') {
    return true
  }

  if (isStaffClosedBetaHttp(location)) {
    return true
  }

  const host = location.hostname.toLowerCase()

  if (isLoopbackHost(host)) {
    return true
  }

  if (host.endsWith('.ts.net')) {
    return true
  }

  return isPrivateIpv4Host(host)
}
