<?php

namespace App\Exceptions;

use Exception;
use Throwable;
use function implode;

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
    public const INTEGRITY_ERROR = 7;

    protected array $errors;

    private static array $messages = [
        0 => 'Undefined error code',
        self::BAD_ACCOUNT => 'Account invalid or not found.',
        self::BAD_REQUEST => 'Bad request.',
        self::BAD_REVISION => 'Outdated or invalid revision token.',
        self::INTEGRITY_ERROR => 'Ledger data is inconsistent.',
        self::INVALID_DATA => 'Error in data source.',
        self::INVALID_OPERATION => 'Request violates business rule.',
        self::NOT_IMPLEMENTED => 'Feature is not implemented.',
    ];

    public function addError(string $error)
    {
        $this->errors[] = $error;
    }

    public function mergeErrors(array $errors = [])
    {
        $this->errors = array_merge($this->errors, $errors);
    }

    public function getErrors(bool $withMessage = false): array
    {
        $result = $this->errors;
        if ($withMessage) {
            array_unshift($result, $this->message);
        }
        return $result;
    }

    public function setErrors(array $errors)
    {
        $this->errors = $errors;
    }

    /**
     * Generate a new instance using a predefined code.
     *
     * @param int $code The underlying error condition
     * @param array $errors An array of supplemental error text
     * @param Throwable|null $previous Any related exception
     *
     * @return static
     */
    public static function withCode(int $code, array $errors = [], Throwable $previous = null): self
    {
        $message = __(self::$messages[$code] ?? self::$messages[0]);
        $exception = new static($message, $code, $previous);
        $exception->setErrors($errors);
        return $exception;
    }

}
