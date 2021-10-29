<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Intended as an internal multi-level break.
 */
class Breaker extends Exception
{
    public const BAD_REQUEST = 1;
    public const BAD_ACCOUNT = 2;
    public const INVALID_OPERATION = 3;
    public const NOT_IMPLEMENTED = 4;
    public const BAD_REVISION = 5;
    public const INVALID_DATA = 6;

    private static array $messages = [
        0 => 'Undefined error code',
        self::BAD_ACCOUNT => 'Account invalid or not found.',
        self::BAD_REQUEST => 'Bad request.',
        self::BAD_REVISION => 'Outdated or invalid revision token.',
        self::INVALID_DATA => 'Error in data source.',
        self::INVALID_OPERATION => 'Request violates business rule.',
        self::NOT_IMPLEMENTED => 'Feature is not implemented.',
    ];

    public static function fromCode(int $code, array $args = [], Throwable $previous = null)
    : self
    {
        $message = __(self::$messages[$code] ?? self::$messages[0], $args);
        return new static($message, $code, $previous);
    }
}
