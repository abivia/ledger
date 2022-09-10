<?php

namespace Abivia\Ledger\Tests;

use Opis\JsonSchema\Validator;

trait ValidatesJson
{
    static protected ?Validator $validator = null;

    public function validateResponse($response, string $with)
    {
        // GitHub can't find the schemas so skip this
        if (file_exists(__DIR__ . '/.skipschemachecks')) {
            return;
        }
        if (self::$validator === null) {
            self::$validator = new Validator();
            if (file_exists(__DIR__ . '/.schemapath')) {
                self::$validator->resolver()->registerPrefix(
                    'https://ledger.abivia.com/api/json/',
                    trim(file_get_contents(__DIR__ . '/.schemapath'))
                );
            }
        }
        $schemaResult = self::$validator->validate(
            $response,
            "https://ledger.abivia.com/api/json/$with.schema.json"
        );
        $valid = $schemaResult->isValid();
        $message = $valid ? '' : $schemaResult->error()->message();
        $this->assertTrue($valid, $message);
    }

}
