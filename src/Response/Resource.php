<?php

declare(strict_types=1);

namespace Setono\Quickpay\Response;

/**
 * Base class for entry-point Response DTOs — the things returned directly by an Endpoint method.
 *
 * Each subclass represents one Quickpay API resource. Subclasses declare typed `public readonly`
 * constructor-promoted fields for the wire format we model. After Valinor maps the typed fields,
 * the endpoint stamps the full decoded JSON onto `$raw` so consumers can reach any field we haven't
 * yet typed (using the original snake_case keys from the Quickpay API docs).
 *
 * The class itself is NOT `readonly` (because `$raw` is mutable until the endpoint sets it).
 * Subclasses should be `final class` (not `final readonly class`) — `rector.php` excludes
 * `src/Response` from `ReadOnlyPropertyRector` to keep this invariant.
 */
abstract class Resource
{
    /**
     * The full decoded JSON response, with the original snake_case keys. Populated by the endpoint
     * after Valinor maps the typed fields; empty array on hand-constructed instances (e.g. in
     * tests).
     *
     * @var array<array-key, mixed>
     */
    public array $raw = [];
}
