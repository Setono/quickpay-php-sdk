<?php

declare(strict_types=1);

namespace Setono\Quickpay\Request\Payment;

use Setono\Quickpay\Request\Payload;

/**
 * An invoice or shipping address attached to a payment. Sent as a nested object; property names are
 * converted to the snake_case keys Quickpay expects (e.g. `zipCode` → `zip_code`).
 */
final class Address extends Payload
{
    public function __construct(
        public ?string $name = null,
        public ?string $att = null,
        public ?string $companyName = null,
        public ?string $street = null,
        public ?string $houseNumber = null,
        public ?string $houseExtension = null,
        public ?string $city = null,
        public ?string $zipCode = null,
        public ?string $region = null,
        public ?string $countryCode = null,
        public ?string $vatNo = null,
        public ?string $phoneNumber = null,
        public ?string $mobileNumber = null,
        public ?string $email = null,
    ) {
    }
}
