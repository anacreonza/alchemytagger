<?php
namespace App;

/**
 * SQLite connnection
 */
class SQLiteConnection {
    /**
     * PDO instance
     * @var type 
     */
    private $pdo;

    /**
     * return in instance of the PDO object that connects to the SQLite database
     * @return \PDO
     */
    public function connect($db_file) {
        if ($this->pdo == null) {
            $this->pdo = new \PDO("sqlite:" . $db_file);
        }
        return $this->pdo;
    }
    public function get_total_entries_from_db($db_file){
        
    }
}