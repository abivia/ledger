<?php

namespace Abivia\Ledger\Models;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Revision;
use Abivia\Ledger\Messages\Currency;
use Abivia\Ledger\Traits\CommonResponseProperties;
use Abivia\Ledger\Traits\HasRevisions;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HigherOrderCollectionProxy;

/**
 * Currencies available to the ledger.
 *
 * @property Carbon $created_at When the record was created.
 * @property string $code The currency code.
 * @property int $decimals The number of decimals to carry.
 * @property Carbon $revision Revision timestamp to detect race condition on update.
 * @property Carbon $updated_at When the record was updated.
 * @mixin Builder
 * @mixin \Illuminate\Database\Query\Builder
 */
class LedgerCurrency extends Model
{
    use CommonResponseProperties, HasFactory, HasRevisions;

    const AMOUNT_SIZE = 32;
    const CODE_SIZE = 16;

    /**
     * @var string[] Casts for table columns
     */
    protected $casts = [
        'decimals' => 'int',
        'revision' => 'datetime',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $fillable = ['code', 'decimals'];
    public $incrementing = false;
    protected $keyType = 'string';

    public $primaryKey = 'code';

    /**
     * The revision Hash is computationally expensive, only calculated when required.
     *
     * @param $key
     * @return HigherOrderCollectionProxy|mixed|string|null
     * @throws Exception
     */
    public function __get($key)
    {
        if ($key === 'revisionHash') {
            return $this->getRevisionHash();
        }
        return parent::__get($key);
    }

    protected static function booted()
    {
        static::saved(function ($model) {
            $model->clearRevisionCache();
        });
    }

    public static function createFromMessage(Currency $message): self
    {
        $instance = new static();
        foreach ($instance->fillable as $property) {
            if (isset($message->{$property})) {
                $instance->{$property} = $message->{$property};
            }
        }
        $instance->save();
        $instance->refresh();

        return $instance;
    }

    /**
     * Look for a currency. If not found throw a Breaker.
     * @param string $currency Currency code.
     * @param int|null $errorCode Breaker code (default to bad request).
     * @return LedgerCurrency
     * @throws Breaker
     */
    public static function findOrBreaker(
        string $currency, int $errorCode = Breaker::BAD_REQUEST
    ): LedgerCurrency
    {
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $ledgerCurrency = LedgerCurrency::find($currency);
        if ($ledgerCurrency === null) {
            throw Breaker::withCode(
                $errorCode,
                [__('Currency :code not found.', ['code' => $currency])]
            );
        }

        return $ledgerCurrency;
    }

    /**
     * Convert to a response array.
     *
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function toResponse(array $options = []): array
    {
        $response = [
            'code' => $this->code,
            'decimals' =>$this->decimals,
        ];

        return $this->commonResponses($response, ['extra', 'names']);
    }

}
