<?php

declare(strict_types=1);

namespace Falcon\Analytics\Tests\Feature;

use Falcon\Analytics\Tests\TestCase;

/**
 * Each test process publishes the compiled files into its own directory.
 *
 * Workers boot the same bench application, whose `public_path()` is one
 * directory. On a fresh checkout they would all publish at once, and the kit's
 * staleness guard would read a half-written file in a test about something else.
 */
final class EachWorkerPublishesIntoItsOwnDirectoryTest extends TestCase
{
    public function test_the_booted_application_publishes_where_this_worker_publishes(): void
    {
        $token = getenv('TEST_TOKEN');
        $expected = $this->publishedDirectory(is_string($token) ? $token : '');

        $this->assertSame(
            str_replace('\\', '/', $expected),
            str_replace('\\', '/', public_path()),
            "L'application d'essai doit publier là où ce processus publie.",
        );
    }

    public function test_a_named_worker_gets_a_directory_of_its_own_and_a_sequential_run_keeps_the_default(): void
    {
        $this->assertStringEndsWith('/laravel/public-essai', str_replace('\\', '/', $this->publishedDirectory('essai')));
        $this->assertStringEndsWith('/laravel/public', str_replace('\\', '/', $this->publishedDirectory('')));
    }

    /** Read through the bench's private method, so this test follows the rule the bench applies. */
    private function publishedDirectory(string $token): string
    {
        $method = new \ReflectionMethod(TestCase::class, 'publishedDirectoryFor');

        return (string) $method->invoke(null, $token);
    }
}
