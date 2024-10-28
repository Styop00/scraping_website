<?php

namespace App\Services\GoogleAPI;

use Google\Service\Exception;
use Google\Service\Sheets;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Client
{
    protected Sheets $service;

    public function __construct()
    {
        $this->service = $this->client();
    }

    /**
     * @return Sheets
     * @throws \Google\Exception
     */
    private function client(): Sheets
    {
        $client = new \Google\Client();
        $client->setScopes(Sheets::SPREADSHEETS);
        $client->setAuthConfig(Storage::path('google_api_key.json'));
        $client->setAccessType('offline');

        return new Sheets($client);
    }

    /**
     * @param array $data
     * @throws Exception
     */
    public function insertDataIntoSheet(array $data = []): void
    {
        try {
            $googleDocId = config('google.sheet_id');

            $chunkedData = array_chunk($data, 20);

            $sheetName = config('google.sheet_name');

            foreach ($chunkedData as $chunk) {

                $body = new Sheets\ValueRange([
                    'values' => $chunk
                ]);

                $params = [
                    'valueInputOption' => 'USER_ENTERED', // Options: RAW or USER_ENTERED
                ];

                // Perform the insert request
                $this->service->spreadsheets_values->append($googleDocId, $sheetName . '!A1:A10', $body, $params);
            }
        } catch (Exception $exception) {
            Log::info('Insert data to Google sheet error: ' . $exception->getMessage());
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getLastValues(): array
    {
        $googleDocId = config('google.sheet_id');
        $sheetName = config('google.sheet_name');

        // Perform the insert request
        $values = $this->service->spreadsheets_values->get($googleDocId, $sheetName . '!B:B');

        $numbers = array_slice($values->getValues(), -15);

        return array_column($numbers, 0);
    }

}
