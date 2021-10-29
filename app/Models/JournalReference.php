<?php

namespace App\Models;

use App\Traits\UuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Link to an external account entity (customer, vendor, etc.).
 *
 * @property string $extra Application specific information.
 * @property string $journalReferenceUuid UUID primary key.
 * @property string $reference External identifier.
 */
class JournalReference extends Model
{
    use HasFactory, UuidPrimaryKey;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'journalReferenceUUid';

}
