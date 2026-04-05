<?php
namespace Merlin\Http;

/**
 * Represents an HTTP response.
 *
 * Build a response by chaining setters and finish by calling {@see send()},
 * or use one of the static factory methods ({@see json()}, {@see html()},
 * {@see redirect()}, etc.) for common cases.
 */
class Response
{
    /**
     * Create a new Response.
     *
     * @param int    $status  HTTP status code.
     * @param array  $headers Associative array of response headers.
     * @param string $body    Response body.
     */
    public function __construct(
        protected int $status = 200,
        protected array $headers = [],
        protected string $body = ''
    ) {
    }

    /**
     * Set the HTTP status code.
     *
     * @param int $code HTTP status code (e.g. 200, 404).
     * @return $this
     */
    public function setStatus(int $code): static
    {
        $this->status = $code;
        return $this;
    }

    /**
     * Set a response header.
     *
     * @param string $key   Header name (e.g. "Content-Type").
     * @param string $value Header value.
     * @return $this
     */
    public function setHeader(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Set multiple response headers.
     *
     * @param array $headers Associative array of headers (e.g. ["Content-Type" => "application/json"]).
     * @return $this
     */
    public function setHeaders(array $headers): static
    {
        $this->headers = [...$this->headers, ...$headers];
        return $this;
    }

    /**
     * Append text to the response body.
     *
     * @param string $text Content to append.
     * @return $this
     */
    public function write(string $text): static
    {
        $this->body .= $text;
        return $this;
    }

    /**
     * Send the response: emit the status code, headers, and body.
     */
    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $k => $v) {
            header("$k: $v");
        }

        echo $this->body;
    }

    /**
     * Create a JSON response.
     *
     * @param mixed $data   Data to JSON-encode.
     * @param int   $status HTTP status code (default 200).
     * @return static
     */
    public static function json(mixed $data, int $status = 200): static
    {
        return new static(
            status: $status,
            headers: ['Content-Type' => 'application/json'],
            body: json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Create a plain-text response.
     *
     * @param string $text   Response body.
     * @param int    $status HTTP status code (default 200).
     * @return static
     */
    public static function text(string $text, int $status = 200): static
    {
        return new static(
            status: $status,
            headers: ['Content-Type' => 'text/plain; charset=utf-8'],
            body: $text
        );
    }

    /**
     * Create an HTML response.
     *
     * @param string $html   HTML content.
     * @param int    $status HTTP status code (default 200).
     * @return static
     */
    public static function html(string $html, int $status = 200): static
    {
        return new static(
            status: $status,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $html
        );
    }

    /**
     * Create a redirect response.
     *
     * @param string $url    URL to redirect to.
     * @param int    $status HTTP redirect status code (default 302).
     * @return static
     */
    public static function redirect(string $url, int $status = 302): static
    {
        return new static(
            status: $status,
            headers: ['Location' => $url],
            body: ''
        );
    }

    /**
     * Create a response with only a status code and an empty body.
     *
     * @param int $status HTTP status code.
     * @return static
     */
    public static function status(int $status): static
    {
        return new static(status: $status);
    }
}
