<?php

namespace Blazemedia\App\Utilities;

use PDO;
use PDOException;

class AppendDB {
    protected $db;


    function __construct() {

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

    public function appendData($dataRow){
        $this->tableExists();

        $query = "INSERT INTO dealfeeds 
                    (ASIN, title, price, discount, reference_price, reference_type, 
                    date_start, date_end, category, sub_category, sub_category_other, 
                    URL, dealid, dealtype, dealstate) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";


        foreach ($dataRow as $row) {

            if(empty($row)){
                var_dump($row);
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
                ASIN VARCHAR(255) DEFAULT NULL,
                title VARCHAR(255) DEFAULT NULL,
                price DECIMAL(10, 2) DEFAULT NULL,
                discount VARCHAR(10) DEFAULT NULL,
                reference_price DECIMAL(10, 2) DEFAULT NULL,
                reference_type VARCHAR(50) DEFAULT NULL,
                date_start DATETIME,
                date_end DATETIME,
                category VARCHAR(255) DEFAULT NULL,
                sub_category VARCHAR(255) DEFAULT NULL,
                sub_category_other VARCHAR(255) DEFAULT NULL,
                URL VARCHAR(255),
                dealid VARCHAR(50) DEFAULT NULL,
                dealtype VARCHAR(50) DEFAULT NULL,
                dealstate VARCHAR(50) DEFAULT NULL
            )";

        $this->db->exec($query);
    }

}
