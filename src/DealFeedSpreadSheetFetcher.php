<?php

namespace Blazemedia\App;

use Blazemedia\App\Api\DealFeedAppendSpreadsheetApi;
use Blazemedia\App\Utilities\AppendDB;
use Blazemedia\App\Utilities\DataAdapter;
use Carbon\Carbon;
use SplFileObject;

class DealFeedSpreadSheetFetcher {

    protected array $availableLinks = [];
    protected array $dataByCategory = [];
    protected array $dataHeader, $dataHeaderClean = [];
    protected $clearData;
    protected $credentials;
    protected $db;

    /**
     * Create a new fetcher command instance.
     *
     * @return void
     */
    public function __construct($credentials) {

        $this->credentials = $credentials;

        $this->setOptions();

        $this->clearPlatformData();

        $this->fetchFile();
    }

    ///fetchDay
    protected function fetchFile(): void {

        $date = Carbon::today();
        echo ('Starting import from date ' . $date->format('d/m/Y'));
        $filePath = __DIR__ . '/../amazon-temp/dealfeeds.csv.gz';

        if (!file_exists($filePath)) {
            echo ("\nFile non trovato\n\n");
            die();
        }

        $fileUnzipped = $this->unZipFile($filePath);

        echo ("\nAppending Datas...\n");

        gc_enable();

        try {
            $file = new SplFileObject($fileUnzipped);
            $file->setFlags(SplFileObject::READ_CSV);
            foreach ($file as $row) {
                if (empty($row)) continue;
                $this->adaptData($row);
            }
            if ($this->completeSpreadsheet()) {
                echo "\n DONE \n";
            }
        } catch (\Throwable $th) {
            //throw $th;
            echo ("\nSomething went wrong fetching Amazon! ERROR:\n$th");
        }
    }

    protected function adaptData($dataDirty) {

        if (count($this->dataByCategory) == 0 && empty($this->dataHeader)) {
            $dataHeader = array_combine($dataDirty, $dataDirty);
            unset($dataHeader['category']);
            unset($dataHeader['imageURL']);
            unset($dataHeader['browseNodeId1']);
            unset($dataHeader['browseNodeId2']);
            unset($dataHeader['subcategoryPath2']);
            unset($dataHeader['marketingMessage']);

            $this->dataHeader = $dataDirty;
            $this->dataHeaderClean = (new DataAdapter())->getHeader();
            return;
        }

        if(count($this->dataHeader) != count($dataDirty))return;

        $data = array_combine($this->dataHeader, $dataDirty);

        $endDate = Carbon::createFromFormat('Y-m-d H:i:s O', $data['dealEndTime']);
        if ($endDate->isPast()) return;
        $categories = explode("/", $data['subcategoryPath1']);
        $category = array_shift($categories);
        $data['subcategoryPath1'] = implode("/", array_slice($categories, 0, 3));

        $data = (new DataAdapter())->getData($data);
        if (env('APPEND_CONNECTION') == 'mysql') {
            return $this->appendData($category, [array_values($data)]);
            gc_collect_cycles();

        }

        $this->dataByCategory[$category][] = array_values($data);

        if (count($this->dataByCategory[$category]) == 100) {
            gc_collect_cycles();

            $this->appendData($category, $this->dataByCategory[$category]);
            $this->dataByCategory[$category][] = [];
        }
    }


    protected function appendData($category, $dataRow) {
        if (env('APPEND_CONNECTION') == 'mysql') {
            return $this->appendDataToDatabase($category, $dataRow);
        }

        return $this->appendDataToSpreadsheet($category, $dataRow);
    }


    protected function appendDataToSpreadsheet($category, $dataRow) {

        $sheet = new DealFeedAppendSpreadsheetApi($this->credentials);

        echo "\n" . $category . "\n";

        return $sheet->appendData($category, $dataRow, $this->dataHeaderClean, 'Sheet1');
    }

    protected function appendDataToDatabase($category, $dataRow) {

        $db = new AppendDB();

        $db->appendData($dataRow);
    }

    protected function setOptions() {
        if ($clearData = getopt('clear-data')) {
            $this->clearData = $clearData;
        }
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


    protected function clearPlatformData() {
    }

    protected function completeSpreadsheet(): bool {
        if (env('APPEND_CONNECTION') == 'mysql') return true;

        if (empty($this->dataByCategory)) return true;


        foreach ($this->dataByCategory as $category => $data) {

            $this->appendData($category, $data);
        }

        return true;
    }
}
