<?php

namespace App\Models\Messages;

use function Symfony\Component\String\s;

abstract class Message
{
    public const OP_ADD = 1;
    public const OP_CREATE = 2;
    public const OP_DELETE = 4;
    public const OP_GET = 8;
    public const OP_UPDATE = 16;

    private static array $opMap = [
        'add' => self::OP_ADD,
        'create' => self::OP_CREATE,
        'delete' => self::OP_DELETE,
        'get' => self::OP_GET,
        'update' => self::OP_UPDATE,
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

}
