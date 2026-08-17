<?php

declare(strict_types=1);

namespace Setono\Quickpay;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Setono\Quickpay\Client\Client;
use Setono\Quickpay\TestDouble\ScriptedHttpClient;

abstract class QuickpayTestCase extends TestCase
{
    protected const BASE = 'https://api.quickpay.net';

    protected const API_KEY = 'apikey';

    /**
     * @param bool|null $synchronized when `null` the `Client` constructor default is used, so tests
     *        without an explicit flag exercise the real default
     */
    protected function client(ScriptedHttpClient $http, ?bool $synchronized = null): Client
    {
        $psr17 = new Psr17Factory();

        if (null === $synchronized) {
            return new Client(self::API_KEY, httpClient: $http, requestFactory: $psr17, streamFactory: $psr17);
        }

        return new Client(
            self::API_KEY,
            httpClient: $http,
            requestFactory: $psr17,
            streamFactory: $psr17,
            synchronized: $synchronized,
        );
    }

    /**
     * Loads a captured Quickpay API response payload (see tests/Fixtures).
     */
    protected static function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__ . '/Fixtures/' . $name);
        if (false === $contents) {
            self::fail(sprintf('Fixture "%s" could not be read.', $name));
        }

        return $contents;
    }

    /**
     * A fresh, unique temp directory path (not created) for tests that need to write to disk.
     */
    protected static function tempDir(): string
    {
        return sys_get_temp_dir() . '/quickpay-sdk-test-' . bin2hex(random_bytes(4));
    }

    /**
     * Recursively remove a directory created by a test.
     */
    protected static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
