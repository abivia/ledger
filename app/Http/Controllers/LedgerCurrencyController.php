<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LedgerDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use stdClass;

class LedgerCurrencyController extends Controller
{
    /**
     * Validate request data to define a currency.
     *
     * @param array $data Request data
     * @return array [bool, array] On success the boolean is true and the array contains
     * valid currency data. On failure, the boolean is false and the array is a list of
     * error messages.
     */
    public static function parseRequest(array $data): array
    {
        $errors = [];
        $status = true;

        if (!($data['code'] ?? false)) {
            $errors[] = 'the code property is required';
            $status = false;
        }
        if (!($data['decimals'] ?? false) || !is_numeric($data['decimals'])) {
            $errors[] = 'an integer decimals property is required';
            $status = false;
        }
        if ($status) {
            return [
                true,
                [
                    'code' => strtoupper($data['code']),
                    'decimals' => (int) $data['decimals']
                ]
            ];
        }

        return [false, $errors];
    }

}
