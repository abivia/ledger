<?php

namespace Abivia\Ledger\Messages;

trait HasCodes
{
    /**
     * @var string A unique identifier.
     */
    public string $code;

    /**
     * @var string A new code to be assigned in an update operation.
     */
    public string $toCode;

    /**
     * Check that the code and toCode properties are good.
     *
     * @param int $opFlags Message operations flag for this request.
     * @param array $options regEx overrides the default regEx, uppercase transforms
     * code and toCode to uppercase (default true).
     * @return array
     */
    protected function validateCodes(
        int $opFlags, array $options = []
    ): array
    {
        $options['uppercase'] ??= true;
        if (isset($options['regEx']) && $options['regEx'] !== '') {
            $regExMessage = 'the :prop property must match the format :regex';
        } else {
            $options['regEx'] = '/^[^ \t\r\n*]*$/';
            $regExMessage = 'the :prop property cannot contain whitespace or *';
        }
        $errors = [];
        if (isset($this->code)) {
            if (preg_match($options['regEx'], $this->code)) {
                if ($options['uppercase']) {
                    $this->code = strtoupper($this->code);
                }
            } else {
                $errors[] = __(
                    $regExMessage, ['prop' => 'code', 'regex' => $options['regEx']]
                );
            }
        } elseif (($opFlags & (Message::OP_ADD | Message::OP_CREATE))) {
            $errors[] = __('the code property is required');
        }
        if (($opFlags & Message::OP_UPDATE) && isset($this->toCode)) {
            if (preg_match($options['regEx'], $this->toCode)) {
                if ($options['uppercase']) {
                    $this->toCode = strtoupper($this->toCode);
                }
            } else {
                $errors[] = __(
                    $regExMessage, ['prop' => 'toCode', 'regex' => $options['regEx']]
                );
            }
        }

        return $errors;
    }

}
