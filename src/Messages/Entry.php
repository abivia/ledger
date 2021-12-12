<?php
declare(strict_types=1);

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Messages\Message;
use Carbon\Carbon;

class Entry extends Message
{
    /**
     * @var string[] Translation arguments. Optional.
     */
    public array $arguments = [];

    protected static array $copyable = [
        ['currency', self::OP_ADD],
        ['description', self::OP_ADD | self::OP_UPDATE],
        [['descriptionArgs', 'arguments'], self::OP_ADD | self::OP_UPDATE],
        //['domain', self::OP_ADD],
        ['extra', self::OP_ADD | self::OP_UPDATE],
        ['id', self::OP_DELETE | self::OP_GET | self::OP_UPDATE],
        //['journal', self::OP_ADD],
        ['language', self::OP_UPDATE],
        ['reviewed', self::OP_ADD | self::OP_UPDATE],
        ['revision', self::OP_DELETE | self::OP_UPDATE],
        //[['date', 'transDate'], self::OP_ADD | self::OP_UPDATE],
    ];

    /**
     * @var string Currency code. If not provided, the domain's default is used.
     */
    public string $currency;

    /**
     * @var string Transaction description. Required on add.
     */
    public string $description;

    /**
     * @var Detail[] Transaction detail records.
     */
    public array $details = [];

    /**
     * @var EntityRef Ledger domain. If not provided the default is used.
     */
    public EntityRef $domain;

    /**
     * @var mixed
     */
    public $extra;

    /**
     * @var int|null The transaction ID, only used on update.
     */
    public int $id;

    /**
     * @var EntityRef Sub-journal reference. Only relevant when adding an entry.
     */
    public EntityRef $journal;

    /**
     * @var string|null Language used for the description. If missing, ledger default used.
     */
    public string $language;

    public Reference $reference;

    /**
     * @var bool Reviewed flag. If absent, set to the ledger default.
     */
    public bool $reviewed = false;

    /**
     * @var string Revision signature. Required for update.
     */
    public string $revision;

    /**
     * @var Carbon Transaction date. Required on add, optional on update.
     */
    public Carbon $transDate;

    /**
     * @inheritdoc
     */
    public static function fromArray(array $data, int $opFlags): self
    {
        $entry = new static();
        $entry->copy($data, $opFlags);
        if ($opFlags & self::OP_ADD) {
            if (isset($data['domain'])) {
                $entry->domain = new EntityRef();
                $entry->domain->code = $data['domain'];
            }
            if (isset($data['journal'])) {
                $entry->journal = new EntityRef();
                $entry->journal->code = $data['journal'];
            }
        }
        if ($opFlags & (self::OP_ADD | self::OP_UPDATE)) {
            if (isset($data['reference'])) {
                $entry->reference = Reference::fromArray($data['reference'], $opFlags);
            }
            if (isset($data['date'])) {
                $entry->transDate = new Carbon($data['date']);
            }
            $entry->details = [];
            foreach ($data['details'] ?? [] as $detail) {
                $entry->details[] = Detail::fromArray($detail, $opFlags);
            }
        }
        if ($opFlags & self::F_VALIDATE) {
            $entry->validate($opFlags);
        }

        return $entry;
    }

    /**
     * @inheritdoc
     */
    public function validate(int $opFlags): self
    {
        $errors = [];
        $rules = LedgerAccount::rules();
        if ($opFlags & self::OP_ADD) {
            if (count($this->details) === 0) {
                $errors[] = __('Entry has no details.');
            }
            if (!isset($this->description)) {
                $errors[] = __('Transaction description is required.');
            }
            if (!isset($this->domain)) {
                $this->domain = new EntityRef();
                $this->domain->code = $rules->domain->default;
            }
            if (!isset($this->language)) {
                $this->language = $rules->language->default;
            }
            if (!isset($this->transDate)) {
                $this->reviewed = $rules->entry->reviewed;
            }
        }
        if ($opFlags & (self::OP_ADD | self::OP_UPDATE)) {
            $openDate = Carbon::parse($rules->openDate);
            if ($this->transDate->lessThan($openDate)) {
                $errors[] = __(
                    'Transaction date cannot be earlier than opening date :date.',
                    ['date' => $openDate->format(LedgerAccount::systemDateFormat())]
                );
            }
        }
        if ($opFlags & (self::OP_DELETE | self::OP_GET | self::OP_UPDATE)) {
            if (!isset($this->id)) {
                $errors[] = __('Entry ID required.');
            }
        }
        if ($opFlags & (self::OP_DELETE | self::OP_UPDATE)) {
            if (!isset($this->revision)) {
                $errors[] = __('Entry revision code required for update.');
            }
        }
        // Validate that the transaction is structured correctly.
        if (count($this->details) !== 0) {
            $debitCount = 0;
            $creditCount = 0;
            foreach ($this->details as $detail) {
                try {
                    $detail->validate($opFlags);
                    if ($detail->signTest > 0) {
                        ++$debitCount;
                    } else {
                        ++$creditCount;
                    }
                } catch (Breaker $exception) {
                    Merge::arrays($errors, $exception->getErrors());
                }
            }
            if ($creditCount === 0 || $debitCount === 0) {
                $errors[] = __(
                    'Entry must have at least one debit and credit'
                );
            }
            if ($creditCount > 1 && $debitCount > 1) {
                $errors[] = __(
                    "Entry can't have multiple debits and multiple credits"
                );

            }
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $this;
    }

}
