<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\SubJournalController;
use Abivia\Ledger\Messages\SubJournal;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Messages\SubJournalQuery;
use Abivia\Ledger\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SubJournalApiController
{
    use ControllerResultHandler;

    /**
     * Perform a sub-journal operation.
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
            $controller = new SubJournalController();
            if ($opFlags & Message::OP_QUERY) {
                $message = SubJournalQuery::fromRequest($request, $opFlags);
                $subJournals = [];
                foreach ($controller->query($message, $opFlags) as $entry) {
                    $subJournals[] = $entry->toResponse([]);
                }
                $response['journals'] = $subJournals;
            } else {
                $message = SubJournal::fromRequest($request, $opFlags);
                $subJournal = $controller->run($message, $opFlags);
                if ($opFlags & Message::OP_DELETE) {
                    $response['success'] = true;
                } else {
                    $response['journal'] = $subJournal->toResponse();
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
