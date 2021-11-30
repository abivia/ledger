<?php

namespace App\Models;

use App\Exceptions\Breaker;
use App\Helpers\Merge;
use App\Helpers\Revision;
use App\Models\Messages\Ledger\Account;
use App\Models\Messages\Ledger\EntityRef;
use App\Traits\HasRevisions;
use App\Traits\UuidPrimaryKey;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;
use stdClass;

/**
 * Ledger Account Definition
 *
 * @property bool $category Set if this is a category account (no transactions/balances).
 * @property bool $closed Set if this account is closed to further transactions.
 * @property string $code The application code (chart of accounts code) for this account.
 * @property Carbon $created_at When the record was created.
 * @property bool $credit Set when this is a credit-side account.
 * @property bool $debit Set when this is a debit-side account.
 * @property object $extra Additional application level information.
 * @property object $flex JSON-formatted internal information (via accessor).
 * @property string $ledgerUuid UUID primary key.
 * @property LedgerName[] $names Associated names.
 * @property string $parentUuid The parent account (or null if this is the root).
 * @property Carbon $revision Revision timestamp to detect race condition on update.
 * @property Carbon $updated_at When the record was updated.
 * @mixin Builder
 */
class LedgerAccount extends Model
{
    use HasFactory, HasRevisions, UuidPrimaryKey;

    /**
     * @var array Model default attributes.
     */
    protected $attributes = [
        'category' => false,
        'code' => '',
        'closed' => false,
        'credit' => false,
        'debit' => false,
        'extra' => null,
        'parentUuid' => null,
    ];

    /**
     * @var stdClass Temporary rules instance while creating a ledger
     */
    private static stdClass $bootRules;

    /**
     * @var string[] Casts for table columns
     */
    protected $casts = [
        'category' => 'boolean',
        'closed' => 'boolean',
        'credit' => 'boolean',
        'debit' => 'boolean',
        'revision' => 'timestamp',
    ];

    /**
     * @var string Override timestamps to get microseconds.
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $fillable = [
        'category', 'code', 'credit', 'debit', 'extra', 'parentUuid'
    ];

    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'ledgerUuid';

    private static ?LedgerAccount $root = null;

    public function balances(): HasMany
    {
        return $this->hasMany(LedgerBalance::class, 'ledgerUuid', 'ledgerUuid');
    }

    private static function baseRuleSet()
    {
        self::$bootRules = new stdClass();

        self::$bootRules->account = new stdClass();
        self::$bootRules->domain = new stdClass();
        // Default is to leave transactions as not reviewed
        self::$bootRules->entry = new stdClass();
        self::$bootRules->entry->reviewed = false;
        self::$bootRules->language = new stdClass();
        self::$bootRules->language->default = App::getLocale();
        self::$bootRules->openDate = Carbon::now()->format(self::systemDateFormat());
        self::$bootRules->pageSize = 100;
    }

    public static function createFromMessage(Account $message): self
    {
        $instance = new static();
        foreach ($instance->fillable as $property) {
            if (isset($message->{$property})) {
                $instance->{$property} = $message->{$property};
            }
        }
        if ($message->parent !== null) {
            $instance->parentUuid = $message->parent->uuid;
        }
        $instance->save();
        $instance->refresh();

        return $instance;
    }

    public function entityRef(): EntityRef
    {
        return new EntityRef($this->code, $this->ledgerUuid);
    }

    /**
     * @param EntityRef $entityRef
     * @return Builder
     * @throws Exception
     * @noinspection PhpIncompatibleReturnTypeInspection
     * @noinspection PhpDynamicAsStaticMethodCallInspection
     */
    public static function findWith(EntityRef $entityRef): Builder
    {
        if (isset($entityRef->uuid) && $entityRef->uuid !== null) {
            $finder = self::where('ledgerUuid', $entityRef->uuid);
        } elseif (isset($entityRef->code)) {
            $finder = self::where('code', $entityRef->code);
        } else {
            throw new Exception(__('Account reference must have either code or uuid entries'));
        }

        return $finder;
    }

    /** @noinspection PhpUnused */
    public function getFlexAttribute($value)
    {
        return json_decode($value);
    }

    public function matchesEntity(EntityRef $ref): bool
    {
        $match = true;
        if ($ref->uuid !== null && $ref->uuid !== $this->ledgerUuid) {
            $match = false;
        }
        if ($ref->code !== null && $ref->code !== $this->code) {
            $match = false;
        }
        return $match;
    }

    public static function loadRoot()
    {
        self::$root = LedgerAccount::with('names')
            ->where('code', '')
            ->first();
    }

    public function names(): HasMany
    {
        return $this->hasMany(LedgerName::class, 'ownerUuid', 'ledgerUuid');
    }

