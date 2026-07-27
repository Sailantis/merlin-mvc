<?php

namespace Azera\Http;

use Azera\Exception;

/**
 * Represents a single HTTP cookie with optional transparent encryption.
 *
 * Use the static {@see make()} factory or construct directly, then call
 * {@see send()} to emit the Set-Cookie header. Read the cookie value with
 * {@see value()}, which handles decryption automatically.
 */
class Cookie
{
	protected string $name;
	protected mixed $value = null;
	protected bool $loaded = false;

	protected int $expires = 0;
	protected string $path = '/';
	protected string $domain = '';
	protected bool $secure = false;
	protected bool $httpOnly = true;

	protected bool $encrypted = false;
	protected string $cipher = self::CIPHER_AUTO;
	protected ?string $key = null;

	// --- Factory Methods -----------------------------------------------------

	/**
	 * Create a new Cookie instance with the given parameters.
	 *
	 * @param string $name The name of the cookie.
	 * @param mixed $value The value of the cookie (optional).
	 * @param int $expires Expiration timestamp (optional).
	 * @param string $path Path for which the cookie is valid (optional).
	 * @param string $domain Domain for which the cookie is valid (optional).
	 * @param bool $secure Whether the cookie should only be sent over HTTPS (optional).
	 * @param bool $httpOnly Whether the cookie should be inaccessible to JavaScript (optional).
	 * @return static A new Cookie instance.
	 */
	public static function make(
		string $name,
		mixed $value = null,
		int $expires = 0,
		string $path = '/',
		string $domain = '',
		bool $secure = false,
		bool $httpOnly = true
	): static {
		return new static($name, $value, $expires, $path, $domain, $secure, $httpOnly);
	}

	// --- Constructor ---------------------------------------------------------

	/**
	 * Create a new Cookie instance.
	 *
	 * @param string $name     Cookie name.
	 * @param mixed  $value    Initial value (null means "not yet loaded").
	 * @param int    $expires  Expiration timestamp (0 = session cookie).
	 * @param string $path     URL path scope.
	 * @param string $domain   Domain scope.
	 * @param bool   $secure   Send over HTTPS only.
	 * @param bool   $httpOnly Inaccessible to JavaScript.
	 */
	public function __construct(
		string $name,
		mixed $value = null,
		int $expires = 0,
		string $path = '/',
		string $domain = '',
		bool $secure = false,
		bool $httpOnly = true
	) {
		$this->name = $name;

		if ($value !== null) {
			$this->value = $value;
			$this->loaded = true;
		}

		$this->expires = $expires;
		$this->path = $path;
		$this->domain = $domain;
		$this->secure = $secure;
		$this->httpOnly = $httpOnly;
	}

	// --- Value Handling ------------------------------------------------------

	/**
	 * Read the cookie value, lazily loading it from $_COOKIE and decrypting if needed.
	 *
	 * @param mixed $default Value to return when the cookie is not present.
	 * @return mixed
	 */
	public function value(mixed $default = null): mixed
	{
		if ($this->loaded) {
			return $this->value;
		}

		if (!isset($_COOKIE[$this->name])) {
			return $default;
		}

		$raw = $_COOKIE[$this->name];

		if ($this->encrypted) {
			$raw = $this->decrypt($raw);
		}

		$this->value = $raw;
		$this->loaded = true;

		return $this->value;
	}

	/**
	 * Set the cookie value (in memory; call {@see send()} to persist).
	 *
	 * @param mixed $value New value.
	 * @return $this
	 */
	public function set(mixed $value): static
	{
		$this->value = $value;
		$this->loaded = true;
		return $this;
	}

	// --- Sending -------------------------------------------------------------

	/**
	 * Emit a Set-Cookie header with the current cookie configuration.
	 *
	 * Encrypts the value first if encryption is enabled.
	 *
	 * @return $this
	 */
	public function send(): static
	{
		$value = $this->value;

		if ($this->encrypted && $value !== null) {
			$value = $this->encrypt($value);
		}

		setcookie(
			$this->name,
			$value,
			$this->expires,
			$this->path,
			$this->domain,
			$this->secure,
			$this->httpOnly
		);

		return $this;
	}

	/**
	 * Delete the cookie by setting its expiration to the past.
	 */
	public function delete(): void
	{
		setcookie(
			$this->name,
			'',
			time() - 3600,
			$this->path,
			$this->domain,
			$this->secure,
			$this->httpOnly
		);
	}

	// --- Encryption ----------------------------------------------------------

	/**
	 * Enable or disable transparent encryption for this cookie.
	 *
	 * @param bool $state True to enable encryption (default), false to disable.
	 * @return $this
	 */
	public function encrypted(bool $state = true): static
	{
		$this->encrypted = $state;
		return $this;
	}

