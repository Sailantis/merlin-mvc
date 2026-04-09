<?php /** @noinspection PhpUnused */

namespace Merlin\Http;

/**
 * HTTP Request class
 */
class Request
{
	/**
	 * Get the raw request body
	 * Caches the body since php://input can only be read once
	 * @return string
	 */
	public function getRequestBody(): bool|string
	{
		static $body = null;
		if ($body === null) {
			$body = file_get_contents('php://input');
		}
		return $body;
	}

	/**
	 * Get and parse JSON request body
	 * @param bool $assoc When true, returns associative arrays. When false, returns objects
	 * @return mixed Returns the parsed JSON data, or null on error
	 * @throws \RuntimeException if the JSON body cannot be parsed
	 */
	public function getJsonBody($assoc = true): mixed
	{
		$body = $this->getRequestBody();
		$jsonBody = json_decode($body, $assoc);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new \RuntimeException('Failed to parse JSON body: ' . json_last_error_msg());
		}
		return $jsonBody;
	}

	/**
	 * Get a parameter from the request (GET, POST, COOKIE, etc.)
	 * @param ?string $name
	 * @param mixed $defaultValue
	 * @return mixed
	 */
	public function get(?string $name = null, mixed $defaultValue = null): mixed
	{
		return isset($name) ? (isset($_REQUEST[$name]) ? $_REQUEST[$name] : $defaultValue) : $_REQUEST;
	}

	/**
	 * Get a POST parameter from the request
	 * @param ?string $name
	 * @param mixed $defaultValue
	 * @return mixed
	 */
	public function getPost(?string $name = null, mixed $defaultValue = null): mixed
	{
		return isset($name) ? (isset($_POST[$name]) ? $_POST[$name] : $defaultValue) : $_POST;
	}

	/**
	 * Get a query parameter from the request
	 * @param ?string $name
	 * @param mixed $defaultValue
	 * @return mixed
	 */
	public function getQuery(?string $name = null, mixed $defaultValue = null): mixed
	{
		return isset($name) ? (isset($_GET[$name]) ? $_GET[$name] : $defaultValue) : $_GET;
	}

	/**
	 * Get a server variable from the request
	 * @param ?string $name
	 * @param mixed $defaultValue
	 * @return mixed
	 */
	public function getServer(?string $name = null, mixed $defaultValue = null): mixed
	{
		return isset($name) ? (isset($_SERVER[$name]) ? $_SERVER[$name] : $defaultValue) : $_SERVER;
	}

	/**
	 * Get the HTTP method of the request, accounting for method overrides in POST requests
	 * @return string
	 */
	public function getMethod(): string
	{
		$requestMethod = 'GET';
		if (isset($_SERVER['REQUEST_METHOD'])) {
			$requestMethod = $_SERVER['REQUEST_METHOD'];
		}

		if ($requestMethod === 'POST') {
			if (isset($_SERVER['X_HTTP_METHOD_OVERRIDE'])) {
				$requestMethod = strtoupper($_SERVER['X_HTTP_METHOD_OVERRIDE']);
			}
		}

		return $requestMethod;
	}

	/**
	 * Get the request scheme (http or https)
	 * @return string
	 */
	public function getScheme(): string
	{
		return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
	}

	/**
	 * Get the server name from the request
	 * @return string
	 */
	public function getServerName(): string
	{
		return isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
	}

	/**
	 * Get the server IP address
	 * @return string
	 */
	public function getServerAddr(): string
	{
		return isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : gethostbyname('localhost');
	}

	/**
	 * Get the host from the request, accounting for Host header and server variables
	 * @return string
	 */
	public function getHttpHost(): string
	{
		if (!empty($_SERVER['HTTP_HOST'])) {
			return $_SERVER['HTTP_HOST'];
		}
		if (!empty($_SERVER['SERVER_NAME'])) {
			return $_SERVER['SERVER_NAME'];
		}
		if (!empty($_SERVER['SERVER_ADDR'])) {
			return $_SERVER['SERVER_ADDR'];
		}
		return '';
	}

	/**
	 * Get the port number from the request, accounting for standard ports and Host header
	 * @return int
	 */
	public function getPort(): int
	{
		if (!empty($_SERVER['HTTP_HOST'])) {
			$host = $_SERVER['HTTP_HOST'];
			$index = strrpos($host, ':');
			if ($index !== false) {
				return (int) substr($host, $index + 1);
			}
			return $this->isSecure() ? 443 : 80;
		}
		return (int) ($_SERVER['SERVER_PORT'] ?? 0);
	}

	/**
	 * Get the Content-Type header from the request
	 * @return string
	 */
	public function getContentType(): string
	{
		if (isset($_SERVER['CONTENT_TYPE'])) {
			return $_SERVER['CONTENT_TYPE'];
		}
		return '';
	}

	/**
	 * Get the client's IP address, optionally trusting proxy headers
	 * @param bool $trustForwardedHeader
	 * @return string|bool
	 */
	public function getClientAddress(bool $trustForwardedHeader = false): string|bool
	{
		// return IP given by proxy?

		if ($trustForwardedHeader) {
			if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$address = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
				$address = $_SERVER['HTTP_CLIENT_IP'];
			}
		}

		if (empty($address)) {
			$address = $_SERVER["REMOTE_ADDR"];
		}

		if (!isset($address)) {
			return false;
		}

		if (strpos($address, ",") !== false) {
			// client address has multiples parts, return only first part
			return explode(",", $address)[0];
		}

		return $address;
	}

	/**
	 * Get the request URI
	 * @return string
	 */
	public function getUri(): string
	{
		return $_SERVER['REQUEST_URI'] ?? '/';
	}

	/**
	 * Get the request path (URI without query string)
	 * @return string
	 */
	public function getPath(): string
	{
		return parse_url($this->getUri(), PHP_URL_PATH) ?: '/';
	}


	/**
	 * Get the User-Agent header from the request
	 * @return string
	 */
	public function getUserAgent(): string
	{
		return $_SERVER['HTTP_USER_AGENT'] ?? '';
	}

	protected final function _getQualityHeader(string $serverIndex, string $name, bool $sort): array
	{
		// Accept: text/html, application/xhtml+xml, application/xml;q=0.9, image/webp, */*;q=0.8
		// Accept-Language: fr-CH, fr;q=0.9, en;q=0.8, de;q=0.7, *;q=0.5
		$returnedParts = [];
		$lastQuality = 1;
		$needSort = false;
		if (isset($_SERVER[$serverIndex])) {
			foreach (explode(',', $_SERVER[$serverIndex]) as $item) {
				$headerParts = [];
				foreach (explode(';', $item) as $part) {
					$part = trim($part);
					$index = strpos($part, '=');
					if ($index === false) {
						$headerParts[$name] = $part;
						$headerParts['quality'] = 1.0;
					} elseif ($index === 1 && $part[0] === 'q') {
						$quality = (float) substr($part, 2);
						$headerParts['quality'] = $quality;
						$needSort = $quality > $lastQuality;
						$lastQuality = $quality;
					} else {
						$headerParts[substr($part, 0, $index)] = substr($part, $index + 1);
					}
				}
				$returnedParts[] = $headerParts;
			}
		}
		if ($sort && $needSort) {
			usort(
				$returnedParts,
				fn($a, $b) => (int) ($b['quality'] * 100) <=> (int) ($a['quality'] * 100)
			);
		}
		return $returnedParts;
	}

	/**
	 * Gets an array with mime/types and their quality accepted by the browser/client from _SERVER["HTTP_ACCEPT"]
	 * @return array
	 */
	public function getAcceptableContent(bool $sort = false): array
	{
		return $this->_getQualityHeader('HTTP_ACCEPT', 'accept', $sort);
	}

	/**
	 * Gets best mime/type accepted by the browser/client from _SERVER["HTTP_ACCEPT"]
	 * @return string
	 */
	public function getBestAccept(): string
	{
		return $this->getAcceptableContent(true)[0]['accept'] ?? '';
	}

	/**
	 * Gets a charsets array and their quality accepted by the browser/client from _SERVER["HTTP_ACCEPT_CHARSET"]
	 * @return array
	 */
	public function getClientCharsets(bool $sort = false): array
	{
		return $this->_getQualityHeader("HTTP_ACCEPT_CHARSET", 'charset', $sort);
	}

	/**
	 * Gets best charset accepted by the browser/client from _SERVER["HTTP_ACCEPT_CHARSET"]
	 * @return string
	 */
	public function getBestCharset(): string
	{
		return $this->getClientCharsets(true)[0]['charset'] ?? '';
	}

	/**
	 * Gets languages array and their quality accepted by the browser/client from _SERVER["HTTP_ACCEPT_LANGUAGE"]
	 */
	public function getLanguages(bool $sort = false): array
	{
		return $this->_getQualityHeader("HTTP_ACCEPT_LANGUAGE", 'language', $sort);
	}

	/**
	 * Gets best language accepted by the browser/client from _SERVER["HTTP_ACCEPT_LANGUAGE"]
	 */
	public function getBestLanguage(): string
	{
		return $this->getLanguages(true)[0]['language'] ?? '';
	}

	/**
	 * Gets auth info accepted by the browser/client from $_SERVER['PHP_AUTH_USER']
	 * @return array|null
	 */
	public function getBasicAuth(): array|null
	{
		if (isset($_SERVER["PHP_AUTH_USER"]) && isset($_SERVER["PHP_AUTH_PW"])) {
			return [
				'username' => $_SERVER["PHP_AUTH_USER"],
				'password' => $_SERVER["PHP_AUTH_PW"],
			];
		}
		return null;
	}

	/**
	 * Gets auth info accepted by the browser/client from $_SERVER['PHP_AUTH_DIGEST']
	 * @return array|null
	 */
	public function getDigestAuth(): array|null
	{
		if (isset($_SERVER["PHP_AUTH_DIGEST"])) {
			if (preg_match_all("#(\\w+)=(['\"]?)([^'\" ,]+)\\2#", $_SERVER["PHP_AUTH_DIGEST"], $matches, 2)) {
				$auth = [];
				foreach ($matches as $match) {
					$auth[$match[1]] = $match[3];
				}
				return $auth;
			}
		}
		return null;
	}

	/**
	 * Checks whether request has been made using AJAX
	 * @return bool
	 */
	public function isAjax(): bool
	{
		// JSON-Requests (fetch, axios, modern Clients)
		if (
			isset($_SERVER['CONTENT_TYPE']) &&
			str_contains($_SERVER['CONTENT_TYPE'], 'application/json')
		) {
			return true;
		}

		// JSON-Requests (fetch, axios, modern Clients)
		if (
			isset($_SERVER['HTTP_ACCEPT']) &&
			str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')
		) {
			return true;
		}

		// Classic jQuery-Header (if set)
		if (
			isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
			$_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest'
		) {
			return true;
		}

		return false;
	}

	/**
	 * Checks whether request has been made using HTTPS
	 * @return bool
	 */
	public function isSecure(): bool
	{
		return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
	}

	/**
	 * Checks whether the request method is POST
	 * @return bool
	 */
	public function isPost(): bool
	{
		return $this->getMethod() === 'POST';
	}

	/**
	 * Checks whether a parameter is present in the combined request data ($_REQUEST)
	 * @param string $name Parameter name
	 * @return bool
	 */
	public function has($name): bool
	{
		return isset($_REQUEST[$name]);
	}

	/**
	 * Checks whether a parameter is present in the POST data ($_POST)
	 * @param string $name Parameter name
	 * @return bool
	 */
	public function hasPost($name): bool
	{
		return isset($_POST[$name]);
	}

	/**
	 * Checks whether a parameter is present in the query string ($_GET)
	 * @param string $name Parameter name
	 * @return bool
	 */
	public function hasQuery($name): bool
	{
		return isset($_GET[$name]);
	}

	/**
	 * Checks whether a server variable is present in $_SERVER
	 * @param string $name Server variable name
	 * @return bool
	 */
	public function hasServer($name): bool
	{
		return isset($_SERVER[$name]);
	}

	// File uploads

	protected function getNormalizedFiles(): array
	{
		static $normalized = null;

		if ($normalized === null) {
			foreach ($_FILES as $field => $data) {
				if (\is_array($data['name'])) {
					$files = [];
					foreach ($data['name'] as $i => $name) {
						$files[] = new UploadedFile(
							$name,
							$data['type'][$i],
							$data['tmp_name'][$i],
							$data['error'][$i],
							$data['size'][$i]
						);
					}
					$normalized[$field] = $files;
				} else {
					$normalized[$field] = new UploadedFile(
						$data['name'],
						$data['type'],
						$data['tmp_name'],
						$data['error'],
						$data['size']
					);
				}
			}
		}

		return $normalized;
	}

	/**
	 * Get an uploaded file for a given key. Returns an UploadedFile object or null if no file was uploaded for the key.
	 * @param string $key
	 * @return UploadedFile|null
	 */
	public function getFile(string $key): ?UploadedFile
	{
		$files = $this->getNormalizedFiles();
		$value = $files[$key] ?? null;

		if ($value instanceof UploadedFile) {
			return $value;
		}

		return $value[0] ?? null;
	}


	/**
	 * Get uploaded files for a given key. Returns an array of UploadedFile objects, even if only one file was uploaded.
	 * @param string $key
	 * @return UploadedFile[]
	 */
	public function getFiles(string $key): array
	{
		$files = $this->getNormalizedFiles();
		$value = $files[$key] ?? null;

		return \is_array($value) ? $value : ($value ? [$value] : []);
	}

}
