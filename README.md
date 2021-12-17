# General Ledger and Journal Package for Laravel

Ledger is a full-featured implementation of the core of any good accounting system, a double-entry
journal and general ledger. It is not a complete accounting solution, but rather the minimum for
keeping track of financial transactions.

That's the only minimal part. Ledger features:

- Full double-entry accounting system with audit trail capability.
- Multi-currency support.
- Support for multiple business units.
- Sub-journal support.
- Multilingual.
- Integrates via direct controller access or through JSON API.
- Atomic transactions with concurrent update blocking.
- Reference system supports soft linking to other ERP components.
- Designed for Laravel from the ground up.

## Documentation

Full documentation is available [here](https://ledger.abivia.com/).

## Quick start

### Installation and Configuration

Install Ledger with composer:

`composer install abivia/ledger`

Create database tables

`php artisan migrate`

### Configuration

The configuration file is installed as `config\ledger.php`. You can enable/disable the 
JSON API, set middleware, and a path prefix to the API.