	/**
	 * Set the encryption cipher to use (one of the {@see \Azera\Crypt}::CIPHER_* constants).
	 *
	 * @param string $cipher Cipher identifier.
	 * @return $this
	 */
	public function cipher(string $cipher): static
	{
		$this->cipher = $cipher;
		return $this;
	}

	/**
	 * Set the encryption key. Defaults to a key derived from PHP's uname when null.
	 *
	 * @param string|null $key Encryption key or null to use the default key.
	 * @return $this
	 */
	public function key(?string $key): static
	{
		$this->key = $key;
		return $this;
	}

	protected function encrypt(string $value): string
	{
		return self::encryptCookie($value, $this->resolveKey(), $this->cipher);
	}

	protected function decrypt(string $value): mixed
	{
		return self::decryptCookie($value, $this->resolveKey(), $this->cipher);
	}

	protected function resolveKey(): string
	{
		if ($this->key !== null) {
			return $this->key;
		}

		return hash('sha256', php_uname(), true);
	}

	// --- Metadata ------------------------------------------------------------

	/**
	 * Get the cookie name.
	 *
	 * @return string Cookie name.
	 */
	public function name(): string
	{
		return $this->name;
	}

	/**
	 * Set the expiration timestamp.
	 *
	 * @param int $timestamp Unix timestamp (0 = session cookie).
	 * @return $this
	 */
	public function expires(int $timestamp): static
	{
		$this->expires = $timestamp;
		return $this;
	}

	/**
	 * Set the URL path scope for the cookie.
	 *
	 * @param string $path URL path (e.g. "/").
	 * @return $this
	 */
	public function path(string $path): static
	{
		$this->path = $path;
		return $this;
	}

	/**
	 * Set the domain scope for the cookie.
	 *
	 * @param string $domain Domain (e.g. ".example.com").
	 * @return $this
	 */
	public function domain(string $domain): static
	{
		$this->domain = $domain;
		return $this;
	}

	/**
	 * Restrict the cookie to HTTPS connections only.
	 *
	 * @param bool $state True to require HTTPS.
	 * @return $this
	 */
	public function secure(bool $state): static
	{
		$this->secure = $state;
		return $this;
	}

	/**
	 * Make the cookie inaccessible to JavaScript (HttpOnly flag).
	 *
	 * @param bool $state True to set the HttpOnly flag.
	 * @return $this
	 */
	public function httpOnly(bool $state): static
	{
		$this->httpOnly = $state;
		return $this;
	}

	/**
	 * Return the cookie value as a string (useful for string-casting).
	 *
	 * @return string Cookie value, or empty string when not set.
	 */
	public function __toString(): string
	{
		return (string) $this->value();
	}

	// region Encyption/Decryption Helpers

	    /**
     * Supported ciphers
     */
    public const CIPHER_CHACHA20_POLY1305 = 'chacha20-poly1305';
    public const CIPHER_AES_256_GCM = 'aes-256-gcm';
    public const CIPHER_AUTO = 'auto';

    /**
     * Encrypt a value using the specified cipher
     *
     * @param string $value The value to encrypt
     * @param string $key The encryption key (at least 32 bytes recommended)
     * @param string $cipher The cipher to use: 'chacha20-poly1305', 'aes-256-gcm', or 'auto'
     * @return string Base64-encoded encrypted value
     * @throws Exception
     */
    protected static function encryptCookie($value, $key, $cipher = self::CIPHER_AUTO)
    {
        if ($cipher === self::CIPHER_AUTO) {
            $cipher = self::getAvailableCipher();
        }

        if ($cipher === self::CIPHER_CHACHA20_POLY1305) {
            return self::encryptSodium($value, $key);
        } elseif ($cipher === self::CIPHER_AES_256_GCM) {
            return self::encryptOpenSSL($value, $key);
        } else {
            throw new Exception("Unsupported cipher: {$cipher}");
        }
    }

    /**
     * Decrypt a value using the specified cipher
     *
     * @param string $value The base64-encoded encrypted value
     * @param string $key The encryption key
     * @param string $cipher The cipher to use: 'chacha20-poly1305', 'aes-256-gcm', or 'auto'
     * @return string|null The decrypted value or null on failure
     * @throws Exception
     */
    protected static function decryptCookie($value, $key, $cipher = self::CIPHER_AUTO)
    {
        if ($cipher === self::CIPHER_AUTO) {
            $cipher = self::getAvailableCipher();
        }

        if ($cipher === self::CIPHER_CHACHA20_POLY1305) {
            return self::decryptSodium($value, $key);
        } elseif ($cipher === self::CIPHER_AES_256_GCM) {
            return self::decryptOpenSSL($value, $key);
        } else {
            throw new Exception("Unsupported cipher: {$cipher}");
        }
    }

	/**
	 * Check if Sodium is available
	 *
	 * @return bool
	 */
	protected static function hasSodium()
	{
		return function_exists('sodium_crypto_aead_chacha20poly1305_ietf_encrypt');
	}

