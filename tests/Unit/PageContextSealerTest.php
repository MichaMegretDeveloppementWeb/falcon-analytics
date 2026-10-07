<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Unit;

use Falcon\Analytics\DTOs\PageContext;
use Falcon\Analytics\Services\PageContextSealer;
use Illuminate\Contracts\Encryption\StringEncrypter;
use Illuminate\Encryption\Encrypter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A page's context comes back as it left, and only a context this class
 * sealed with this key comes back at all.
 */
final class PageContextSealerTest extends TestCase
{
    private const string KEY_IN_USE = '0123456789abcdef0123456789abcdef';

    private const string BROWSER = '7a3c2f6e-1b4d-4e8a-9c1f-2d5e6a7b8c9d';

    public function test_a_context_comes_back_as_it_left(): void
    {
        $sealer = self::sealerWith(self::KEY_IN_USE);
        $opened = $sealer->open($sealer->seal(new PageContext('client', 4, self::BROWSER)));

        $this->assertEquals(new PageContext('client', 4, self::BROWSER), $opened);
    }

    public function test_whom_it_names_cannot_be_read_in_it(): void
    {
        $sealed = self::sealerWith(self::KEY_IN_USE)->seal(new PageContext('client', 4, self::BROWSER));

        $this->assertStringNotContainsString(self::BROWSER, (string) base64_decode($sealed, true));
        $this->assertStringNotContainsString('client', (string) base64_decode($sealed, true));
    }

    /** A key rotated with APP_PREVIOUS_KEYS still opens the pages drawn before. */
    public function test_the_previous_key_still_opens_what_it_sealed(): void
    {
        $sealed = self::sealerWith('fedcba9876543210fedcba9876543210')->seal(new PageContext('client', 4, self::BROWSER));

        $rotated = (new Encrypter(self::KEY_IN_USE, 'aes-256-cbc'))->previousKeys(['fedcba9876543210fedcba9876543210']);

        $this->assertNotNull((new PageContextSealer($rotated))->open($sealed));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function whatIsNotAContext(): array
    {
        $encrypter = new Encrypter(self::KEY_IN_USE, 'aes-256-cbc');
        $sealed = static fn (array $payload): string => $encrypter->encryptString((string) json_encode($payload));
        $fine = ['v' => 1, 'p' => 'fa-ctx', 't' => 'client', 'i' => 4, 'k' => self::BROWSER];

        return [
            'empty' => [''],
            'not encrypted' => [(string) json_encode($fine)],
            'sealed with another key' => [(new Encrypter('fedcba9876543210fedcba9876543210', 'aes-256-cbc'))->encryptString((string) json_encode($fine))],
            'another purpose' => [$sealed(['p' => 'session'] + $fine)],
            'another version' => [$sealed(['v' => 2] + $fine)],
            'no subject type' => [$sealed(['t' => ''] + $fine)],
            'a subject type too long' => [$sealed(['t' => str_repeat('x', 33)] + $fine)],
            'an identifier written as text' => [$sealed(['i' => '4'] + $fine)],
            'a negative identifier' => [$sealed(['i' => -1] + $fine)],
            'a browser that is no identifier' => [$sealed(['k' => 'uuid-5'] + $fine)],
            'not json' => [$encrypter->encryptString('client:4')],
            'too long to be one' => [str_repeat('a', PageContextSealer::MAX_LENGTH + 1)],
        ];
    }

    #[DataProvider('whatIsNotAContext')]
    public function test_what_it_did_not_seal_does_not_open(string $sealed): void
    {
        $this->assertNull(self::sealerWith(self::KEY_IN_USE)->open($sealed));
    }

    /** A string too long to be a context costs no decryption. */
    public function test_a_string_too_long_is_not_even_decrypted(): void
    {
        $encrypter = new class implements StringEncrypter
        {
            public int $decrypted = 0;

            public function encryptString(#[\SensitiveParameter] $value): string
            {
                return $value;
            }

            public function decryptString($payload): string
            {
                $this->decrypted++;

                return $payload;
            }
        };

        (new PageContextSealer($encrypter))->open(str_repeat('a', PageContextSealer::MAX_LENGTH + 1));

        $this->assertSame(0, $encrypter->decrypted);
    }

    /** Opening what a request carries never reaches unserialize, whatever the key. */
    public function test_a_serialised_payload_is_not_one(): void
    {
        $encrypter = new Encrypter(self::KEY_IN_USE, 'aes-256-cbc');

        $this->assertNull((new PageContextSealer($encrypter))->open($encrypter->encrypt(['v' => 1, 'p' => 'fa-ctx'])));
    }

    private static function sealerWith(string $key): PageContextSealer
    {
        return new PageContextSealer(new Encrypter($key, 'aes-256-cbc'));
    }
}
