<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request;

/**
 * Base class for every request DTO sent as a JSON body.
 *
 * Acts as a null-stripping, snake_case marker: the SDK's {@see \CuyZ\Valinor\NormalizerBuilder}
 * configuration (see {@see \Setono\Quickpay\Client\Client::registerNormalizerTransformers()}) has a
 * transformer that matches `Payload` and, for every `Payload` at every depth, converts the
 * object-normalized array's camelCase property names to the snake_case keys Quickpay expects and
 * filters out `null` / `[]` entries — so optional DTO properties that default to `null` are absent
 * from the produced JSON rather than serialized as `"field": null`.
 *
 * Subclasses are `final class` with **mutable** `public` promoted properties. Fields the Quickpay
 * API *unconditionally* requires — verified against the live API, not just the docs — are required,
 * non-nullable constructor arguments, so forgetting one fails at the call site (and is caught by
 * static analysis) instead of surfacing as a `ValidationException` after a network round-trip. All
 * other arguments are optional, so a request can be built in one named-argument call or
 * incrementally (`new CreatePaymentRequest('order-1', 'DKK')`, then assign fields). Beyond those
 * required arguments there is no construction-time validation — conditional requirements and format
 * rules are enforced by the Quickpay API (a violation surfaces as a `ValidationException`).
 */
abstract class Payload
{
}
