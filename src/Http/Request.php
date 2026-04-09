<?php declare(strict_types=1);

namespace Merlin\Http;

use RuntimeException;

class Request
{
    private array $server;
    private array $get;
    private array $post;
    private array $files;
    private ?string $rawBody = null;
    private bool $trustProxyHeaders;
    private array $request;

    public function __construct(
        array $server = null,
        array $get = null,
        array $post = null,
        array $files = null,
        bool $trustProxyHeaders = false
    ) {
        $this->server = $server ?? $_SERVER;
        $this->get = $get ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->files = $files ?? $_FILES;
        $this->trustProxyHeaders = $trustProxyHeaders;
        // GET < POST (explicit order, independent of php.ini)
        $this->request = [...$this->get, ...$this->post];
    }

    // -------------------------
    // Body / JSON
    // -------------------------
    public function getBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = (string) @file_get_contents('php://input');
        }
        return $this->rawBody;
    }

    /**
     * @param bool $assoc
     * @return mixed
     * @throws RuntimeException
     */
    public function getJsonBody(bool $assoc = true): mixed
    {
        $body = $this->getBody();
        if ($body === '') {
            return null;
        }
        try {
            $data = json_decode($body, $assoc, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Failed to parse JSON body: ' . $e->getMessage(), 0, $e);
        }
        return $data;
    }

    // -------------------------
    // Parameter accessors (modern, short)
    // -------------------------
    public function input(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return $this->request;
        }
        return $this->request[$name] ?? $default;
    }

    public function query(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return $this->get;
        }
        return $this->get[$name] ?? $default;
    }

    public function post(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return $this->post;
        }
        return $this->post[$name] ?? $default;
    }

    public function server(?string $name = null, mixed $default = null): mixed
    {
        if ($name === null) {
            return $this->server;
        }
        return $this->server[$name] ?? $default;
    }

    // -------------------------
    // Has checks
    // -------------------------
    public function hasInput(string $name): bool
    {
        return isset($this->request[$name]);
    }

    public function hasQuery(string $name): bool
    {
        return isset($this->get[$name]);
    }

    public function hasPost(string $name): bool
    {
        return isset($this->post[$name]);
    }

    // -------------------------
    // Method / scheme / host / port / uri / path
    // -------------------------
    public function getMethod(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        // Header override (common variants)
        $override = $this->server['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $this->server['X_HTTP_METHOD_OVERRIDE'] ?? $this->server['HTTP_X_METHOD_OVERRIDE'] ?? null;
        if ($method === 'POST' && $override) {
            return strtoupper($override);
        }

        // Body override (e.g. _method)
        if ($method === 'POST' && isset($this->post['_method'])) {
            return strtoupper((string)$this->post['_method']);
        }

        return $method;
    }

    /**
	 * Checks whether the request method is POST
	 * @return bool
	 */
	public function isPost(): bool
	{
		return $this->getMethod() === 'POST';
	}

    public function getScheme(): string
    {
        $https = $this->server['HTTPS'] ?? null;
        if ($https && strtolower($https) !== 'off') {
            return 'https';
        }
        if ($this->trustProxyHeaders && !empty($this->server['HTTP_X_FORWARDED_PROTO'])) {
            return explode(',', $this->server['HTTP_X_FORWARDED_PROTO'])[0];
        }
        return 'http';
    }

    /**
	 * Checks whether request has been made using HTTPS
	 * @return bool
	 */
	public function isSecure(): bool
	{
		return $this->getScheme() === 'https';
	}

    public function getHost(): string
    {
        if ($this->trustProxyHeaders && !empty($this->server['HTTP_X_FORWARDED_HOST'])) {
            return explode(',', $this->server['HTTP_X_FORWARDED_HOST'])[0];
        }
        if (!empty($this->server['HTTP_HOST'])) {
            return $this->server['HTTP_HOST'];
        }
        if (!empty($this->server['SERVER_NAME'])) {
            return $this->server['SERVER_NAME'];
        }
        return 'localhost';
    }

    public function getPort(): int
    {
        // Host header may include port
        $host = $this->server['HTTP_HOST'] ?? '';
        if ($host !== '' && str_contains($host, ':')) {
            $parts = explode(':', $host);
            $port = (int) end($parts);
            if ($port > 0) {
                return $port;
            }
        }

        if ($this->trustProxyHeaders && !empty($this->server['HTTP_X_FORWARDED_PORT'])) {
            return (int) explode(',', $this->server['HTTP_X_FORWARDED_PORT'])[0];
        }

        return (int) ($this->server['SERVER_PORT'] ?? ($this->getScheme() === 'https' ? 443 : 80));
    }

    public function getUri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function getPath(): string
    {
        $path = parse_url($this->getUri(), PHP_URL_PATH);
        return $path === false || $path === null ? '/' : $path;
    }

    // -------------------------
    // Client IP
    // -------------------------
    public function getClientIp(bool $trustForwarded = false): string|false
    {
        // 1) Proxy headers allowed?
        if ($trustForwarded && $this->trustProxyHeaders) {

            // RFC 7239: Forwarded: for=...
            if (!empty($this->server['HTTP_FORWARDED'])) {
                $ip = $this->extractFromForwarded($this->server['HTTP_FORWARDED']);
                if ($ip) {
                    return $ip;
                }
            }
            
            // X-Forwarded-For: may contain multiple IPs
            $xff = $this->server['HTTP_X_FORWARDED_FOR'] ?? null;

            if ($xff) {
                // First hop
                $first = trim(explode(',', $xff)[0]);

                // IPv6 with port: [2001:db8::1]:1234
                if (str_starts_with($first, '[')) {
                    $end = strpos($first, ']');
                    if ($end !== false) {
                        return substr($first, 1, $end - 1);
                    }
                }

                // Remove trailing :port if present
                return preg_replace('/:\d+$/', '', $first);
            }

            // Fallback: HTTP_X_REAL_IP
            $ip = $this->server['HTTP_X_REAL_IP'] ?? null;
        }

        // 2) Default: REMOTE_ADDR
        if (!isset($ip)) {
            $ip = $this->server['REMOTE_ADDR'] ?? null;
        }

        return $ip ? trim($ip) : false;
    }

    private function extractFromForwarded(string $header): ?string
    {
        // Split multiple forwarded entries: for=1.2.3.4;proto=https, for=5.6.7.8
        $parts = explode(',', $header);

        // We only care about the first hop
        $first = trim($parts[0]);

        // Extract key-value pairs (for=..., proto=..., host=...)
        // Example: for="[2001:db8::1]:1234";proto=https
        foreach (explode(';', $first) as $pair) {
            $pair = trim($pair);

            if (stripos($pair, 'for=') === 0) {
                $value = trim(substr($pair, 4)); // remove "for="

                // Remove surrounding quotes
                $value = trim($value, "\"'");

                // IPv6 in brackets: [2001:db8::1]:1234
                if (str_starts_with($value, '[')) {
                    $end = strpos($value, ']');
                    if ($end !== false) {
                        return substr($value, 1, $end - 1);
                    }
                }

                // Remove trailing :port if present
                return preg_replace('/:\d+$/', '', $value);
            }
        }

        return null;
    }


    // -------------------------
    // Content negotiation
    // -------------------------
    public function getAcceptableContent(bool $sort = false): array
    {
        return $this->parseQualityHeader('HTTP_ACCEPT', 'accept', $sort);
    }

    public function getBestAccept(): string
    {
        return $this->getAcceptableContent(true)[0]['accept'] ?? '';
    }

    public function getLanguages(bool $sort = false): array
    {
        return $this->parseQualityHeader('HTTP_ACCEPT_LANGUAGE', 'language', $sort);
    }

    public function getBestLanguage(): string
    {
        return $this->getLanguages(true)[0]['language'] ?? '';
    }

    private function parseQualityHeader(string $serverKey, string $name, bool $sort): array
    {
        $result = [];
        $header = $this->server[$serverKey] ?? '';
        if ($header === '') {
            return $result;
        }

        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $segments = explode(';', $part);
            $value = array_shift($segments);
            $quality = 1.0;
            $params = [];
            foreach ($segments as $seg) {
                $seg = trim($seg);
                if (str_starts_with($seg, 'q=')) {
                    $quality = (float) substr($seg, 2);
                } elseif (str_contains($seg, '=')) {
                    [$k, $v] = explode('=', $seg, 2);
                    $params[trim($k)] = trim($v);
                }
            }
            $entry = [...['quality' => $quality, $name => $value], ...$params];
            $result[] = $entry;
        }

        if ($sort) {
            usort($result, fn($a, $b) => $b['quality'] <=> $a['quality']);
        }

        return $result;
    }

    // -------------------------
    // JSON / AJAX helpers
    // -------------------------
    public function isJson(): bool
    {
        $ct = $this->server['CONTENT_TYPE'] ?? '';
        if ($ct !== '' && str_contains($ct, 'application/json')) {
            return true;
        }
        $accept = $this->server['HTTP_ACCEPT'] ?? '';
        if ($accept !== '' && str_contains($accept, 'application/json')) {
            return true;
        }
        return false;
    }

    /** @deprecated Use isJson() */
    public function isAjax(): bool
    {
        if ($this->isJson()) {
            return true;
        }
        return ($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    public function getBasicAuth(): ?array
    {
        // Default: PHP_AUTH_USER / PHP_AUTH_PW (Apache, built-in server)
        if (isset($this->server['PHP_AUTH_USER'], $this->server['PHP_AUTH_PW'])) {
            return [
                'username' => $this->server['PHP_AUTH_USER'],
                'password' => $this->server['PHP_AUTH_PW'],
            ];
        }

        // Fallback: Authorization Header (often Nginx + PHP-FPM)
        $auth = $this->server['HTTP_AUTHORIZATION']
            ?? $this->server['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if ($auth && str_starts_with(strtolower($auth), 'basic ')) {
            $decoded = base64_decode(substr($auth, 6), true);

            if ($decoded && str_contains($decoded, ':')) {
                [$user, $pass] = explode(':', $decoded, 2);
                return [
                    'username' => $user,
                    'password' => $pass,
                ];
            }
        }

        return null;
    }

    public function getBearerToken(): ?string
    {
        // 1) Standard: Authorization header
        $auth = $this->server['HTTP_AUTHORIZATION']
            ?? $this->server['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if (!$auth) {
            return null;
        }

        // Normalize
        $auth = trim($auth);

        // 2) Must start with "Bearer "
        if (stripos($auth, 'Bearer ') !== 0) {
            return null;
        }

        // 3) Extract token
        $token = trim(substr($auth, 7));

        return $token !== '' ? $token : null;
    }


    // -------------------------
    // Files handling
    // -------------------------
    private ?array $normalizedFiles = null;

    private function normalizeFiles(): array
    {
        if ($this->normalizedFiles !== null) {
            return $this->normalizedFiles;
        }

        $normalized = [];
        foreach ($this->files as $field => $data) {
            if (!isset($data['name'])) {
                continue;
            }
            if (is_array($data['name'])) {
                $items = [];
                $count = count($data['name']);
                for ($i = 0; $i < $count; $i++) {
                    $items[] = new UploadedFile(
                        $data['name'][$i] ?? '',
                        $data['type'][$i] ?? '',
                        $data['tmp_name'][$i] ?? '',
                        $data['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                        $data['size'][$i] ?? 0
                    );
                }
                $normalized[$field] = $items;
            } else {
                $normalized[$field] = new UploadedFile(
                    $data['name'],
                    $data['type'] ?? '',
                    $data['tmp_name'] ?? '',
                    $data['error'] ?? UPLOAD_ERR_NO_FILE,
                    $data['size'] ?? 0
                );
            }
        }

        $this->normalizedFiles = $normalized;
        return $normalized;
    }

    public function getFile(string $key): ?UploadedFile
    {
        $files = $this->normalizeFiles();
        $value = $files[$key] ?? null;
        if ($value instanceof UploadedFile) {
            return $value;
        }
        if (is_array($value) && count($value) > 0) {
            return $value[0];
        }
        return null;
    }

    /**
     * @return UploadedFile[]
     */
    public function getFiles(string $key): array
    {
        $files = $this->normalizeFiles();
        $value = $files[$key] ?? null;
        if ($value === null) {
            return [];
        }
        return is_array($value) ? $value : [$value];
    }

    public function getUserAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function getContentType(): string
    {
        return $this->server['CONTENT_TYPE'] ?? '';
    }
}