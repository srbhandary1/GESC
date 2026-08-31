#!/usr/bin/env bash
# Generates the static-IP edge without weakening authenticated sessions.

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

  local requested_public_origin="${public_origin}"

  # Static-IP / WAN access: serve the SPA and API on :8080 instead of
  # bouncing to a rotating HTTPS tunnel the remote client cannot use.
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
	redir * ${public_origin}{uri} 307
}
EOF
    return
  fi

  local extra_https_hosts="${9:-}"
  local https_site_labels=""
  local https_name
  local https_seen=" "
  append_https_site_label() {
    local ip_name="$1"
    if [[ ! "${ip_name}" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]; then
      return 0
    fi
    if [[ "${https_seen}" == *" ${ip_name} "* ]]; then
      return 0
    fi
    https_seen+="${ip_name} "
    if [[ -n "${https_site_labels}" ]]; then
      https_site_labels+=", "
    fi
    https_site_labels+="https://${ip_name}:${public_https_port}"
  }
  append_https_site_label "127.0.0.1"
  if [[ "${requested_public_origin}" =~ ^http://([0-9]{1,3}\.){3}[0-9]{1,3}:[0-9]+$ ]]; then
    append_https_site_label "$(printf '%s' "${requested_public_origin}" | sed -E 's#^http://##' | sed -E 's/:.*//')"
  fi
  if [[ -n "${extra_https_hosts}" ]]; then
    IFS=',' read -ra extra_https_host_array <<< "${extra_https_hosts}"
    for https_name in "${extra_https_host_array[@]}"; do
      append_https_site_label "${https_name}"
    done
  fi
  [[ -n "${https_site_labels}" ]] || https_site_labels="https://127.0.0.1:${public_https_port}"

  # D-0256: keep authenticated HTTP on :8080 (D-0248 cookies) and add a
  # same-app HTTPS listener on named :8443 addresses with Caddy's local
  # CA so browsers expose getUserMedia on LAN/WAN IP origins. The HTTP
  # site must be scheme-qualified (`http://:8080`); a hostname-less
  # `:8080` plus `:8443 { tls internal }` conflicts Caddy's default TLS
  # automation policy. `auto_https disable_redirects` lets Caddy issue
  # internal certificates without binding :80 or redirecting :8080.
  # Certificates are not publicly trusted; learners accept the browser
  # warning once. `skip_install_trust` avoids mutating the host trust store.
  cat >"${target_file}" <<EOF
{
	admin off
	auto_https disable_redirects
	skip_install_trust
}
http://:${public_port} {
	@notDownloads not path /downloads/*
	encode @notDownloads gzip
	handle /api/* {
		reverse_proxy 127.0.0.1:${api_port}
	}
	handle /sanctum/* {
		reverse_proxy 127.0.0.1:${api_port}
	}
	handle /oauth/* {
		reverse_proxy 127.0.0.1:${api_port}
	}
	handle /auth/mobile/* {
		reverse_proxy 127.0.0.1:${api_port}
	}
	handle /health/live {
		rewrite * /api/v1/health/live
		reverse_proxy 127.0.0.1:${api_port}
	}
	handle /health/ready {
		rewrite * /api/v1/health/ready
		reverse_proxy 127.0.0.1:${api_port}
	}
	@credentialPages path /password-establishment.html /password-recovery.html
	handle @credentialPages {
		header Cache-Control "no-store, max-age=0"
		header Referrer-Policy "no-referrer"
		header X-Robots-Tag "noindex, nofollow"
		root * ${web_dist}
		file_server
	}
	handle /downloads/* {
		header Cache-Control "public, max-age=300, must-revalidate"
		header Content-Type "application/octet-stream"
		header Content-Disposition "attachment; filename=\"global-spoken-english.apk\""
		header X-Content-Type-Options "nosniff"
		root * ${web_dist}
		file_server
	}
	handle {
		root * ${web_dist}
		try_files {path} /index.html
		file_server
	}
}
${https_site_labels} {
	tls internal
	@notDownloads not path /downloads/*
	encode @notDownloads gzip
	handle /api/* {
		reverse_proxy 127.0.0.1:${api_port}
	}
	handle /sanctum/* {
		reverse_proxy 127.0.0.1:${api_port}
	}
	handle /oauth/* {
		reverse_proxy 127.0.0.1:${api_port}
	}
	handle /auth/mobile/* {
		reverse_proxy 127.0.0.1:${api_port}
	}
	handle /health/live {
		rewrite * /api/v1/health/live
		reverse_proxy 127.0.0.1:${api_port}
	}
	handle /health/ready {
		rewrite * /api/v1/health/ready
		reverse_proxy 127.0.0.1:${api_port}
	}
	@credentialPages path /password-establishment.html /password-recovery.html
	handle @credentialPages {
		header Cache-Control "no-store, max-age=0"
		header Referrer-Policy "no-referrer"
		header X-Robots-Tag "noindex, nofollow"
		root * ${web_dist}
		file_server
	}
	handle /downloads/* {
		header Cache-Control "public, max-age=300, must-revalidate"
		header Content-Type "application/octet-stream"
		header Content-Disposition "attachment; filename=\"global-spoken-english.apk\""
		header X-Content-Type-Options "nosniff"
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
