<?php

namespace Abivia\Ledger\Models;

use Abivia\Hydration\HydrationException;
use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Abivia\Ledger\Helpers\Revision;
use Abivia\Ledger\Messages\Account;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Root\Flex;
use Abivia\Ledger\Root\Rules\LedgerRules;
use Abivia\Ledger\Traits\HasRevisions;
use Abivia\Ledger\Traits\UuidPrimaryKey;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property-read  string $parentCode The parent account code (or null if this is the root).
 * @property string $parentUuid The parent account (or null if this is the root).
 * @property Carbon $revision Revision timestamp to detect race condition on update.
 * @property string $taxCode An account code for tax purposes.
 * @property Carbon $updated_at When the record was updated.
 * @mixin Builder
 * @mixin \Illuminate\Database\Query\Builder
 */
class LedgerAccount extends Model
{
    use HasFactory, HasRevisions, UuidPrimaryKey;

    const CODE_SIZE = 32;
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
     * @var LedgerRules Temporary rules instance while creating a ledger
     */
    private static LedgerRules $bootRules;

    /**
     * @var string[] Casts for table columns
     */
    protected $casts = [
        'category' => 'boolean',
        'closed' => 'boolean',
        'credit' => 'boolean',
        'debit' => 'boolean',
        'revision' => 'datetime',
    ];

    /**
     * @var string Override timestamps to get microseconds.
     */
    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $fillable = [
        'category', 'code', 'credit', 'debit', 'extra', 'parentUuid', 'taxCode'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * @var array|string[] Attributes that can be copied directly into a message
     */
    protected static array $inMessage = [
        'category', 'closed', 'code', 'credit', 'debit', 'extra', 'taxCode'
    ];

    protected $primaryKey = 'ledgerUuid';

    private static ?LedgerAccount $root = null;

    public function __get($key)
    {
        if ($key === 'parentCode') {
            if ($this->parentUuid === null) {
                return null;
            }
            $parent = LedgerAccount::find($this->parentUuid);
            return $parent->code ?? null;
        }
        return parent::__get($key);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LedgerBalance::class, 'ledgerUuid', 'ledgerUuid');
    }

    private static function baseRuleSet()
    {
        self::$bootRules = new LedgerRules();
    }

    public static function createFromMessage(Account $message): self
    {
        $instance = new static();
        foreach ($instance->fillable as $property) {
            if (isset($message->{$property})) {
                $instance->{$property} = $message->{$property};
            }
        }
        if (isset($message->parent)) {
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
        if (isset($entityRef->uuid)) {
            $finder = self::where('ledgerUuid', $entityRef->uuid);
        } elseif (isset($entityRef->code)) {
            $finder = self::where('code', $entityRef->code);
        } else {
            throw new Exception(__('Account reference must have either code or uuid entries'));
        }

        return $finder;
    }

    /** @noinspection PhpUnused */
    /**
     * @throws HydrationException
     */
    public function getFlexAttribute($value): Flex
    {
        $flex = new Flex();
        $flex->hydrate($value);
        return $flex;
    }

    /**
     * Get a list of this account ID and all subaccount IDs.
     *
     * @return string[]
     */
    public function getSubAccountList(): array
    {
        $idList = [$this->ledgerUuid];
        $subAccounts = LedgerAccount::where('parentUuid', $this->ledgerUuid)->get();
        /** @var LedgerAccount $account */
        foreach ($subAccounts as $account) {
            $idList = array_merge($idList, $account->getSubAccountList());
        }

        return $idList;
    }

    public function matchesEntity(EntityRef $ref): bool
    {
        $match = true;
        if (isset($ref->uuid) && $ref->uuid !== $this->ledgerUuid) {
            $match = false;
        }
        if ($ref->code !== $this->code) {
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
                        "Parent :parent does not exist but is used in :code",
                        ['parent' => $next, 'code' => $current]
                    ));
                }
            }
            if ($lookFor !== null && $ledgerAccount->matchesEntity($lookFor)) {
                throw Breaker::withCode(
                    Breaker::RULE_VIOLATION,
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
            unset($next->code);
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
     * @param bool $bootable
     * @return LedgerRules|null
     */
    public static function rules(bool $bootable = false): ?LedgerRules
    {
        if (self::$root === null) {
            self::loadRoot();
            if (self::$root === null) {
                if (!$bootable) {
                    return null;
                }
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
        if ($value === null) {
            $this->attributes['flex'] = null;
        } else {
            $this->attributes['flex'] = json_encode($value);
        }
    }

    /**
     * Merge data into the rule set.
     * @return LedgerRules
     */
    public static function resetRules(): LedgerRules
    {
        self::$root = null;
        self::loadRoot();
        if (self::$root === null) {
            self::baseRuleSet();
            return self::$bootRules;
        }

        return self::$root->flex->rules;
    }

    /**
     * Merge data into the rule set.
     * @param stdClass $data
     * @return LedgerRules
     */
    public static function setRules(stdClass $data): LedgerRules
    {
        if (self::$root === null) {
            self::loadRoot();
            if (self::$root === null) {
                if (!isset(self::$bootRules)) {
                    self::baseRuleSet();
                }
                Merge::objects(self::$bootRules, $data);
                return self::$bootRules;
            }
        }
        $flex = self::$root->flex;
        Merge::objects($flex->rules, $data);
        self::$root->flex = $flex;
        self::$root->save();

        return self::$root->flex->rules;
    }

    public static function systemDateFormat(): string {
        $dummy = new self();
        return $dummy->getDateFormat();
    }

    public function toMessage(): Account
    {
        $message = new Account();
        $message->uuid = $this->ledgerUuid;
        foreach (self::$inMessage as $property) {
            if ($this->{$property} !== null) {
                $message->{$property} = $this->{$property};
            }
        }
        if ($this->parentUuid !== null) {
            $message->parent = new EntityRef();
            $message->parent->uuid = $this->parentUuid;
        }
        $message->revision = Revision::create($this->revision, $this->updated_at);
        foreach ($this->names as $name) {
            $message->names[] = $name->toMessage();
        }

        return $message;
    }

    /**
     * @param array $options
     * @return array
     * @throws Exception
     */
    public function toResponse(array $options = []): array
    {
        $response = ['uuid' => $this->ledgerUuid];
        if ($this->code !== '') {
            $response['code'] = $this->code;
        }
        if ($this->taxCode !== '') {
            $response['taxCode'] = $this->taxCode;
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
     * @param Builder|null $query
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
        } elseif (isset($entityRef->uuid)) {
            if ($operator === '=') {
                // With an equal operator, the UUID can be used directly.
                $finder = $query->where('ledgerUuid', $operator, $entityRef->uuid);
            } else {
                // Other operators need the account code, so get that first.
                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
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
