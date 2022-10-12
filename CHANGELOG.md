## 1.7.2

- A query in the trial balance report failed to add a table prefix. Thanks, @alexgoogole!

## 1.7.1

### Bugs:
- Incorrect field in ReportController::getCached(). Thanks @alexgoogole!

## 1.7.0

### Changes:
- Added clearing transactions.
- Started validating results against our JSON schemas.
- Significant cleanup and restructuring of JSON schemas.
- Consolidated migrations for a new installation.

### Bugs:
- Removed redundant messages when returning an error from several API requests

## 1.6.4

### Bugs:
- Audit log now just includes the User ID (if any), not the whole user object.

## 1.6.3

### Bugs:
- Fix rounding error in journal entry.

### Changes:
- Improve error message when account not found in journal entry.

## 1.6.2

### Bugs:
- Fix accidental removal of PHP 8.0 from composer.json

## 1.6.1

### Bugs:
- JournalReference::createFromMessage wasn't looking up the domain.

### Changes:

- Message creation now allows a reference as a string UUID (Detail and Entry messages)
- Add account property to JournalDetail model to retrieve the parent account.
- Add entry property to JournalDetail model to retrieve the parent Entry.

## 1.6.0

### Bugs:

- Abivia\Ledger\Messages\Detail::validate set debit/credit to '' instead of using unset().

### Changes:

- Abivia\Ledger\Messages\Detail::normalizeAmount now takes a null argument
- Added CI for running tests.

## 1.5.1

### Bugs:

- Publishing an updated version was creating redundant migrations.

## 1.5.0

### Changes:

- Added a `locked` flag to Journal Entries and a lock (/unlock) operation to the
journal entry API. Delete and update transactions will fail when an entry is locked.


## 1.4.0

### Changes:

- Moving logic that's useful to applications outside the API into separate classes
under the Logic namespace.
- Templates can now set the account codeFormat rule. Ledger create requests can override the
template.
- Added LedgerAccount->parentCode which will fetch the parent's account code (or null if no parent)
- LedgerAccount and Name models have a new toMessage() method.
- Made RootController::listTemplates() static.
- Made the default domain name a constant in Messages\Create
- Added constructors to Messages\Currency, Messages\Name
- Added Messages\Account::inheritFlagsFrom()
- Made ReportAccount::$flex protected so that it is invisible to the API

### Bugs:

- Duplicate error messages were being returned.
- The toCode attribute of an account update message was being ignored.
- Invalid API root operations were being treated as create.
- Debit/credit flags were not being inherited for accounts defined in a Create message.
- Names were not being returned on sub-journal requests.

## 1.3.0

### Changes:

- `fromArray()` method of message classes given more meaningful defaults for `$opFlags` argument
  (typically OP_ADD).
- If no flags are defined in an entry in `Message::$copyable` the default is now `Message::ALL_OPS`.
  
### Bugs:

- Tests were breaking with new migration class names.
- Fix accounting error in GettingStartedTest.
- Sort accounts by code on trial balance report.

## 1.2.6

- Bugfix: fix migration file/class naming #2.

## 1.2.5

- Bugfix: fix migration file/class naming.

## 1.2.4

- Bugfix: adjust migration timestamps to ensure they run in the right order.

## 1.2.3

- Bugfix: add sequence number to migrations to ensure they run in the right order.

## 1.2.2

- Bugfix: short form parent account references (code only) not handled correctly.

## 1.2.1

- Fix regression: custom ledger rules were being dropped.

## 1.2.0

- Templates and ledger create operations can now define "sections" for reporting purposes.
- Accounts now have a `taxCode` attribute to simplify mapping for tax reporting.
- Created a set of Rules classes for storing Ledger rules.
- Switched from referencing migrations to publishing them.
- Bug fixes.

## 1.1.1

- Delete operations for Account, Currency, Domain, SubJournal weren't verifying revision hash.

## 1.1.0

- Implement Balance retrieval via JSON API.
- Bugfix: ranges on account query were ignored.
- Minor bug fixes.

## 1.0.0
- First production release
 
