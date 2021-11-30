<?php
declare(strict_types=1);

namespace App\Helpers;

use Exception;
use stdClass;

class Merge
{
    public static function arrays(array &$target, array $source)
    {
        $target = array_merge_recursive($target, $source);
    }

    public static function arrayToObject(stdClass $target, array $source)
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

    public static function objects(stdClass $target, stdClass $source)
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
