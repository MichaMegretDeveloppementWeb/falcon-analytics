<?php

declare(strict_types=1);

namespace Falcon\Analytics\Services;

use Falcon\Analytics\DTOs\PageContext;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Support\Str;
use JsonException;

/**
 * Seals the context a page hands its collector, and opens it again when a
 * batch brings it back.
 *
 * Encrypted with the application key, so nobody can read whom it names nor
 * alter it · a plain string goes in, never a serialised object, so opening
 * what a request carries runs no unserialize.
 *
 * @internal
 */
final readonly class PageContextSealer
{
    /** Longer than any context this class writes: past it, nothing is decrypted. */
    public const MAX_LENGTH = 1024;

    private const VERSION = 1;

    /** Keeps a context from passing for another token the host encrypts with the same key. */
    private const PURPOSE = 'fa-ctx';

    public function __construct(private StringEncrypter $encrypter) {}

    public function seal(PageContext $context): string
    {
        return $this->encrypter->encryptString(json_encode([
            'v' => self::VERSION,
            'p' => self::PURPOSE,
            't' => $context->subjectType,
            'i' => $context->subjectId,
            'k' => $context->browserKey,
        ], JSON_THROW_ON_ERROR));
    }

    /** The context a batch carries, or null when it is not one this class sealed. */
    public function open(string $sealed): ?PageContext
    {
        if (strlen($sealed) > self::MAX_LENGTH) {
            return null;
        }

        try {
            $payload = json_decode($this->encrypter->decryptString($sealed), true, 4, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return null;
        }

        return is_array($payload) ? $this->contextOf($payload) : null;
    }

    /** @param  array<mixed>  $payload */
    private function contextOf(array $payload): ?PageContext
    {
        $type = $payload['t'] ?? null;
        $id = $payload['i'] ?? null;
        $key = $payload['k'] ?? null;

        if (($payload['v'] ?? null) !== self::VERSION || ($payload['p'] ?? null) !== self::PURPOSE) {
            return null;
        }

        if (! is_string($type) || $type === '' || strlen($type) > 32 || ! is_int($id) || $id < 0) {
            return null;
        }

        if (! is_string($key) || ! Str::isUuid($key)) {
            return null;
        }

        return new PageContext($type, $id, $key);
    }
}
