<?php
declare(strict_types=1);

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Abivia\Ledger\Http\Controllers\JournalEntryController;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Messages\Message;
use Carbon\Carbon;

class Entry extends Message
{
    /**
     * @var string[] Arguments to be passed to a string translation function.
     */
    public array $arguments = [];

    /**
     * @var bool This is explicitly a clearing transaction (multiple debit/credits).
     */
    public bool $clearing = false;

    protected static array $copyable = [
        ['clearing', self::OP_ADD | self::OP_UPDATE],
        ['currency', self::OP_ADD],
        ['description', self::OP_ADD | self::OP_UPDATE],
        [['arguments', 'descriptionArgs'], self::OP_ADD | self::OP_UPDATE],
        //['domain', self::OP_ADD],
        ['extra', self::OP_ADD | self::OP_UPDATE],
        ['id', self::OP_DELETE | self::OP_GET | self::OP_LOCK | self::OP_UPDATE],
        //['journal', self::OP_ADD],
        ['language', self::OP_UPDATE],
        ['lock', self::OP_LOCK],
        ['reviewed', self::OP_ADD | self::OP_UPDATE],
        ['revision', self::OP_DELETE | self::OP_LOCK | self::OP_UPDATE],
        //[['date', 'transDate'], self::OP_ADD | self::OP_UPDATE],
    ];

    /**
     * @var string Currency code. If not provided, the domain's default is used.
     */
    public string $currency;

    /**
     * @var string Transaction description.
     */
    public string $description;

    /**
     * @var Detail[] Transaction detail records.
     */
    public array $details = [];

    /**
     * @var EntityRef The domain this entry applies to.
     */
    public EntityRef $domain;

    /**
     * @var mixed An arbitrary string for use by the application.
     */
    public $extra;

    /**
     * @var int A unique identifier for this entry.
     */
    public int $id;

    /**
     * @var EntityRef The journal this entry applies to.
     */
    public EntityRef $journal;

    /**
     * @var string|null The language used for the supplied description.
     */
    public string $language;

    /**
     * @var bool Lock or unlock a journal entry.
     */
    public bool $lock;

    /**
     * @var Reference A link to an external entity.
     */
    public Reference $reference;

    /**
     * @var bool Reviewed flag. If absent, set to the ledger default.
     */
    public bool $reviewed = false;

    /**
     * @var string Revision signature.
     */
    public string $revision;

    /**
     * @var Carbon Transaction date.
     */
    public Carbon $transDate;

    /**
     * @inheritdoc
     */
    public static function fromArray(array $data, int $opFlags = self::OP_ADD): self
    {
        $entry = new static();
        $entry->copy($data, $opFlags);
        if ($opFlags & self::OP_ADD) {
            if (isset($data['domain'])) {
                $entry->domain = EntityRef::fromMixed($data['domain'], $opFlags);
            }
            if (isset($data['journal'])) {
                $entry->journal = new EntityRef();
                $entry->journal->code = $data['journal'];
            }
        }
        if ($opFlags & (self::OP_ADD | self::OP_UPDATE)) {
            if (isset($data['reference'])) {
                if (!is_array($data['reference'])) {
                    $data['reference'] = ['uuid' => $data['reference']];
                }
                $entry->reference = Reference::fromArray($data['reference'], $opFlags);
            }
            if (isset($data['transDate'])) {
                $entry->transDate = new Carbon($data['transDate']);
            } elseif (isset($data['date'])) {
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
     * @throws Breaker
     */
    public function run(): array {
        $controller = new JournalEntryController();
        $journalEntry = $controller->run($this);
        if ($this->opFlags & (Message::OP_DELETE)) {
            $response = ['success' => true];
        } else {
            $response = ['entry' => $journalEntry->toResponse($this->opFlags)];
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function validate(?int $opFlags = null): self
    {
        $opFlags ??= $this->getOpFlags();
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
            $this->requireRevision($errors);
        }
        if (isset($this->reference)) {
            $this->reference->validate($opFlags);
        }
        if ($opFlags & self::OP_LOCK) {
            // Locking operations only care about the lock flag.
            if (!isset($this->lock)) {
                $errors[] = __('Lock operation requires a lock flag.');
            }
        } else {
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
                if (!$this->clearing && $creditCount > 1 && $debitCount > 1) {
                    $errors[] = __(
                        "Entry can't have multiple debits and multiple credits"
                        . " unless it is a clearing transaction."
                    );
                }
            }
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $this;
    }

}
