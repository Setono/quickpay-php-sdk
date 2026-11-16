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
    private int $position = 0;

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
        return $this->position;
    }

    public function eof(): bool
    {
        return $this->position >= strlen($this->contents);
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
        if (null !== $this->failure) {
            throw $this->failure;
        }

        $chunk = substr($this->contents, $this->position, $length);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function getContents(): string
    {
        return $this->read(max(0, strlen($this->contents) - $this->position));
    }

    public function getMetadata(?string $key = null): mixed
    {
        return null === $key ? [] : null;
    }
}
