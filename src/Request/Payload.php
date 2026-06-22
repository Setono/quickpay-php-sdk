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
 * Subclasses are `final class` with **mutable** `public` promoted properties and all-optional
 * constructor arguments, so a request can be built incrementally (`new CreatePaymentRequest()`, then
 * assign fields) or in one named-argument call. There is no construction-time validation — required
 * fields are enforced by the Quickpay API (a missing one surfaces as a `ValidationException`).
 */
abstract class Payload
{
}
