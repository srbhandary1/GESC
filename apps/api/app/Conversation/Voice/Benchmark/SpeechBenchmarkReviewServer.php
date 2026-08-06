<?php

declare(strict_types=1);

namespace App\Conversation\Voice\Benchmark;

use JsonException;
use RuntimeException;

final readonly class SpeechBenchmarkReviewServer
{
    private const int INACTIVITY_SECONDS = 1800;

    private const int ABSOLUTE_SECONDS = 14_400;

    public function __construct(
        private SpeechBenchmarkRunStore $store,
    ) {}

    /**
     * @param  callable(string): void  $announce
     * @return array<string, int>
     */
    public function serve(
        string $runId,
        int $listenerSlot,
        callable $announce,
    ): array {
        $this->store->reviewManifest($runId);
        $token = bin2hex(random_bytes(32));
        $errorCode = 0;
        $errorMessage = '';
        $socket = stream_socket_server(
            'tcp://127.0.0.1:0',
            $errorCode,
            $errorMessage,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
        );
        if ($socket === false) {
            throw new RuntimeException('The loopback review listener could not start.');
        }

        $address = stream_socket_get_name($socket, false);
        if (
            ! is_string($address)
            || preg_match('/\A127\.0\.0\.1:(\d+)\z/', $address, $match) !== 1
        ) {
            fclose($socket);
            throw new RuntimeException('The review listener did not bind to loopback.');
        }

        $announce('http://127.0.0.1:'.$match[1].'/#token='.$token);
        $startedAt = time();
        $lastActivityAt = $startedAt;
        $completed = false;
        $aggregate = [];

        try {
            while (
                ! $completed
                && time() - $startedAt < self::ABSOLUTE_SECONDS
                && time() - $lastActivityAt < self::INACTIVITY_SECONDS
            ) {
                $read = [$socket];
                $write = [];
                $except = [];
                $selected = stream_select($read, $write, $except, 1);
                if ($selected === false) {
                    throw new RuntimeException('The review listener failed.');
                }
                if ($selected === 0) {
                    continue;
                }

                $connection = stream_socket_accept($socket, 1);
                if ($connection === false) {
                    continue;
                }
                try {
                    $peer = stream_socket_get_name($connection, true);
                    if (
                        ! is_string($peer)
                        || ! str_starts_with($peer, '127.0.0.1:')
                    ) {
                        $this->writeResponse(
                            $connection,
                            $this->response(403, 'Forbidden'),
                        );

                        continue;
                    }

                    try {
                        $request = $this->readRequest($connection);
                        $response = $this->handle(
                            $runId,
                            $listenerSlot,
                            $token,
                            $request['method'],
                            $request['path'],
                            $request['headers'],
                            $request['body'],
                        );
                    } catch (RuntimeException|JsonException) {
                        // Keep the review session alive across malformed probes
                        // (health checks, premature browser disconnects, etc.).
                        $response = $this->response(400, 'Bad Request');
                    }
                    $this->writeResponse($connection, $response);
                    $lastActivityAt = time();
                    if (
                        ($response['review_completed'] ?? false) === true
                        && isset($response['aggregate'])
                    ) {
                        $completed = true;
                        $aggregate = $response['aggregate'];
                    }
                } catch (RuntimeException) {
                    // Response write failures must not abort the whole session.
                    continue;
                } finally {
                    fclose($connection);
                }
            }
        } finally {
            fclose($socket);
        }

        if (! $completed) {
            throw new RuntimeException('The listener review session expired.');
        }

        return $aggregate;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{
     *   status: int,
     *   headers: array<string, string>,
     *   body: string,
     *   review_completed?: bool,
     *   aggregate?: array<string, int>
     * }
     */
    public function handle(
        string $runId,
        int $listenerSlot,
        string $token,
        string $method,
        string $path,
        array $headers,
        string $body,
    ): array {
        foreach (
            ['forwarded', 'x-forwarded-for', 'x-forwarded-host', 'x-real-ip'] as $proxyHeader
        ) {
            if (isset($headers[$proxyHeader])) {
                return $this->response(403, 'Forbidden');
            }
        }
        if (
            isset($headers['host'])
            && preg_match('/\A127\.0\.0\.1:\d+\z/', $headers['host']) !== 1
        ) {
            return $this->response(403, 'Forbidden');
        }
        if (
            isset($headers['origin'])
            && preg_match(
                '#\Ahttp://127\.0\.0\.1:\d+\z#',
                $headers['origin'],
            ) !== 1
        ) {
            return $this->response(403, 'Forbidden');
        }

        if ($method === 'GET' && $path === '/') {
            return $this->response(
                200,
                $this->page(),
                'text/html; charset=utf-8',
            );
        }
        if ($method === 'GET' && $path === '/review.js') {
            return $this->response(
                200,
                $this->script(),
                'text/javascript; charset=utf-8',
            );
        }
        if (! $this->authorized($headers, $token)) {
            return $this->response(401, 'Unauthorized');
        }

        if ($method === 'GET' && $path === '/api/manifest') {
            return $this->jsonResponse([
                'fixtures' => $this->store->reviewManifest($runId),
            ]);
        }

        if (
            $method === 'GET'
            && preg_match(
                '#\A/api/audio/([a-f0-9]{32})\z#',
                $path,
                $match,
            ) === 1
        ) {
            return $this->response(
                200,
                $this->store->audio($runId, $match[1]),
                'audio/wav',
            );
        }

        if ($method === 'POST' && $path === '/api/submit') {
            if (strlen($body) > 128 * 1024) {
                return $this->response(413, 'Payload Too Large');
            }
            try {
                $payload = json_decode(
                    $body,
                    true,
                    8,
                    JSON_THROW_ON_ERROR,
                );
            } catch (JsonException) {
                return $this->response(400, 'Bad Request');
            }
            if (! is_array($payload) || ! is_array($payload['choices'] ?? null)) {
                return $this->response(400, 'Bad Request');
            }

            try {
                $aggregate = $this->store->recordReview(
                    $runId,
                    $listenerSlot,
                    $payload['choices'],
                );
            } catch (RuntimeException) {
                return $this->response(409, 'Conflict');
            }
            $response = $this->jsonResponse([
                'status' => 'recorded',
                'output_count' => $aggregate['output_count'],
                'reviewer_count' => $aggregate['reviewer_count'],
            ]);
            $response['review_completed'] = true;
            $response['aggregate'] = $aggregate;

            return $response;
        }

        return $this->response(404, 'Not Found');
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function authorized(array $headers, string $token): bool
    {
        $authorization = $headers['authorization'] ?? '';

        return strlen($token) === 64
            && preg_match('/\A[a-f0-9]{64}\z/', $token) === 1
            && hash_equals('Bearer '.$token, $authorization);
    }

    /**
     * @return array{
     *   method: string,
     *   path: string,
     *   headers: array<string, string>,
     *   body: string
     * }
     */
    private function readRequest(mixed $connection): array
    {
        stream_set_timeout($connection, 5);
        $requestLine = fgets($connection, 8192);
        if (
            ! is_string($requestLine)
            || preg_match(
                '#\A(GET|POST) ([^ ]+) HTTP/1\.[01]\r?\n\z#',
                $requestLine,
                $match,
            ) !== 1
        ) {
            throw new RuntimeException('The loopback review request is invalid.');
        }
        $headers = [];
        $headerBytes = 0;
        while (($line = fgets($connection, 8192)) !== false) {
            $headerBytes += strlen($line);
            if ($headerBytes > 16 * 1024) {
                throw new RuntimeException('The loopback review headers are too large.');
            }
            if ($line === "\r\n" || $line === "\n") {
                break;
            }
            $position = strpos($line, ':');
            if ($position === false) {
                throw new RuntimeException('The loopback review headers are invalid.');
            }
            $name = strtolower(trim(substr($line, 0, $position)));
            $value = trim(substr($line, $position + 1));
            if ($name === '' || isset($headers[$name])) {
                throw new RuntimeException('The loopback review headers are invalid.');
            }
            $headers[$name] = $value;
        }

        $contentLength = isset($headers['content-length'])
            ? filter_var($headers['content-length'], FILTER_VALIDATE_INT)
            : 0;
        if (
            $contentLength === false
            || $contentLength < 0
            || $contentLength > 128 * 1024
        ) {
            throw new RuntimeException('The loopback review body is invalid.');
        }
        $body = '';
        while (strlen($body) < $contentLength) {
            $remaining = max(1, $contentLength - strlen($body));
            $chunk = fread($connection, $remaining);
            if (! is_string($chunk) || $chunk === '') {
                throw new RuntimeException('The loopback review body is incomplete.');
            }
            $body .= $chunk;
        }

        $path = parse_url($match[2], PHP_URL_PATH);
        if (! is_string($path)) {
            throw new RuntimeException('The loopback review path is invalid.');
        }

        return [
            'method' => $match[1],
            'path' => $path,
            'headers' => $headers,
            'body' => $body,
        ];
    }

    /**
     * @param  array{
     *   status: int,
     *   headers: array<string, string>,
     *   body: string
     * }  $response
     */
    private function writeResponse(mixed $connection, array $response): void
    {
        $reason = match ($response['status']) {
            200 => 'OK',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            409 => 'Conflict',
            413 => 'Payload Too Large',
            default => 'Error',
        };
        $head = 'HTTP/1.1 '.$response['status'].' '.$reason."\r\n";
        foreach ($response['headers'] as $name => $value) {
            $head .= $name.': '.$value."\r\n";
        }
        $head .= 'Content-Length: '.strlen($response['body'])."\r\n";
        $head .= "Connection: close\r\n\r\n";
        $bytes = $head.$response['body'];
        while ($bytes !== '') {
            $written = fwrite($connection, $bytes);
            if ($written === false || $written === 0) {
                throw new RuntimeException(
                    'The loopback review response failed.',
                );
            }
            $bytes = substr($bytes, $written);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *   status: int,
     *   headers: array<string, string>,
     *   body: string
     * }
     */
    private function jsonResponse(array $payload): array
    {
        return $this->response(
            200,
            json_encode(
                $payload,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
            ),
            'application/json; charset=utf-8',
        );
    }

    /**
     * @return array{
     *   status: int,
     *   headers: array<string, string>,
     *   body: string
     * }
     */
    private function response(
        int $status,
        string $body,
        string $contentType = 'text/plain; charset=utf-8',
    ): array {
        return [
            'status' => $status,
            'headers' => [
                'Content-Type' => $contentType,
                'Cache-Control' => 'no-store, max-age=0',
                'Pragma' => 'no-cache',
                'Content-Security-Policy' => "default-src 'none'; "
                    ."script-src 'self'; connect-src 'self'; "
                    ."media-src 'self' blob:; style-src 'unsafe-inline'; "
                    ."base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
                'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'Cross-Origin-Resource-Policy' => 'same-origin',
            ],
            'body' => $body,
        ];
    }

    private function page(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Synthetic speech fixture review</title>
  <style>
    body{font:16px system-ui,sans-serif;max-width:48rem;margin:2rem auto;padding:0 1rem}
    fieldset{margin:1rem 0;padding:1rem}button{min-height:44px;margin:.5rem .5rem .5rem 0}
    label{display:block;margin:.5rem 0}.notice{padding:1rem;background:#fff3cd}
  </style>
</head>
<body>
  <main>
    <h1>Synthetic speech fixture review</h1>
    <p class="notice">Development-only synthetic audio. Audio and choices stay in browser memory until submission.</p>
    <div id="review" aria-live="polite">Loading…</div>
  </main>
  <script src="/review.js"></script>
</body>
</html>
HTML;
    }

    private function script(): string
    {
        return <<<'JS'
(() => {
  'use strict';
  const root = document.getElementById('review');
  let bearer = new URLSearchParams(location.hash.slice(1)).get('token') || '';
  let fixtures = [];
  let index = 0;
  const choices = [];
  let activeUrl = null;
  let activeAudio = null;
  const request = (path, options = {}) => fetch(path, {
    ...options,
    cache: 'no-store',
    credentials: 'omit',
    headers: {
      ...(options.headers || {}),
      Authorization: `Bearer ${bearer}`
    }
  });
  const stopAudio = () => {
    if (activeAudio) {
      activeAudio.pause();
      activeAudio.removeAttribute('src');
      activeAudio.load();
    }
    activeAudio = null;
    if (activeUrl) URL.revokeObjectURL(activeUrl);
    activeUrl = null;
  };
  const render = () => {
    stopAudio();
    const fixture = fixtures[index];
    if (!fixture) return submit();
    root.replaceChildren();
    const heading = document.createElement('h2');
    heading.textContent = `Fixture ${index + 1} of ${fixtures.length}`;
    const detail = document.createElement('p');
    detail.textContent = `${fixture.fixture_id} · ${fixture.voice}`;
    const source = document.createElement('blockquote');
    source.textContent = fixture.source_text;
    const play = document.createElement('button');
    play.type = 'button';
    play.textContent = 'Play in memory';
    play.addEventListener('click', async () => {
      stopAudio();
      const response = await request(`/api/audio/${fixture.review_id}`);
      if (!response.ok) throw new Error('Audio unavailable');
      const bytes = await response.arrayBuffer();
      activeUrl = URL.createObjectURL(new Blob([bytes], {type: 'audio/wav'}));
      const audio = new Audio(activeUrl);
      activeAudio = audio;
      audio.addEventListener('ended', stopAudio, {once: true});
      audio.addEventListener('error', stopAudio, {once: true});
      try {
        await audio.play();
      } catch {
        stopAudio();
        throw new Error('Audio playback failed');
      }
    });
    const form = document.createElement('fieldset');
    const legend = document.createElement('legend');
    legend.textContent = 'Disposition';
    form.append(legend);
    const fields = [
      ['intelligible', 'Intelligible'],
      ['acceptable', 'Acceptable'],
      ['critical_defect', 'Critical pronunciation defect']
    ];
    for (const [name, labelText] of fields) {
      const prompt = document.createElement('p');
      prompt.textContent = labelText;
      form.append(prompt);
      for (const [value, answer] of [['yes', 'Yes'], ['no', 'No']]) {
        const label = document.createElement('label');
        const input = document.createElement('input');
        input.type = 'radio';
        input.name = name;
        input.value = value;
        input.required = true;
        label.append(input, ` ${answer}`);
        form.append(label);
      }
    }
    const next = document.createElement('button');
    next.type = 'button';
    next.textContent = index + 1 === fixtures.length ? 'Submit review' : 'Next fixture';
    next.addEventListener('click', () => {
      if (!form.reportValidity()) return;
      const values = Object.fromEntries(
        fields.map(([name]) => [
          name,
          form.querySelector(`[name="${name}"]:checked`).value === 'yes'
        ])
      );
      choices.push({review_id: fixture.review_id, ...values});
      index += 1;
      render();
    });
    root.append(heading, detail, source, play, form, next);
  };
  const submit = async () => {
    stopAudio();
    root.textContent = 'Submitting…';
    const response = await request('/api/submit', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({choices})
    });
    root.textContent = response.ok
      ? 'Review recorded. You may close this page.'
      : 'Review was not recorded. Close this page and ask the operator to retry.';
  };
  const begin = () => {
    request('/api/manifest')
      .then(response => response.ok ? response.json() : Promise.reject())
      .then(payload => { fixtures = payload.fixtures; render(); })
      .catch(() => { root.textContent = 'The review session is unavailable.'; });
  };
  const askForToken = () => {
    root.replaceChildren();
    const note = document.createElement('p');
    note.textContent = 'The #token fragment was missing (often stripped when opening from chat). Paste the 64-character hex token, then continue.';
    const input = document.createElement('input');
    input.type = 'text';
    input.autocomplete = 'off';
    input.spellcheck = false;
    input.placeholder = '64-character hex token';
    input.style.width = '100%';
    input.setAttribute('aria-label', 'Review token');
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = 'Continue review';
    button.addEventListener('click', () => {
      const value = input.value.trim().toLowerCase();
      if (!/^[a-f0-9]{64}$/.test(value)) {
        note.textContent = 'Token must be exactly 64 lowercase hex characters. Try again.';
        return;
      }
      bearer = value;
      location.hash = `token=${value}`;
      begin();
    });
    root.append(note, input, button);
  };
  addEventListener('pagehide', stopAudio);
  if (!/^[a-f0-9]{64}$/.test(bearer)) {
    askForToken();
    return;
  }
  begin();
})();
JS;
    }
}
