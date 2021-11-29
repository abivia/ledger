<?php

namespace App\Models\Messages;

use App\Exceptions\Breaker;
use function Symfony\Component\String\s;

abstract class Message
{
    // Message flag settings State/Function flags from bit 30 down
    /**
     * Set when the request came from the JSON API.
     */
    public const F_API = 2**30;
    /**
     * Set on a request to validate the message.
     */
    public const F_VALIDATE = 2**29;

    // Operation flags from bit 0 up.
    public const ALL_OPS = 0b111111;
    public const OP_ADD = 1;
    public const OP_CREATE = 2;
    public const OP_DELETE = 2**2;
    public const OP_GET = 2**3;
    public const OP_QUERY = 2**4;
    public const OP_UPDATE = 2**5;

    /**
     * @var array Each element is either a property name or array of
     * [property name, operation mask]. If a mask is provided it must
     * match the current operation flags.
     */
    protected static array $copyable = [];

    private static array $opMap = [
        'add' => self::OP_ADD,
        'create' => self::OP_CREATE,
        'delete' => self::OP_DELETE,
        'get' => self::OP_GET,
        'query' => self::OP_QUERY,
        'update' => self::OP_UPDATE,
        'validate' => self::F_VALIDATE,
    ];

    public function copy(array $data, int $opFlags): self
    {
        foreach (static::$copyable as $info) {
            if (is_array($info)) {
                [$property, $mask] = $info;
                if (is_array($property)) {
                    [$property, $fromProperty] = $property;
                } else {
                    $fromProperty = $property;
                }
            } else {
                $property = $info;
                $fromProperty = $info;
                $mask = $opFlags;
            }
            if ($opFlags & $mask && isset($data[$fromProperty])) {
                $this->{$property} = $data[$fromProperty];
            }
        }

        return $this;
    }

    /**
     * Populate the message with data from an array of request data.
     *
     * @param array $data Data generated by the request.
     * @param int $opFlags Bitmask of the request operation (may include FM_VALIDATE)
     * @return static Message initialized with relevant data.
     * @throws Breaker On error, e.g. required data is missing or on validation.
     */
    public abstract static function fromRequest(array $data, int $opFlags): self;

    /**
     * Convert a method name to an operation bitmask.
     *
     * @param string $method The method name.
     * @param array $options Options are:
     * add Bitmask of flags to add to the result.
     * allowZero boolean, if not set an exception is thrown when there is no matching flag.
     * disallow Bitmask of methods to ignore.
     * @return int Operation bitmask, zero if not recognized or disallowed.
     * @throws Breaker
     */
    public static function toOpFlags(string $method, array $options = []): int
    {
        $opFlags = self::$opMap[$method] ?? 0;
        if ($opFlags !== 0 && isset($options['disallow'])) {
            $opFlags &= ~$options['disallow'];
        }
        if (!($options['allowZero'] ?? false) && $opFlags === 0) {
            throw Breaker::withCode(
                Breaker::INVALID_OPERATION,
                [':operation is not a valid function.', ['operation' => $method]]
            );
        }
        if (isset($options['add'])) {
            $opFlags |= $options['add'];
        }
        return $opFlags;
    }

    /**
     * Check the message for validity.
     *
     * @param int $opFlags Operation bitmask.
     * @return self
     * @throws Breaker When data is not valid.
     */
    public abstract function validate(int $opFlags): self;

}
