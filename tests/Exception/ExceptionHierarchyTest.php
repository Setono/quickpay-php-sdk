<?php

declare(strict_types=1);

namespace Setono\Quickpay\Exception;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExceptionHierarchyTest extends TestCase
{
    /**
     * @param class-string $class
     * @param class-string $expectedBase
     */
    #[Test]
    #[DataProvider('hierarchy')]
    public function it_has_the_expected_hierarchy(string $class, string $expectedBase): void
    {
        self::assertTrue(is_a($class, $expectedBase, true));
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function hierarchy(): \Generator
    {
        yield 'validation is a client error' => [ValidationException::class, ClientErrorException::class];
        yield 'unauthorized is a client error' => [UnauthorizedException::class, ClientErrorException::class];
        yield 'forbidden is a client error' => [ForbiddenException::class, ClientErrorException::class];
        yield 'not found is a client error' => [NotFoundException::class, ClientErrorException::class];
        yield 'method not allowed is a client error' => [MethodNotAllowedException::class, ClientErrorException::class];
        yield 'conflict is a client error' => [ConflictException::class, ClientErrorException::class];
        yield 'too many requests is a client error' => [TooManyRequestsException::class, ClientErrorException::class];
        yield 'internal server error is a server error' => [InternalServerErrorException::class, ServerErrorException::class];
        yield 'mapping is a malformed response' => [MappingException::class, MalformedResponseException::class];
        yield 'client error is response aware' => [ClientErrorException::class, ResponseAwareException::class];
        yield 'server error is response aware' => [ServerErrorException::class, ResponseAwareException::class];
        yield 'unexpected status code is response aware' => [UnexpectedStatusCodeException::class, ResponseAwareException::class];

        yield 'validation is a quickpay exception' => [ValidationException::class, QuickpayException::class];
        yield 'internal server error is a quickpay exception' => [InternalServerErrorException::class, QuickpayException::class];
        yield 'malformed response is a quickpay exception' => [MalformedResponseException::class, QuickpayException::class];
        yield 'unexpected status code is a quickpay exception' => [UnexpectedStatusCodeException::class, QuickpayException::class];
        yield 'invalid url is a quickpay exception' => [InvalidUrlException::class, QuickpayException::class];
        yield 'invalid checksum is a quickpay exception' => [InvalidChecksumException::class, QuickpayException::class];
        yield 'invalid callback is a quickpay exception' => [InvalidCallbackException::class, QuickpayException::class];
    }

    #[Test]
    public function it_parses_the_quickpay_error_body(): void
    {
        $body = '{"message":"Validation error","errors":{"order_id":["has already been taken"]},"error_code":"invalid_parameters"}';
        $e = new ValidationException(new Response(422, [], $body), body: $body);

        self::assertSame('Validation error', $e->getMessageText());
        self::assertSame('invalid_parameters', $e->getErrorCode());
        self::assertSame(['order_id' => ['has already been taken']], $e->getValidationErrors());
    }

    #[Test]
    public function the_error_getters_degrade_gracefully_on_an_empty_body(): void
    {
        $e = new InternalServerErrorException(new Response(500), body: '');

        self::assertNull($e->getMessageText());
        self::assertNull($e->getErrorCode());
        self::assertSame([], $e->getValidationErrors());
    }
}
