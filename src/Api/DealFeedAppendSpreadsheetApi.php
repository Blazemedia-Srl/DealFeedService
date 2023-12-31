<?php

namespace Blazemedia\App\Api;

use Blazemedia\GDrive\GSpreadsheet;
use Blazemedia\GDrive\GSpreadsheetAppend;

class DealFeedAppendSpreadsheetApi {

    protected $config;
    protected $sheetService;
    protected $spreadsheetService;

    function __construct($credentials) {

        $credentials_path = $credentials;

        $this->sheetService = new GSpreadsheetAppend($credentials_path);
        $this->spreadsheetService = new GSpreadsheet($credentials_path);
    }


    public function appendData($spreadsheetTitle, array $dataRow, array $dataHeader, $range = 'Sheet1',) {
        if (empty($spreadsheetTitle)) return;

        //  $spreadsheet = $this->spreadsheetService->deleteByName($spreadsheetTitle);return;

        $spreadsheet = $this->spreadsheetService->checkOrCreate($spreadsheetTitle, $dataHeader);

        $inserting = $this->sheetService->insertData($spreadsheet['id'], $range, $dataRow);

        return $inserting;
    }
}
