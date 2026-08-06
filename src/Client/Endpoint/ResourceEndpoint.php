<?php

declare(strict_types=1);

namespace Setono\Quickpay\Client\Endpoint;

use Setono\Quickpay\Request\Payload;
use Setono\Quickpay\Response\Resource;

/**
 * Abstract base for endpoints that represent a single REST resource at a path with a typed item DTO.
 *
 * Subclasses declare two protected hints: {@see self::getPath()} (the resource URL path) and
 * {@see self::getItemClass()} (the typed DTO class). Shared helpers map the JSON response to that
 * DTO and stamp `$raw`:
 *  - {@see self::getOne()}    — GET `"{getPath()}"` or `"{getPath()}/{$id}"`.
 *  - {@see self::createOne()} — POST a typed body.
 *  - {@see self::update()}    — PUT a typed body to `"{getPath()}/{$id}"`.
 *  - {@see self::operation()} — POST (optionally a body) to `"{getPath()}/{$id}/{$action}"`.
 *  - {@see self::putSub()}    — PUT a typed body to `"{getPath()}/{$id}/{$sub}"`, returning the raw
 *                              decoded array (for sub-resources mapped to a class other than the
 *                              endpoint's item class, e.g. the payment link).
 *
 * @template T of Resource
 */
abstract class ResourceEndpoint extends Endpoint
{
    /**
     * @internal Implemented by SDK endpoint subclasses to declare their resource path. Not part of
     *           the package's BC promise; downstream subclassing is not supported.
     */
    abstract protected static function getPath(): string;

    /**
     * @internal Implemented by SDK endpoint subclasses. Not part of the package's BC promise.
     *
     * @return class-string<T>
     */
    abstract protected static function getItemClass(): string;

    /**
     * @param int|string|null $id when null, fetches `getPath()`; when given, fetches `"{getPath()}/{$id}"`
     *
     * @return T
     */
    protected function getOne(int|string|null $id = null): Resource
    {
        $path = null === $id
            ? static::getPath()
            : sprintf('%s/%s', static::getPath(), $id);

        return $this->mapItem(static::getItemClass(), $this->client->get($path));
    }

    /**
     * @return T
     */
    protected function createOne(Payload $request): Resource
    {
        return $this->mapItem(static::getItemClass(), $this->client->post(static::getPath(), $request));
    }

    /**
     * @return T
     */
    protected function update(int|string $id, Payload $request): Resource
    {
        return $this->mapItem(
            static::getItemClass(),
            $this->client->patch(sprintf('%s/%s', static::getPath(), $id), $request),
        );
    }

    /**
     * POST to `"{getPath()}/{$id}/{$action}"` (e.g. authorize, capture, refund, cancel) and map the
     * returned resource. The `$request` body is optional — operations such as cancel take no body.
     *
     * Quickpay processes operations asynchronously by default and returns a `202 Accepted` with the
     * operation still pending — the 202 body is the full resource, but only a snapshot taken when
     * the operation was queued (the new operation has `pending: true` and no status code yet; the
     * resource's other fields still hold their pre-operation values). Pass `$synchronized = true` to
     * add the `?synchronized` flag, which makes Quickpay wait and return the completed transaction
     * (its final state) instead. When `$synchronized` is `null` the client-wide default
     * ({@see \Setono\Quickpay\Client\ClientInterface::isSynchronized()}) applies.
     *
     * @return T
     */
    protected function operation(int|string $id, string $action, ?Payload $request = null, ?bool $synchronized = null): Resource
    {
        $path = sprintf('%s/%s/%s', static::getPath(), $id, $action);
        if ($synchronized ?? $this->client->isSynchronized()) {
            $path .= '?synchronized';
        }

        return $this->mapItem(static::getItemClass(), $this->client->post($path, $request));
    }

    /**
     * PUT a typed body to `"{getPath()}/{$id}/{$sub}"` and return the raw decoded array, so the
     * caller can map it to a class other than the endpoint's item class.
     *
     * @return array<array-key, mixed>
     */
    protected function putSub(int|string $id, string $sub, Payload $request): array
    {
        return $this->client->put(sprintf('%s/%s/%s', static::getPath(), $id, $sub), $request);
    }
}
