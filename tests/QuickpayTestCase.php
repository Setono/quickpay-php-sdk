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

    protected function client(ScriptedHttpClient $http): Client
    {
        $psr17 = new Psr17Factory();

        return new Client(self::API_KEY, httpClient: $http, requestFactory: $psr17, streamFactory: $psr17);
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
}
