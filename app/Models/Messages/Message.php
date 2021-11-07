<?php

namespace App\Models\Messages;

use function Symfony\Component\String\s;

abstract class Message
{
    public const OP_ADD = 1;
    public const OP_CREATE = 2;
    public const OP_DELETE = 2**2;
    public const OP_GET = 2**3;
    public const OP_UPDATE = 2**4;
    public const OP_VALIDATE = 2**30;

    private static array $opMap = [
        'add' => self::OP_ADD,
        'create' => self::OP_CREATE,
        'delete' => self::OP_DELETE,
        'get' => self::OP_GET,
        'update' => self::OP_UPDATE,
        'validate' => self::OP_VALIDATE,
    ];

    public abstract static function fromRequest(array $data, int $opFlag): self;
    /**
     * @param string $method
     * @return int
     */
    protected static function toOpFlag(string $method): int
    {
        return self::$opMap[$method] ?? 0;
    }

    public abstract function validate(int $opFlag): self;

}
