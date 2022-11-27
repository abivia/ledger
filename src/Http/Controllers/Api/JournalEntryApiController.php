<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\JournalEntryController;
use Abivia\Ledger\Messages\Entry;
use Abivia\Ledger\Messages\EntryQuery;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class JournalEntryApiController
{
    use ControllerResultHandler;

    /**
     * Perform a journal entry operation.
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
            $controller = new JournalEntryController();
            if ($opFlags & Message::OP_QUERY) {
                $message = EntryQuery::fromRequest($request, $opFlags);
                $entries = [];
                foreach ($controller->query($message, $opFlags) as $entry) {
                    $entries[] = $entry->toResponse(Message::OP_GET);
                }
                $response['entries'] = $entries;
            } else {
                $message = Entry::fromRequest($request, $opFlags);
                $journalEntry = $controller->run($message, $opFlags);
                if ($opFlags & (Message::OP_DELETE)) {
                    $response['success'] = true;
                } else {
                    $response['entry'] = $journalEntry->toResponse($opFlags);
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
