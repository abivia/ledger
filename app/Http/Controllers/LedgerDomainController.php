<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LedgerDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use stdClass;

class LedgerDomainController extends Controller
{
    public static function parseRequest(array $data): array
    {
        $errors = [];
        $result = [];
        $status = true;
        if (($data['code'] ?? false)) {
            $result['code'] = $data['code'];
        } else {
            $errors[] = 'the code property is required';
            $status = false;
        }
        if (!($data['names'] ?? false)) {
            $errors[] = 'the names property is required';
            $status = false;
        }
        if (!$status) {
            return [false, $errors];
        }
        [$status, $names] = LedgerNameController::parseRequestList(
            $data['names'], false, 1
        );
        if (!$status) {
            return [false, $names];
        }
        $result['names'] = $names;
        $result['subJournals'] = $data['subJournals'] ?? false;
        if (isset($data['currency'])) {
            $result['currencyDefault'] = strtoupper($data['currency']);
        }
        return [true, $result];

    }
}
