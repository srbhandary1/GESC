#!/usr/bin/env bash
# Generates the static-IP port entry without weakening authenticated sessions.

write_soft_launch_public_http_config() {
  local target_file="$1"
  local public_port="$2"
  local api_port="$3"
  local web_dist="$4"
  local public_origin="${5:-}"

  if [[ "${public_origin}" =~ ^https://[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?(:[0-9]{1,5})?$ ]]; then
    cat >"${target_file}" <<EOF
{
	auto_https off
}
:${public_port} {
	redir * ${public_origin}{uri} 307
}
EOF
    return
  fi

  cat >"${target_file}" <<EOF
{
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
	handle {
		root * ${web_dist}
		try_files {path} /index.html
		file_server
	}
}
EOF
}
