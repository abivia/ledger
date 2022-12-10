<?php
declare(strict_types=1);

namespace Abivia\Ledger\Tests\Feature;

trait PageLoader
{
    private function getPages(
        string $entryPoint,
        array $requestData,
        string $responseType,
        string $responseElement,
        callable $setAfter
    ): array {
        $pages = 0;
        $total = 0;
        while (1) {
            $response = $this->json(
                'post', $entryPoint, $requestData
            );
            $actual = $this->isSuccessful($response, $responseElement);
            // Check the response against our schema
            $this->validateResponse($actual, $responseType);
            $resources = $actual->$responseElement;
            ++$pages;
            $total += count($resources);
            if (count($resources) !== $requestData['limit']) {
                break;
            }
            $setAfter($requestData, $resources);
        }

        return [$pages, $total];
    }

}
