<?php

namespace Blazemedia\App;

use Blazemedia\App\Api\DealFeedAppendSpreadsheetApi;
use Blazemedia\App\Utilities\CsvReader;
use Carbon\Carbon;

class DealFeedSpreadSheetFetcher {

    const BASE_DIRECTORY = 'amazon-temp';
    protected array $availableLinks = [];
    protected array $dataByCategory = [];
    protected array $dataHeader = [];
    protected $startDate;
    protected $endDate;
    protected $credentials;

    /**
     * Create a new fetcher command instance.
     *
     * @return void
     */
    public function __construct($credentials) {

        $this->credentials = $credentials;

        $this->setOptions();
    }

    /**
     * Current account credentials
     *
     * @var null|array
     */
    protected $currentAccount = null;

    protected function fetchOps(): void {
        $this->clearPlatformData();

        $this->currentAccount = env('apiamazon.accounts.blazemedia', []);

        $this->fetchFile();

        foreach ($this->availableLinks as $url) {
            $this->fetchFile($url);
        }
    }

    ///fetchDay
    protected function fetchFile(): void {

        $date = $this->startDate;
        echo('Starting import for date ' . $date->format('d/m/Y'));
        $filePath = __DIR__.'../amazon-temp/dealfeeds.csv.gz';

        if (!file_exists($filePath)) {
            echo('File non trovato');
            die();
        }

        $fileUnzipped = $this->unZipFile($filePath);

        $this->warn('Appending Datas...');

        gc_enable();
        $i = 0;

        $csv = new CsvReader($fileUnzipped);
        $i = 0;
        $csv->foreach(function ($data) use (&$i,$date) {
            try {

                $this->adaptData($data);
                $i++;

            } catch (\Throwable $th) {
                echo $th;
            }

            if( $i % 500 == 0 ) gc_collect_cycles();
        });

        $this->line(' Datas imported...', 'success');

        echo("\nSomething went wrong fetching Amazon! ");
    }

    protected function adaptData($data) {

        if (count($this->dataByCategory) == 0 && empty($this->dataHeader)) {
            unset($data['category']);
            unset($data['imageURL']);
            unset($data['browseNodeId1']);
            unset($data['browseNodeId2']);
            unset($data['subcategoryPath2']);
            unset($data['marketingMessage']);

            $this->dataHeader = $data;
            return;
        }

        $endDate = Carbon::createFromFormat('Y-m-d H:i:s O', $data['dealEndTime']);
        if ($endDate->isPast()) return;

        $categories = explode("/", $data['subcategoryPath1']);
        $category = array_shift($categories);
        $data['subcategoryPath1'] = implode("/", array_slice($categories, 0, 3));

        unset($data['category']);
        unset($data['imageURL']);
        unset($data['browseNodeId1']);
        unset($data['browseNodeId2']);
        unset($data['subcategoryPath2']);
        unset($data['marketingMessage']);

        $this->dataByCategory[$category][] = array_values($data);

        if (count($this->dataByCategory[$category]) == 1000) {

            $this->appendDataToSpreadsheet($category, $this->dataByCategory[$category]);
            $this->dataByCategory[$category][] = [];
        }
    }



    protected function appendDataToSpreadsheet($category, $dataRow) {

        $sheet = new DealFeedAppendSpreadsheetApi($this->credentials);

        $sheet->appendData($category, 'Sheet1', $dataRow, $this->dataHeader);
    }

    protected function setOptions(){
        $this->startDate = getOpt('start-date');
        $this->endDate = getOpt('start-date');
    }

    
    protected function unZipFile(string $filename): string {
        $bufferSize = 4096;
        $file = gzopen($filename, 'rb');
        $outputFilename = str_replace('.gz', '', $filename);
        $outFile = fopen($outputFilename, 'wb');

        // Keep repeating until the end of the input file
        while (!gzeof($file)) {
            fwrite($outFile, gzread($file, $bufferSize)); //Read buffer-size bytes.
        }

        fclose($outFile); //Close the files once they are done with
        gzclose($file);

        return $outputFilename;
    }
}
