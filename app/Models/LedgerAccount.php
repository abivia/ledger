<?php

namespace App\Models;

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

    private static function baseRuleSet()
    {
        self::$bootRules = new stdClass();

        self::$bootRules->domain = new stdClass();
        // Default is to leave transactions as not reviewed
        self::$bootRules->entry = new stdClass();
        self::$bootRules->entry->reviewed = false;
        self::$bootRules->language = new stdClass();
        self::$bootRules->language->default = App::getLocale();
    }

    public static function bootRules(array $data)
    {
        if (!isset(self::$bootRules)) {
            self::baseRuleSet();
        }
        Merge::objects(self::$bootRules, json_decode(json_encode($data)));
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
            throw new Exception('Account reference must have either code or uuid entries');
        }

        return $finder;
    }

    /** @noinspection PhpUnused */
    public function getFlexAttribute($value)
    {
        return json_decode($value);
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
     * Get the ledger root singleton, loading it if required.
     *
     * @throws Exception If there is no root.
     */
    public static function root(): LedgerAccount
    {
        if (self::$root === null) {
            self::loadRoot();
            if (self::$root === null) {
                throw new Exception('Ledger root is not defined.');
            }
        }

        return self::$root;
    }

    public static function rules()
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

    public static function saveRoot()
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

}
