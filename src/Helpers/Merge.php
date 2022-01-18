<?php
declare(strict_types=1);

namespace Abivia\Ledger\Helpers;

use stdClass;

/**
 * Utility class for merging arrays ans sdtClass objects.
 */
class Merge
{
    /**
     * Merge source array into target array.
     *
     * @param array $target
     * @param array $source
     * @return void
     */
    public static function arrays(array &$target, array $source)
    {
        $target = array_merge_recursive($target, $source);
    }

    /**
     * Merge source array into an object.
     *
     * @param object $target
     * @param array $source
     * @return void
     */
    public static function arrayToObject(object $target, array $source)
    {
        foreach ($source as $property => $value) {
            if (isset($target->{$property})) {
                if (is_object($target->{$property}) && is_array($value)) {
                    self::arrayToObject($target->{$property}, $value);
                } elseif (is_object($target->{$property}) && is_object($value)) {
                    self::objects($target->{$property}, $value);
                } elseif (is_array($target->{$property})) {
                    if (is_array($value)) {
                        $target->{$property} = array_merge($target->{$property}, $value);
                    } elseif (is_scalar($value)) {
                        $target->{$property}[] = $value;
                    } else {
                        $target->{$property} = $value;
                    }
                } else {
                    $target->{$property} = $value;
                }
            } else {
                $target->{$property} = $value;
            }
        }
    }

    /**
     * Merge source object into target object.
     *
     * @param object $target
     * @param object $source
     * @return void
     */
    public static function objects(object $target, object $source)
    {
        foreach ($source as $property => $value) {
            if (isset($target->{$property})) {
                if (is_object($target->{$property}) && is_object($value)) {
                    self::objects($target->{$property}, $value);
                } elseif (is_array($target->{$property})) {
                    if (is_array($value)) {
                        $target->{$property} = array_merge($target->{$property}, $value);
                    } elseif (is_scalar($value)) {
                        $target->{$property}[] = $value;
                    } else {
                        $target->{$property} = $value;
                    }
                } else {
                    $target->{$property} = $value;
                }
            } else {
                $target->{$property} = $value;
            }
        }
    }

}
