<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\Breaker;
use App\Http\Controllers\LedgerDomainController;
use App\Models\Messages\Ledger\Domain;
use App\Models\Messages\Message;
use App\Traits\ControllerResultHandler;
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
            $opFlag = Message::toOpFlags(
                $operation, ['add' => Message::F_API, 'disallow' => Message::OP_CREATE]
            );
            $message = Domain::fromRequest($request->all(), $opFlag);
            $controller = new LedgerDomainController();
            $ledgerDomain = $controller->run($message, $opFlag);
            if ($opFlag & Message::OP_DELETE) {
                $response['success'] = true;
            } else {
                $response['domain'] = $ledgerDomain->toResponse();
            }
        } catch (Breaker $exception) {
            $this->errors[] = $exception->getErrors();
            $this->warning($exception);
            $response['errors'] = $this->errors;
        } catch (QueryException $exception) {
            $this->dbException($exception);
            $response['errors'] = $this->errors;
        } catch (Exception $exception) {
            $this->unexpectedException($exception);
        }
        $response['time'] = new Carbon();

        return $response;
    }

}
