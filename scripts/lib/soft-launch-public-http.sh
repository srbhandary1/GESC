#!/usr/bin/env bash
# Generates the static-IP edge without weakening browser sessions.
# When a temporary HTTPS origin is live, HTML still 307-redirects there.
# /api and /oauth reverse-proxy so the closed-beta Android HTTP origin on
# port 8080 can reach Passport without depending on the tunnel hostname.

write_soft_launch_public_http_config() {
  local target_file="$1"
  local public_port="$2"
  local api_port="$3"
  local web_dist="$4"
  local public_origin="${5:-}"
  local stable_host="${6:-}"
  local public_https_port="${7:-8443}"
  local proxy_port="${8:-9080}"

  if [[ -n "${stable_host}" ]]; then
    if [[ ! "${stable_host}" =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])$ ]] \
      || [[ "${stable_host}" != *.* ]] \
      || [[ "${public_origin}" != "https://${stable_host}" ]] \
      || [[ ! "${public_https_port}" =~ ^[0-9]+$ ]] \
      || (( public_https_port < 1024 || public_https_port > 65535 )); then
      printf '%s\n' 'Invalid stable HTTPS edge configuration.' >&2
      return 1
    fi

    cat >"${target_file}" <<EOF
{
	admin off
	http_port ${public_port}
	https_port ${public_https_port}
}

http:// {
	redir * https://${stable_host}{uri} 307
}

${stable_host} {
	encode gzip
	header {
		Strict-Transport-Security "max-age=31536000"
		X-Content-Type-Options "nosniff"
		X-Frame-Options "DENY"
		Referrer-Policy "strict-origin-when-cross-origin"
	}
	reverse_proxy 127.0.0.1:${proxy_port}
}
EOF
    return
  fi

  # Staff Tailscale/LAN testing: serve the SPA and API on :8080 instead of
  # 307-redirecting browsers to the rotating HTTPS tunnel.
  if [[ "${GE_SOFT_LAUNCH_SERVE_PUBLIC_HTTP:-}" == "true" ]]; then
    public_origin=""
  fi

  if [[ "${public_origin}" =~ ^https://[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?(:[0-9]{1,5})?$ ]]; then
    cat >"${target_file}" <<EOF
{
	admin off
	auto_https off
}
:${public_port} {
	encode gzip
	handle /api/* {
		reverse_proxy 127.0.0.1:${api_port}
	}
	handle /oauth/* {
		reverse_proxy 127.0.0.1:${api_port}
	}
	handle {
		redir * ${public_origin}{uri} 307
	}
}
EOF
    return
  fi

  cat >"${target_file}" <<EOF
{
	admin off
	auto_https off
}
:${public_port} {
	encode gzip
	handle /api/* {
		reverse_proxy 127.0.0.1:${api_port}
	}
	handle /sanctum/* {
		reverse_proxy 127.0.0.1:${api_port}
	}
	handle /oauth/* {
		reverse_proxy 127.0.0.1:${api_port}
	}
	@androidApk path /downloads/*.apk
	handle @androidApk {
		encode off
		header Cache-Control "public, max-age=300"
		header Content-Type "application/vnd.android.package-archive"
		header Content-Disposition "attachment; filename=global-spoken-english-android.apk"
		root * ${web_dist}
		file_server
	}
	handle {
		root * ${web_dist}
		try_files {path} /index.html
		file_server
	}
}
EOF
}
