<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Models\LedgerAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as DbBuilder;

/**
 * Messages that return paginated results over a range.
 */
abstract class Paginated extends Message
{
    use HasNames;

    /**
     * @var array A list of single codes or code range selections
     */
    public array $codes = [];

    protected static array $copyable = [
        'after',
        'limit',
        'codes',
    ];
    /**
     * @var int The maximum number of elements to return.
     */
    public int $limit;
    /**
     * @var array
     */
    public array $names = [];

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data, int $opFlags = self::OP_ADD): self
    {
        $message = new static();
        $message->copy($data, $opFlags);
        $message->loadNames($data, $opFlags);
        $hasBegin = isset($data['range']) && $data['range'] !== '';
        $hasEnd = isset($data['rangeEnding']) && $data['rangeEnding'] !== '';
        if ($hasBegin || $hasEnd) {
            $item = ['', ''];
            if ($hasBegin) {
                $item[0] = $data['range'];
            }
            if ($hasEnd) {
                $item[1] = $data['rangeEnding'];
            }
            $message->codes[] = $item;
        }

        return $message;
    }

    /**
     * Apply a code range selection to a query.
     * @param Builder|DbBuilder $query
     * @param array $item
     * @param bool $negate
     * @param bool $wild
     * @return Builder|DbBuilder
     */
    private function selectCodeRange(
        Builder|DbBuilder $query,
        array $item,
        bool $negate,
        bool $wild
    ): Builder|DbBuilder
    {
        if ($negate) {
            if ($item[0] === '') {
                $query = $query->where('code', '>', $item[1]);
            } elseif ($item[1] === '') {
                $query = $query->where('code', '<', $item[0]);
            } else {
                $query = $query->whereNotBetween('code', $item);
            }
        } elseif ($wild) {
            $query = $query->orWhere(function ($query) use ($item) {
                foreach ($item as $search) {
                    $query = $query->where('code', 'like', $search);
                }
                return $query;
            });
        } else {
            $query = $query->orWhere(function ($query) use ($item) {
                if ($item[0] === '') {
                    $query = $query->where('code', '<=', $item[1]);
                } elseif ($item[1] === '') {
                    $query = $query->where('code', '>=', $item[0]);
                } else {
                    $query = $query->whereBetween('code', $item);
                }
                return $query;
            });
        }
        $query->toSql();
        return $query;
    }

    /**
     * Apply code selections to a query
     * @param Builder|DbBuilder $query
     * @return Builder|DbBuilder
     */
    public function selectCodes(Builder|DbBuilder $query): Builder|DbBuilder
    {
        foreach ($this->codes as $item) {
            $negate = false;
            $wild = false;
            if (is_array($item)) {
                $negate = ($item[0] === '!' || $item[0] === '!*');
                $wild = ($item[0] === '*' || $item[0] === '!*');
                if ($negate || $wild) {
                    if (count($item) > 2) {
                        array_shift($item);
                    } else {
                        $item = $item[1];
                    }
                }
            }
            if (is_array($item)) {
                $query = $this->selectCodeRange($query, $item, $negate, $wild);
            } else {
                if ($negate) {
                    if ($wild) {
                        $query = $query->whereNot('code', 'like', $item);
                    } else {
                        $query = $query->where('code', '!=', $item);
                    }
                } elseif ($wild) {
                    $query = $query->where('code', 'like', "%$item%");
                } else {
                    $query = $query->orWhere('code', '=', $item);
                }
            }
        }

        return $query;
    }

    /**
     * Apply name selections to a query.
     * @param Builder|DbBuilder $query
     * @return Builder|DbBuilder
     */
    public function selectNames(Builder|DbBuilder $query): Builder|DbBuilder
    {
        /** @var Name $name */
        foreach ($this->names as $name) {
            if ($name->exclude) {
                $operator = $name->like ? 'not like' : '!=';
                if ($name->language === '') {
                    // Selecting without regard to language
                    $query = $query->where('name', $operator, $name->name);
                } elseif ($name->name === '') {
                    // Selecting without regard to name
                    $query = $query->where('language', $operator, $name->language);
                } else {
                    // Select for name and language
                    $query = $query->where(function ($query) use ($name, $operator) {
                        return $query->where('name', $operator, $name->name)
                            ->orWhere('language', $operator, $name->language);
                    });
                }
            } else {
                $operator = $name->like ? 'like' : '=';
                $query = $query->orWhere(function ($query) use ($name, $operator) {
                    if ($name->language !== '') {
                        $query = $query->where('language', $operator, $name->language);
                    }
                    if ($name->name !== '') {
                        $query = $query->where('name', $operator, $name->name);
                    }
                    return $query;
                });
            }
        }
        return $query;
    }

    /**
     * @inheritDoc
     */
    public function validate(?int $opFlags = null): self
    {
        $opFlags ??= $this->getOpFlags();
        // Limit results on API calls
        if ($opFlags & self::F_API) {
            $limit = LedgerAccount::rules()->pageSize;
            if (isset($this->limit)) {
                $this->limit = min($this->limit, $limit);
            } else {
                $this->limit = $limit;
            }
        }
        /** @var Name $name */
        foreach ($this->names as $name) {
            $name->validate($opFlags);
        }
        return $this;
    }

}
