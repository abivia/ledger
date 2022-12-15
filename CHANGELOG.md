## 1.10.0

### Bugs:
- Fixed table references in TrialBalanceReport (again)
- Ledger was forcing a name to be present in the default language. Now a named 
entity must have at least one name in any language.

### Changes:
- Added a description property to entry queries, allowing wildcard matches on the description.
- Changed the error code on unknown operations from Rule Violation to Bad Request.
- Added query operations for currencies, domains, and sub-journals.
- Expanded query selection capabilities by adding `codes` and `names` properties where relevant.
- Updated JSON schemas and documentation.

## 1.9.0

### Bugs
- [Issue 10](https://github.com/abivia/ledger/issues/10) exposed several cases where performing operations
before the ledger was created resulted in confusing responses instead of an error.
- [Issue 12](https://github.com/abivia/ledger/issues/12) The sub-journal was bing validated byt not stored
in the journal entry.

### Changes
- ControllerResultHandler::unexpectedException() returns a generic error string suitable for
returning to an API call, instead of void.
- LedgerAccount::rules() has a new `required` argument that will throw a Breaker instead of
returning null. It defaults to true.

## 1.8.3

### Bugs:
- It was possible to give two ledger accounts the same name in the same language.
- It was possible to give two ledger domains the same name in the same language.

### Changes:
- Updated tests to catch the duplicate naming problem.

## 1.8.2

### Bugs:
- Description arguments in Entry messages were not correctly handled.
- Running a trial balance report on an empty journal generated an error. Thanks to @RoNDz for
finding this (Issue #9).

### Changes:
- New test case for Issue 9.

## 1.8.1

### Changes:
- Added new LedgerAccountTest::testCreateNoRules to catch missing pageSize
and missing ledger rules errors.

### Bugs:
- If no default page size was set, account/query would return an error (thanks @lewrw!).
- If no rules were specified on ledger creation, an error resulted.
 
## 1.8.0

### Changes:
- Ability to add or limit reports via config (thanks @ivanmazep!).
- Implementation of report options on API requests. Trial balance report can now limit how
many levels of subaccount detail are returned; application can limit max depth.
- Converted some exceptions to throw a Breaker instead. Added CONFIG_ERROR
code, thrown when the report configuration is invalid.
- Breaker::withCode now accepts a string for the error argument in addition to an array.
- The trial balance report now returns the domain with the name localized.
- API requests now decode the JSON directly, avoiding middleware.
- Added the decimal, negative, and thousands options to the trial balance report
- Removed the unused `opFlags` argument from ReportAccount::fromArray() and deprecated
the equally unused ReportAccount::validate().

## 1.7.2

### Bugs:
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
 
