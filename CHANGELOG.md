# Ledger

## Create a new Ledger

`/api/v1/ledger/create`

```json
{
    "domains": [
        {
            "code": "domain_identifier",
            "currency": "CAD",
            "names": [
                {
                    "name": "My New Domain",
                    "language": "xx-xx"
                }
            ],
            "subJournals": false
        }
    ],
    "journals": [
        {
            "code": "journal_identifier",
            "names": [
                {
                    "name": "My SubJournal",
                    "language": "xx-xx"
                }
            ]
        }
    ],
    "currencies": [
        {
            "code": "currency_code",
            "decimals": "{number}"
        }
    ],
    "names": [
        {
            "name": "My New Ledger",
            "language": "xx-xx (if missing, system default)"
        }
    ],
    "rules": {
        "account": {
            "codeFormat": "regex (default /[a-z0-9]+/i)"   
        },
        "domain": {
            "default": "string"
        }
    },
    "extra": "arbitrary application data",
    "accounts": [
        "Optional. One time CoA initialization to create accounts/categories with balances."
    ],
    "template": "preset_name"
}
```
Returns
time is always present.
If the errors attribute is present, the remaining attributes are not.
```json
{
    "time": "ISO time format",
    "errors": [
        {
            "text": "some informative error message",
            "arguments": []
        }
    ],
    "ledger": {
        "uuid": "root account UUID",
        "revision": "hashed time with microseconds",
        "createdAt": "ISO Time",
        "updatedAt": "ISO Time"
    }
}
```

## Add a ledger account

`api/v1/ledger/account/add`

```json
{
    "names": [
        {
            "name": "New Account Name translation",
            "language": "xx-xx"
        }
    ],
    "code": "unique account code that passes ledger.rules.account.codeFormat regex",
    "parent": {
        "_note": "Only one of code or UUID required",
        "code": "Parent account code",
        "uuid": "Parent account UUID"
    },
    "debit": "{boolean}, required if no parent provided",
    "credit": "{boolean}, required if no parent provided",
    "category": "{boolean} parent must be root or another category.",
    "extra": "arbitrary application data"
}
```
Returns
Either the errors or account attribute will be present, not both.
```json
{
    "time": "ISO time format",
    "errors": [
        {
            "message": "some informative error message"
        }
    ],
    "account": {
        "uuid": "root account UUID",
        "code": "account code",
        "names": [
            {
                "name": "text",
                "language": "xx-xx",
                "createdAt": "ISO Time",
                "updatedAt": "ISO Time"
            }
        ],
        "extra": "arbitrary application data",
        "revision": "hash",
        "createdAt": "ISO Time",
        "updatedAt": "ISO Time"
    }
}
```


`api/v1/ledger/account/get`

```json
{
    "uuid": "account UUID",
    "code": "unique account code that passes ledger.rules.account.codeFormat regex"
}
```
Returns
Either the errors or account attribute will be present, not both.
```json
{
    "time": "ISO time format",
    "errors": [
        {
            "message": "some informative error message"
        }
    ],
    "account": {
        "uuid": "root account UUID",
        "code": "account code",
        "names": [
            {
                "name": "text",
                "language": "xx-xx",
                "createdAt": "ISO Time",
                "updatedAt": "ISO Time"
            }
        ],
        "extra": "arbitrary application data",
        "revision": "hash",
        "createdAt": "ISO Time",
        "updatedAt": "ISO Time"
    }
}
```

`api/v1/ledger/account/delete`
```json
{
    "code": "currency code"
}
```
Returns
Either the errors or accounts attribute will be present, not both.
```json
{
    "time": "ISO time format",
    "errors": [
        {
            "message": "some informative error message"
        }
    ],
    "accounts": ["ledgerUuid1", "ledgerUuid2", "..."]
}
```

`api/v1/ledger/account/update`

```json
{
    "revision": "hash code",
    "uuid": "account UUID",
    "code": "account code",
    "names": [
        {
            "name": "New Account Name translation",
            "language": "xx-xx"
        }
    ],
    "parent": {
        "_note": "Only one of code or UUID required",
        "code": "Parent account code",
        "uuid": "Parent account UUID"
    },
    "debit": "{boolean}, required if no parent provided",
    "credit": "{boolean}, required if no parent provided",
    "category": "{boolean} parent must be root or another category.",
    "closed": "boolean",
    "extra": "arbitrary application data"
}
```
Returns
Either the errors or account attribute will be present, not both.

multiple account queries...

## Currencies
`api/v1//ledger/currency/add`

```json
{
    "code": "currency_code",
    "decimals": "{number}"
}
```
Returns
Either the errors or account attribute will be present, not both.
```json
{
    "time": "ISO time format",
    "errors": [
        {
            "message": "some informative error message"
        }
    ],
    "currency": {
        "code": "currency code",
        "decimals": "{number}",
        "revision": "hash",
        "createdAt": "ISO Time",
        "updatedAt": "ISO Time"
    }
}
```

`api/v1//ledger/currency/delete`

```json
{
    "code": "currency_code"
}
```
Returns
Either the errors or account attribute will be present, not both.
```json
{
    "time": "ISO time format",
    "errors": [
        {
            "message": "some informative error message"
        }
    ],
    "currency": {
        "code": "currency code"
    }
}
```

`api/v1//ledger/currency/get`

```json
{
    "code": "currency_code"
}
```
Returns
Either the errors or account attribute will be present, not both.
```json
{
    "time": "ISO time format",
    "errors": [
        {
            "message": "some informative error message"
        }
    ],
    "currency": {
        "code": "currency code",
        "decimals": "{number}",
        "revision": "hash",
        "createdAt": "ISO Time",
        "updatedAt": "ISO Time"
    }
}
```

api/v1//ledger/currency/update

```json
{
    "revision": "hash code",
    "code": "account code",
    "toCode": "new currency code",
    "decimals": "{number}"
}
```
Returns
Either the errors or account attribute will be present, not both.
```json
{
    "time": "ISO time format",
    "errors": [
        {
            "message": "some informative error message"
        }
    ],
    "currency": {
        "code": "currency code",
        "decimals": "{number}",
        "revision": "hash",
        "createdAt": "ISO Time",
        "updatedAt": "ISO Time"
    }
}
```

## Domains
api/v1//ledger/domain/add
api/v1//ledger/domain/delete
api/v1//ledger/domain/get
api/v1//ledger/domain/update

## Journal references

api/v1//journal/reference/add
api/v1//journal/reference/delete
api/v1//journal/reference/get
api/v1//journal/reference/update

## Journal entries

api/v1//journal/entry/add
api/v1//journal/entry/reverse
api/v1//journal/entry/get
api/v1//journal/entry/update

## Journal entry queries (report section?)
