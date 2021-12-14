<?php

namespace Abivia\Ledger\Models;

class NoDatabase
{
    /**
     * @var array Each element is either a property name or array of
     * [object property name, source property name].
     */
    protected static array $copyable = [];

    /**
     * Selectively copy information from a data array.
     *
     * @param array|object $data
     * @return $this
     */
    public function copy($data): self
    {
        foreach (static::$copyable as $info) {
            if (is_array($info)) {
                [$property, $fromProperty] = $info;
            } else {
                $property = $info;
                $fromProperty = $info;
            }
            if (isset($data[$fromProperty])) {
                if (is_object($data)) {
                    $this->{$property} = $data->$fromProperty;
                } else {
                    $this->{$property} = $data[$fromProperty];
                }
            }
        }

        return $this;
    }

}
