<?php

namespace Blazemedia\App;

use Blazemedia\App\Api\DealFeedAppendSpreadsheetApi;
use Blazemedia\App\Utilities\DataAdapter;
use Carbon\Carbon;
use Exception;
use PDO;
use PDOException;
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

        if (env('APPEND_CONNECTION', 'api') == 'mysql') {

            try {

                $host     = env('DB_HOST', 'localhost');
                $username   = env('DB_USER', 'root');
                $password = env('DB_PASS', '');
                $dbname = env('DB_NAME', '');

                $this->db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
            } catch (PDOException $pe) {

                die("Could not connect to the database $dbname :" . $pe->getMessage());
            }
        }

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

            $i = 0;
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
            $this->dataHeaderClean = (new DataAdapter())->getHeader();;
            return;
        }

        $data = array_combine($this->dataHeader, array_slice($dataDirty, 0, 19));

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

        $data = (new DataAdapter())->getData($data);

        $this->dataByCategory[$category][] = array_values($data);

        if (count($this->dataByCategory[$category]) == 500) {

            $this->appendData($category, $this->dataByCategory[$category]);
            $this->dataByCategory[$category][] = [];
        }
    }


    protected function appendData($category, $dataRow) {

        if (env('APPEND_CONNECTION') == 'mysql') {
            return $this->appendDataToDatabase($dataRow);
        }

        return $this->appendDataToSpreadsheet($category, $dataRow);
    }


    protected function appendDataToSpreadsheet($category, $dataRow) {

        $sheet = new DealFeedAppendSpreadsheetApi($this->credentials);

        echo "\n" . $category . "\n";

        return $sheet->appendData($category, $dataRow, $this->dataHeaderClean, 'Sheet1');
    }

    protected function appendDataToDatabase($dataRow) {

        $this->tableExists();

        $query = "INSERT IGNORE dealfeeds 
                    (ASIN, title, price, discount, reference_price, reference_type, 
                    date_start, date_end, category, sub_category, sub_category_other, 
                    URL, dealid, dealtype, dealstate) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";


        foreach ($dataRow as $row) {

            if(empty($row)){
                continue;
            }

            $statement = $this->db->prepare($query);

            for ($i = 0; $i < count($row); $i++) {
                $statement->bindParam($i + 1, $row[$i]);
            }

            $statement->execute();
        }
    }

    protected function tableExists() {

        $tableName = 'dealfeeds';
        $checkTableExists = $this->db->query("SHOW TABLES LIKE '$tableName'")->rowCount() > 0;

        if ($checkTableExists) {
            return;
        }

        $query = "CREATE TABLE $tableName (
                ASIN VARCHAR(255),
                title VARCHAR(255),
                price DECIMAL(10, 2),
                discount VARCHAR(10),
                reference_price DECIMAL(10, 2),
                reference_type VARCHAR(50),
                date_start DATETIME,
                date_end DATETIME,
                category VARCHAR(255),
                sub_category VARCHAR(255),
                sub_category_other VARCHAR(255),
                URL VARCHAR(255),
                dealid VARCHAR(50),
                dealtype VARCHAR(50),
                dealstate VARCHAR(50),
            )";

        $this->db->exec($query);
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
        if (empty($this->dataByCategory)) return false;

        foreach ($this->dataByCategory as $category => $data) {
            $this->appendData($category, $data);
        }

        return true;
    }
}
