<?php

namespace Abivia\Ledger\Traits;

use Exception;

trait CommonResponseProperties
{
    /**
     * Add common properties to a response array.
     * @param array $response The response array.
     * @param array<string> $exclude A list of fields to be excluded.
     * @return array The response array with common fields.
     * @throws Exception
     */
    private function commonResponses(array $response, array $exclude = []): array
    {
        if (!in_array('names', $exclude)) {
            $response['names'] = [];
            foreach ($this->names as $name) {
                $response['names'][] = $name->toResponse();
            }
        }
        if (!in_array('extra', $exclude) && isset($this->extra)) {
            $response['extra'] = $this->extra;
        }
        $response['revision'] = $this->revisionHash;
        $response['createdAt'] = $this->created_at;
        $response['updatedAt'] = $this->updated_at;
        $this->batchLogFetch();

        return $response;
    }
}
