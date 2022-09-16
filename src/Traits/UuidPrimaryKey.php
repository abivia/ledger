<?php
declare(strict_types=1);

namespace Abivia\Ledger\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait UuidPrimaryKey
{
    protected static function bootUuidPrimaryKey()
    {
        static::creating(function (Model $model) {
            if (!$model->{$model->primaryKey}) {
                $model->{$model->primaryKey} = Str::uuid();
            }
        });
    }

}