    /**
     * Get the path to the root for an account, optionally checking for an external
     * circular reference.
     *
     * @param EntityRef $start
     * @param ?EntityRef $lookFor
     * @return LedgerAccount[] Account parents from $start up to the root.
     * @throws Breaker If a circular reference is found.
     * @throws Exception
     */
    public static function parentPath(EntityRef $start, ?EntityRef $lookFor): array
    {
        $next = clone $start;
        $current = null;
        $parents = [];
        $parentsByUuid = [];
        while (true) {
            // Fetch the parent
            /** @var LedgerAccount $ledgerAccount */
            $ledgerAccount = self::findWith($next)->first();
            if ($ledgerAccount === null) {
                if ($current === null) {
                    throw Breaker::withCode(
                        Breaker::BAD_ACCOUNT,
                        [__("Parent :parent not found."), ['parent' => $next]]
                    );
                } else {
                    throw new Exception(__(
                        "Parent :parent does not exist but is used in :account",
                        ['parent' => $next, 'account' => $current]
                    ));
                }
            }
            if ($lookFor !== null && $ledgerAccount->matchesEntity($lookFor)) {
                throw Breaker::withCode(
                    Breaker::INVALID_OPERATION,
                    [__(
                        "Adding :start to :ref would cause a circular reference.",
                        ['start' =>$start, 'ref' => $lookFor]
                    )]
                );
            }
            if (isset($parentsByUuid[$ledgerAccount->ledgerUuid])) {
                throw new Exception(__(
                    "Account :account is part of a closed parent reference loop.",
                    ['account' => $current]
                ));
            }
            $parentsByUuid[$ledgerAccount->ledgerUuid] = true;
            $parents[] = $ledgerAccount;
            $current = $ledgerAccount;
            if ($ledgerAccount->parentUuid === null) {
                break;
            }
            $next->code = null;
            $next->uuid = $ledgerAccount->parentUuid;
        }

        return $parents;
    }

    /**
     * Get the ledger root singleton, loading it if required.
     *
     * @throws Exception If there is no root.
     */
    public static function root(): LedgerAccount
    {
        if (self::$root === null) {
            self::loadRoot();
            if (self::$root === null) {
                throw new Exception(__('Ledger root is not defined.'));
            }
        }

        return self::$root;
    }

    /**
     * Get the current rule set. During ledger creation, this is a set of bootstrap rules.
     *
     * @return stdClass
     */
    public static function rules(): stdClass
    {
        if (self::$root === null) {
            self::loadRoot();
            if (self::$root === null) {
                if (!isset(self::$bootRules)) {
                    self::baseRuleSet();
                }
                return self::$bootRules;
            }
        }
        return self::$root->flex->rules;
    }

    /**
     * @return LedgerAccount|null
     */
    public static function saveRoot(): ?LedgerAccount
    {
        if (self::$root !== null) {
            self::$root->save();
        }

        return self::$root;
    }

    /** @noinspection PhpUnused */
    public function setFlexAttribute($value)
    {
        $this->attributes['flex'] = json_encode($value);
    }

    /**
     * Merge data into the rule set.
     * @return stdClass
     */
    public static function resetRules()
    {
        self::$root = null;
        self::loadRoot();
        if (self::$root === null) {
            if (!isset(self::$bootRules)) {
                self::baseRuleSet();
            }
            return self::$bootRules;
        }

        return self::$root->flex->rules;
    }

    /**
     * Merge data into the rule set.
     * @param array $data
     * @return stdClass
     */
    public static function setRules(array $data)
    {
        if (self::$root === null) {
            self::loadRoot();
            if (self::$root === null) {
                if (!isset(self::$bootRules)) {
                    self::baseRuleSet();
                }
                Merge::arrayToObject(self::$bootRules, $data);
                return self::$bootRules;
            }
        }
        Merge::arrayToObject(self::$root->flex->rules, $data);
        self::$root->save();

        return self::$root->flex->rules;
    }

    public static function systemDateFormat(): string {
        $dummy = new self();
        return $dummy->getDateFormat();
    }

    /**
     * @param array $options
     * @return array
     */
    public function toResponse(array $options = []): array
    {
        $response = ['uuid' => $this->ledgerUuid];
        if ($this->code !== '') {
            $response['code'] = $this->code;
        }
        if ($this->parentUuid !== null) {
            $response['parentUuid'] = $this->parentUuid;
        }
        $response['names'] = [];
        foreach ($this->names as $name) {
            $response['names'][] = $name->toResponse();
        }
        $response['category'] = $this->category;
        $response['debit'] = $this->debit;
        $response['credit'] = $this->credit;
        $response['closed'] = $this->closed;
        if ($this->extra !== null) {
            $response['extra'] = $this->extra;
        }
        $response['revision'] = Revision::create($this->revision, $this->updated_at);
        $response['createdAt'] = $this->created_at;
        $response['updatedAt'] = $this->updated_at;

        return $response;
    }

    /**
     * @param string $operator
     * @param EntityRef $entityRef
     * @param Builder $query
     * @return Builder
     * @throws Exception
     */
    public static function whereEntity(
        string $operator, EntityRef $entityRef, ?Builder $query = null
    ): Builder
    {
        if ($query === null) {
            $query = self::query();
        }
        if (isset($entityRef->code)) {
            // We have a code so simple case, just use it
            $finder = $query->where('code', $operator, $entityRef->code);
        } elseif (isset($entityRef->uuid) && $entityRef->uuid !== null) {
            if ($operator === '=') {
                // With an equal operator, the UUID can be used directly.
                $finder = $query->where('ledgerUuid', $operator, $entityRef->uuid);
            } else {
                // Other operators need the account code, so get that first.
                $ref = self::find($entityRef->uuid);
                if ($ref === null) {
                    throw new Exception(
                        __('Account :uuid not found.', ['uuid' => $entityRef->uuid])
                    );
                }
                $finder = $query->where('code', $operator, $ref->code);
            }
        } else {
            throw new Exception(__('Account reference must have either code or uuid entries'));
        }

        return $finder;
    }

}
