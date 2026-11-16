<?php

declare(strict_types=1);

namespace Setono\Quickpay\TestDouble;

use Psr\Http\Message\StreamInterface;

/**
 * A response body the way a streaming PSR-18 client hands it over: readable once, front to back,
 * and not seekable — so a second read yields nothing.
 *
 * Given a `$failure`, every read throws it instead, like a connection that breaks off after the
 * headers arrived (nyholm/psr7 reports that as "Unable to read stream contents: …").
 */
final class NonSeekableStream implements StreamInterface
{
    private bool $consumed = false;

    public function __construct(
        private readonly string $contents,
        private readonly ?\RuntimeException $failure = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->getContents();
    }

    public function close(): void
    {
    }

    /**
     * @return resource|null
     */
    public function detach()
    {
        return null;
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function tell(): int
    {
        return $this->consumed ? strlen($this->contents) : 0;
    }

    public function eof(): bool
    {
        return $this->consumed;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        throw new \RuntimeException('Stream is not seekable');
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new \RuntimeException('Stream is not writable');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        return $this->getContents();
    }

    public function getContents(): string
    {
        if (null !== $this->failure) {
            throw $this->failure;
        }

        if ($this->consumed) {
            return '';
        }

        $this->consumed = true;

        return $this->contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        return null === $key ? [] : null;
    }
}
