<?php

namespace App\Services\Notify;

use RuntimeException;

/**
 * The `data` of a catalogued event holds something that cannot be kept at all
 * (today: a nested array under an allowed key). The ingest API answers 422.
 */
class InvalidEventData extends RuntimeException
{
    /** @param  array<string, array<int, string>>  $errors  field path => messages */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('The event data is not valid for this event.');
    }

    /** @return array<string, array<int, string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}