	/**
	 * Check if OpenSSL is available
	 *
	 * @return bool
	 */
	protected static function hasOpenSSL()
	{
		return function_exists('openssl_encrypt');
	}


    /**
     * Get the best available cipher (prefers Sodium over OpenSSL)
     *
     * @return string
     * @throws Exception
     */
    public static function getAvailableCipher()
    {
        if (self::hasSodium()) {
            return self::CIPHER_CHACHA20_POLY1305;
        } elseif (self::hasOpenSSL()) {
            return self::CIPHER_AES_256_GCM;
        } else {
            throw new Exception("No encryption library available (Sodium or OpenSSL required)");
        }
    }

    /**
     * Encrypt using Sodium (ChaCha20-Poly1305)
     *
     * @param string $value
     * @param string $key
     * @return string Base64-encoded: nonce + ciphertext
     * @throws Exception
     */
    protected static function encryptSodium($value, $key)
    {
        if (!self::hasSodium()) {
            throw new Exception("Sodium extension not available");
        }

        // Derive a proper key from the input key (Sodium requires 32 bytes)
        $derivedKey = hash('sha256', $key, true);

        // Generate a random nonce (12 bytes for ChaCha20-Poly1305-IETF)
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_CHACHA20POLY1305_IETF_NPUBBYTES);

        // Encrypt with authenticated encryption
        $ciphertext = sodium_crypto_aead_chacha20poly1305_ietf_encrypt(
            $value,
            '',  // No additional data
            $nonce,
            $derivedKey
        );

        // Return base64-encoded: nonce + ciphertext
        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypt using Sodium (ChaCha20-Poly1305)
     *
     * @param string $value Base64-encoded encrypted value
     * @param string $key
     * @return string|null
     * @throws Exception
     */
    protected static function decryptSodium($value, $key)
    {
        if (!self::hasSodium()) {
            throw new Exception("Sodium extension not available");
        }

        // Derive the same key
        $derivedKey = hash('sha256', $key, true);

        // Decode from base64
        $encrypted = base64_decode($value);
        if ($encrypted === false) {
            return null;
        }

        $nonceSize = SODIUM_CRYPTO_AEAD_CHACHA20POLY1305_IETF_NPUBBYTES;

        if (strlen($encrypted) <= $nonceSize) {
            return null;
        }

        // Extract nonce and ciphertext
        $nonce = substr($encrypted, 0, $nonceSize);
        $ciphertext = substr($encrypted, $nonceSize);

        // Decrypt
        $decrypted = sodium_crypto_aead_chacha20poly1305_ietf_decrypt(
            $ciphertext,
            '',  // No additional data
            $nonce,
            $derivedKey
        );

        return $decrypted !== false ? $decrypted : null;
    }

    /**
     * Encrypt using OpenSSL (AES-256-GCM)
     *
     * @param string $value
     * @param string $key
     * @return string Base64-encoded: iv + tag + ciphertext
     * @throws Exception
     */
    protected static function encryptOpenSSL($value, $key)
    {
        if (!self::hasOpenSSL()) {
            throw new Exception("OpenSSL extension not available");
        }

        $method = 'aes-256-gcm';

        // Derive a proper key from the input key
        $derivedKey = hash('sha256', $key, true);

        // Generate IV
        $ivSize = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivSize);

        // Encrypt with GCM mode (authenticated encryption)
        $tag = '';
        $ciphertext = openssl_encrypt(
            $value,
            $method,
            $derivedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',  // No additional data
            16   // Tag length
        );

        if ($ciphertext === false) {
            throw new Exception("OpenSSL encryption failed");
        }

        // Return base64-encoded: iv + tag + ciphertext
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt using OpenSSL (AES-256-GCM)
     *
     * @param string $value Base64-encoded encrypted value
     * @param string $key
     * @return string|null
     * @throws Exception
     */
    protected static function decryptOpenSSL($value, $key)
    {
        if (!self::hasOpenSSL()) {
            throw new Exception("OpenSSL extension not available");
        }

        $method = 'aes-256-gcm';

        // Derive the same key
        $derivedKey = hash('sha256', $key, true);

        // Decode from base64
        $encrypted = base64_decode($value);
        if ($encrypted === false) {
            return null;
        }

        $ivSize = openssl_cipher_iv_length($method);
        $tagSize = 16;

        if (strlen($encrypted) <= $ivSize + $tagSize) {
            return null;
        }

        // Extract IV, tag, and ciphertext
        $iv = substr($encrypted, 0, $ivSize);
        $tag = substr($encrypted, $ivSize, $tagSize);
        $ciphertext = substr($encrypted, $ivSize + $tagSize);

        // Decrypt
        $decrypted = openssl_decrypt(
            $ciphertext,
            $method,
            $derivedKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $decrypted !== false ? $decrypted : null;
    }

	// endregion
}
