<?php

namespace Blazemedia\App\Utilities;

use Carbon\Carbon;
use PDO;
use PDOException;

class AppendDB {

    protected $db;
    protected $tableName = 'dealfeeds';


    function __construct() {

        try {

            $host     = env('DB_HOST', 'localhost');
            $username = env('DB_USER', 'root');
            $password = env('DB_PASS', '');
            $dbname   = env('DB_NAME', '');

            $this->db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

        } catch (PDOException $pe) {

            die("Could not connect to the database $dbname :" . $pe->getMessage());
        }

        if( !$this->tableExists() ) $this->createTable();

    }


    public function appendData($dataRow) {

        $query = "INSERT INTO dealfeeds 
                    (ASIN, title, price, discount, reference_price, reference_type, 
                    date_start, date_end, category, sub_category, sub_category_other, 
                    URL, dealid, dealtype, dealstate) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    title = VALUES(title),
                    price = VALUES(price),
                    discount = VALUES(discount),
                    reference_price = VALUES(reference_price),
                    reference_type = VALUES(reference_type),
                    date_start = VALUES(date_start),
                    date_end = VALUES(date_end),
                    category = VALUES(category),
                    sub_category = VALUES(sub_category),
                    sub_category_other = VALUES(sub_category_other),
                    URL = VALUES(URL),
                    dealid = VALUES(dealid),
                    dealtype = VALUES(dealtype),
                    dealstate = VALUES(dealstate)";


        foreach( $dataRow as $row ) {

            if( empty($row) ) {
                print_r( $row );
                continue;
            }

            $statement = $this->db->prepare($query);

            for ($i = 0; $i < count($row); $i++) {
                $statement->bindParam($i + 1, $row[$i]);
            }
            $statement->execute();
        }
    }


    protected function createTable() {

        $query = <<<SQL
        
        CREATE TABLE `{$this->tableName}` (
            `ASIN`  VARCHAR(63) PRIMARY KEY,
            `title` VARCHAR(255),
            `price` DECIMAL(10, 2),
            `discount`  VARCHAR(10) DEFAULT NULL,
            `reference_price` DECIMAL(10, 2) DEFAULT NULL,
            `reference_type`  VARCHAR(63) DEFAULT NULL,
            `date_start` DATETIME,
            `date_end`   DATETIME,
            `category` VARCHAR(255) DEFAULT NULL,
            `sub_category` VARCHAR(255) DEFAULT NULL,
            `sub_category_other` VARCHAR(255) DEFAULT NULL,
            `URL` TEXT,
            `dealid` VARCHAR(63) DEFAULT NULL,
            `dealtype` VARCHAR(63) DEFAULT NULL,
            `dealstate` VARCHAR(63) DEFAULT NULL
        )        
        SQL;

        $this->db->exec($query);
    }

    
    protected function tableExists() {

        return $this->db->query("SHOW TABLES LIKE '%{$this->tableName}%'")->rowCount() > 0;
    }


    public function truncateExpiredDatas($date = null) {
        if (!$date){
            $date = Carbon::today()->format('Y-m-d H:i:s');
        }

        $query = "DELETE FROM dealfeeds WHERE date_end < '$date'";

        $this->db->exec($query);
    }
}
