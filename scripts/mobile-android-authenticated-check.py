#!/usr/bin/env python3
"""Live authenticated mobile-path checks for soft-launch E2E."""

from __future__ import annotations

import base64
import hashlib
import json
import os
import subprocess
import sys
import urllib.parse

BASE = os.environ.get("MOBILE_E2E_API_ORIGIN", "http://127.0.0.1:8080")
CLIENT_ID = os.environ.get(
    "MOBILE_E2E_OAUTH_CLIENT_ID",
    "019fdd3f-4bba-720b-9e4e-a771ecccdd7f",
)
REDIRECT_URI = "com.globalenglish.app:/oauth/callback"
INSTALLATION_ID = "01J00000000000000000000010"
JAR = "/tmp/mobile-e2e-cookies.txt"


def run(args: list[str], *, check: bool = True) -> subprocess.CompletedProcess[str]:
    return subprocess.run(args, text=True, capture_output=True, check=check)


def curl(*args: str, check: bool = True) -> subprocess.CompletedProcess[str]:
    return run(["curl", "-sS", *args], check=check)


def main() -> int:
    username = os.environ.get("MOBILE_E2E_USERNAME", "")
    password = os.environ.get("MOBILE_E2E_PASSWORD", "")
    if not username or not password:
        print("[SKIP] MOBILE_E2E_USERNAME/PASSWORD not set")
        return 0

    curl("-c", JAR, "-b", JAR, f"{BASE}/sanctum/csrf-cookie")
    xsrf = None
    for line in open(JAR, encoding="utf-8"):
        if "XSRF-TOKEN" in line:
            xsrf = urllib.parse.unquote(line.split("\t")[-1].strip())
    if xsrf is None:
        print("[FAIL] Missing XSRF-TOKEN cookie")
        return 1

    sign_in = curl(
        "-c",
        JAR,
        "-b",
        JAR,
        "-X",
        "POST",
        f"{BASE}/api/v1/auth/password/sign-ins",
        "-H",
        "Accept: application/json",
        "-H",
        "Content-Type: application/json",
        "-H",
        f"X-XSRF-TOKEN: {xsrf}",
        "-H",
        f"Referer: {BASE}/",
        "--data",
        json.dumps({"username": username, "password": password}),
        "-w",
        "\n%{http_code}",
    )
    sign_in_code = sign_in.stdout.strip().splitlines()[-1]
    if sign_in_code != "200":
        print(f"[FAIL] Password sign-in returned HTTP {sign_in_code}")
        print(sign_in.stdout)
        return 1
    print("[PASS] Live password sign-in succeeded")

    verifier = base64.urlsafe_b64encode(os.urandom(48)).decode().rstrip("=")
    challenge = (
        base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest())
        .decode()
        .rstrip("=")
    )
    state = base64.urlsafe_b64encode(os.urandom(24)).decode().rstrip("=")
    params = urllib.parse.urlencode(
        {
            "client_id": CLIENT_ID,
            "response_type": "code",
            "redirect_uri": REDIRECT_URI,
            "scope": "mobile:api",
            "state": state,
            "code_challenge": challenge,
            "code_challenge_method": "S256",
        }
    )
    authorize = curl(
        "-D",
        "-",
        "-o",
        "/dev/null",
        "-b",
        JAR,
        f"{BASE}/oauth/authorize?{params}",
    )
    location = next(
        line.split(":", 1)[1].strip()
        for line in authorize.stdout.splitlines()
        if line.lower().startswith("location:")
    )
    parsed = urllib.parse.urlparse(location)
    query = urllib.parse.parse_qs(parsed.query)
    if query.get("state", [None])[0] != state or "code" not in query:
        print("[FAIL] OAuth authorize redirect missing code/state")
        return 1
    print("[PASS] Mobile OAuth authorize returned authorization code")

    token = curl(
        "-X",
        "POST",
        f"{BASE}/oauth/token",
        "-H",
        "Accept: application/json",
        "-H",
        "Content-Type: application/x-www-form-urlencoded",
        "--data-urlencode",
        "grant_type=authorization_code",
        "--data-urlencode",
        f"client_id={CLIENT_ID}",
        "--data-urlencode",
        f"redirect_uri={REDIRECT_URI}",
        "--data-urlencode",
        f"code={query['code'][0]}",
        "--data-urlencode",
        f"code_verifier={verifier}",
        "--data-urlencode",
        f"installation_id={INSTALLATION_ID}",
    )
    tokens = json.loads(token.stdout)
    access = tokens.get("access_token")
    refresh = tokens.get("refresh_token")
    if not isinstance(access, str) or not isinstance(refresh, str):
        print("[FAIL] OAuth token exchange did not return tokens")
        print(token.stdout)
        return 1
    print("[PASS] Mobile OAuth token exchange succeeded")

    me = curl(
        f"{BASE}/api/v1/me",
        "-H",
        f"Authorization: Bearer {access}",
        "-H",
        "Accept: application/json",
        "-w",
        "\n%{http_code}",
    )
    me_code = me.stdout.strip().splitlines()[-1]
    me_body = json.loads("\n".join(me.stdout.strip().splitlines()[:-1]))
    if me_code != "200":
        print(f"[FAIL] Bearer /me returned HTTP {me_code}")
        return 1
    print(
        "[PASS] Bearer identity loaded for user "
        f"{me_body.get('user', {}).get('id', 'unknown')}"
    )

    home = curl(
        f"{BASE}/api/v1/home",
        "-H",
        f"Authorization: Bearer {access}",
        "-H",
        "Accept: application/json",
        "-w",
        "\n%{http_code}",
    )
    home_code = home.stdout.strip().splitlines()[-1]
    if home_code != "200":
        print(f"[FAIL] Bearer /home returned HTTP {home_code}")
        return 1
    print("[PASS] Learner home reachable with mobile bearer token")

    levels = curl(
        f"{BASE}/api/v1/me/levels",
        "-H",
        f"Authorization: Bearer {access}",
        "-H",
        "Accept: application/json",
        "-w",
        "\n%{http_code}",
    )
    levels_code = levels.stdout.strip().splitlines()[-1]
    levels_body = json.loads("\n".join(levels.stdout.strip().splitlines()[:-1]))
    if levels_code != "200" or not levels_body.get("levels"):
        print(f"[FAIL] Bearer /me/levels returned HTTP {levels_code}")
        return 1
    print(
        "[PASS] Level path reachable with mobile bearer token "
        f"({len(levels_body['levels'])} levels)"
    )

    session = curl(
        f"{BASE}/api/v1/me/levels/1/sessions/introduction",
        "-H",
        f"Authorization: Bearer {access}",
        "-H",
        "Accept: application/json",
        "-w",
        "\n%{http_code}",
    )
    session_code = session.stdout.strip().splitlines()[-1]
    if session_code != "200":
        print(f"[FAIL] Level session payload returned HTTP {session_code}")
        return 1
    print("[PASS] Level session content reachable for recording journey")

    return 0


if __name__ == "__main__":
    sys.exit(main())
