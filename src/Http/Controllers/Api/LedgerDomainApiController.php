<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\LedgerDomainController;
use Abivia\Ledger\Messages\Domain;
use Abivia\Ledger\Messages\DomainQuery;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LedgerDomainApiController
{
    use ControllerResultHandler;

    /**
     * Perform a domain operation.
     *
     * @param Request $request
     * @param string $operation
     * @return array
     */
    public function run(Request $request, string $operation): array
    {
        $this->errors = [];
        $response = [];
        try {
            $opFlags = Message::toOpFlags(
                $operation, ['add' => Message::F_API, 'disallow' => Message::OP_CREATE]
            );
            $controller = new LedgerDomainController();
            if ($opFlags & Message::OP_QUERY) {
                $message = DomainQuery::fromRequest($request, $opFlags);
                $domains = [];
                foreach ($controller->query($message, $opFlags) as $entry) {
                    $domains[] = $entry->toResponse([]);
                }
                $response['domains'] = $domains;
            } else {
                $message = Domain::fromRequest($request, $opFlags);
                $ledgerDomain = $controller->run($message, $opFlags);
                if ($opFlags & Message::OP_DELETE) {
                    $response['success'] = true;
                } else {
                    $response['domain'] = $ledgerDomain->toResponse();
                }
            }
        } catch (Breaker $exception) {
            $this->warning($exception);
            $response['errors'] = $this->errors;
        } catch (QueryException $exception) {
            $this->dbException($exception);
            $response['errors'] = $this->errors;
        } catch (Exception $exception) {
            $response['errors'] = $this->errors;
            $response['errors'][] = $this->unexpectedException($exception);
        }
        $response['time'] = new Carbon();

        return $response;
    }

}
