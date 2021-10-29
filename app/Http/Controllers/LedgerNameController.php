<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LedgerDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use stdClass;

class LedgerNameController extends Controller
{
    public static function parseRequest(array $data, bool $inherit = false): array
    {
        if ($inherit) {
            $data['language'] ??= App::getLocale();
        }
        if (($data['name'] ?? false) && ($data['language'] ?? false)) {
            $result = [
                'name' => $data['name'],
                'language' => $data['language'],
            ];
            return [true, $result];
        }

        return [false, ["must include name and language properties"]];
    }

    public static function parseRequestList(
        array $data, bool $inherit = false, int $minimum = 0
    ): array
    {
        $names = [];
        foreach ($data as $nameData) {
            [$status, $name] = self::parseRequest($nameData, $inherit);
            if (!$status) {
                return [false, $name];
            }
            $names[$name['language']] = $name;
        }
        if (count($names) < $minimum) {
            $entry = $minimum === 1 ? 'entry' : 'entries';
            return [false, ["must provide at least $minimum name $entry"]];
        }
        return [true, $names];
    }

}
