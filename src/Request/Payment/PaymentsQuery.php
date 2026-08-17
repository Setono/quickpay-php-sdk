<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Enum\PaymentState;
use Setono\Quickpay\Request\CollectionRequestOptions;

/**
 * Typed filters + pagination for `GET /payments` (listing / searching payments), for use with
 * {@see \Setono\Quickpay\Client\Endpoint\PaymentsEndpoint::getPage()} and `paginate()`:
 *
 * ```
 * $client->payments()->paginate(new PaymentsQuery(state: PaymentState::New, accepted: true, pageSize: 50));
 * ```
 *
 * Every filter is optional and only sent when set. Semantics verified against the live API:
 *  - `orderId` is an EXACT, case-sensitive match (and `order_id` is unique per account, so it
 *    yields at most one payment — see `PaymentsEndpoint::findByOrderId()`).
 *  - `accepted` / `fraudSuspected` are sent as `true` / `false`.
 *  - `minTime` / `maxTime` are sent in the format the API documents (`Y-m-d H:i:s O`); the
 *    timezone of the given object is preserved.
 *  - `sortDir` must be `asc` or `desc` (anything else is rejected by the API with a
 *    `ValidationException`); the default order is newest first.
 * The API's `page_key` and `date[...]` / `timestamp` parameters are not modeled — pass them (or
 * any future filter) verbatim via `$extra`.
 */
final class PaymentsQuery extends CollectionRequestOptions
{
    /**
     * The `min_time` / `max_time` format the API documents: `%Y-%m-%d %H:%M:%S %z`.
     */
    public const TIME_FORMAT = 'Y-m-d H:i:s O';

    /**
     * @param string|null $orderId find the payment with exactly this `order_id`
     * @param PaymentState|string|null $state filter by payment state
     * @param bool|null $accepted only payments (not) accepted by the acquirer
     * @param \DateTimeInterface|null $minTime only payments created at or after this time
     * @param \DateTimeInterface|null $maxTime only payments created before this time
     * @param string|null $acquirer filter by acquirer (e.g. `clearhaus`)
     * @param bool|null $fraudSuspected filter by suspected fraud
     * @param int|null $id find by payment (transaction) id
     * @param string|null $sortBy property to sort by (e.g. `created_at`)
     * @param 'asc'|'desc'|null $sortDir sort direction
     * @param int|null $operationsSize maximum number of operations to include per payment
     * @param array<string, scalar|null> $extra additional query parameters, passed through verbatim
     */
    public function __construct(
        public readonly ?string $orderId = null,
        public readonly PaymentState|string|null $state = null,
        public readonly ?bool $accepted = null,
        public readonly ?\DateTimeInterface $minTime = null,
        public readonly ?\DateTimeInterface $maxTime = null,
        public readonly ?string $acquirer = null,
        public readonly ?bool $fraudSuspected = null,
        public readonly ?int $id = null,
        public readonly ?string $sortBy = null,
        public readonly ?string $sortDir = null,
        public readonly ?int $operationsSize = null,
        public readonly array $extra = [],
        int $page = 1,
        int $pageSize = 20,
    ) {
        parent::__construct($page, $pageSize);
    }

    public function toArray(): array
    {
        $query = $this->extra;

        $filters = [
            'order_id' => $this->orderId,
            'state' => $this->state instanceof PaymentState ? $this->state->value : $this->state,
            'accepted' => self::bool($this->accepted),
            'min_time' => $this->minTime?->format(self::TIME_FORMAT),
            'max_time' => $this->maxTime?->format(self::TIME_FORMAT),
            'acquirer' => $this->acquirer,
            'fraud_suspected' => self::bool($this->fraudSuspected),
            'id' => $this->id,
            'sort_by' => $this->sortBy,
            'sort_dir' => $this->sortDir,
            'operations_size' => $this->operationsSize,
        ];

        foreach ($filters as $name => $value) {
            if (null !== $value) {
                $query[$name] = $value;
            }
        }

        // page / page_size always come from the typed options (and last), even if `$extra` names them.
        unset($query['page'], $query['page_size']);

        return $query + parent::toArray();
    }

    private static function bool(?bool $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return $value ? 'true' : 'false';
    }
}
