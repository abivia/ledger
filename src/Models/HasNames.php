<?php

namespace Abivia\Ledger\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Trait for Models with related LedgerNames.
 */
trait HasNames
{

    public function names(): HasMany
    {
        return $this->hasMany(LedgerName::class, 'ownerUuid', $this->primaryKey);
    }

    public function nameIn(mixed $languages): string
    {
        if (is_string($languages)) {
            $languages = [$languages];
        }
        /** @noinspection PhpUndefinedMethodInspection */
        $names = $this->names->keyBy('language');
        foreach ($languages as $language) {
            if (isset($names[$language])) {
                $name = $names[$language]->name;
                break;
            }
        }
        if (!isset($name)) {
            $name = $names->first()->name;
        }

        return $name;
    }

}
