#!/usr/bin/env bash
# Fail closed unless the Wave 5 stable host is actually reachable.
# Does not print Cloudflare tokens, learner data, or secrets.
set -euo pipefail

HOST="${GE_SOFT_LAUNCH_STABLE_HOST:-ai.globalspokenenglish.com}"
EXPECTED_LAN="${GE_SOFT_LAUNCH_LAN_IPV4:-192.168.1.4}"
fail=0

note() { printf '[FAIL] %s\n' "$*"; fail=1; }
ok() { printf '[PASS] %s\n' "$*"; }

if [[ ! "${HOST}" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])$ ]] || [[ "${HOST}" != *.* ]]; then
  printf 'HOST must be a lowercase DNS hostname.\n' >&2
  exit 2
fi

public_ip="$(curl -fsS --max-time 8 https://api.ipify.org || true)"
if [[ ! "${public_ip}" =~ ^[0-9.]+$ ]]; then
  note "this host's public IPv4 could not be read"
else
  ok "this host public IPv4 is set (not printed)"
fi

dns_a="$(dig +short A "${HOST}" @1.1.1.1 | awk 'NF && $1 ~ /^[0-9.]+$/' | head -1)"
if [[ -z "${dns_a}" ]]; then
  note "${HOST} has no A record at Cloudflare DNS"
else
  ok "${HOST} has an A record"
  if [[ -n "${public_ip}" && "${dns_a}" != "${public_ip}" ]]; then
    note "${HOST} A record does not match this host's public IPv4"
  elif [[ -n "${public_ip}" ]]; then
    ok "${HOST} A record matches this host's public IPv4"
  fi
fi

lan="$(ip -4 -o addr show scope global | awk '/inet 192.168./ {print $4}' | head -1)"
lan_addr="${lan%%/*}"
if [[ "${lan_addr}" != "${EXPECTED_LAN}" ]]; then
  note "LAN IPv4 is ${lan_addr:-unknown}; router maps must use this address (runbook example 192.168.1.3 may be stale)"
else
  ok "LAN IPv4 matches ${EXPECTED_LAN}"
fi

http_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 8 "http://${HOST}/" 2>/dev/null || true)"
http_code="${http_code:-000}"
if curl -fsS --max-time 8 "https://${HOST}/api/v1/health/ready" >/dev/null 2>&1; then
  https_ready=yes
else
  https_ready=no
fi
if [[ "${http_code}" != "307" && "${http_code}" != "308" && "${http_code}" != "301" ]]; then
  note "http://${HOST}/ did not HTTP-redirect to HTTPS (code ${http_code})"
else
  ok "http://${HOST}/ redirects"
fi
if [[ "${https_ready}" != yes ]]; then
  note "https://${HOST}/api/v1/health/ready is not ready"
else
  ok "https://${HOST}/api/v1/health/ready"
fi

printf '\nStable-host prerequisites: %s\n' "$([[ "${fail}" -eq 0 ]] && printf pass || printf fail)"
exit "${fail}"
