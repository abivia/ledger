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
 
