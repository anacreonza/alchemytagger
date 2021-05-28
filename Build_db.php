<?php
namespace App;
ini_set('memory_limit', '2000M');
require 'vendor/autoload.php';
use App\SQLiteConnection;

$dat_file = $argv[1];
if ($dat_file == null){
    die("Please specify an Alchemy DAT file.");
}
$db_name = basename($dat_file);
$db_file = str_replace(".dat", ".sqlite", $dat_file);
$pdo = (new SQLiteConnection())->connect($db_file);
if ($pdo == null) {
    die("Error connecting to SQLite database.");
}
\print_r("Building SQLite Database for $db_name\n");
$filehandle = @fopen($dat_file, "r");
if ($filehandle == null){
    die("Unable to read dat file $dat_file\n");
}
$data = [];
echo "Scanning dat file at $dat_file...\n";
$keys = [];

while(($line = fgets($filehandle)) !== false) {
    // echo "Processing line " . $line_no . "\n";
    $line = str_replace(array("\n", "\r"), '', $line);
    if (empty($line)){
        $data[] = $entry;
    }
    $line_data = explode(": ", $line);
    $key = '';
    $value = '';
    if (isset($line_data[0])){
        $key = trim($line_data[0]);
        $key = str_replace("$", '', $key);
        if ($key == "ID"){
            $key = "Alchemy ID";
            // Start a new entry
            $entry = [];
        }
        // Add valid key to array of keys to figure out how to build db.
        if ($key !== null){
            $keys[] = \SQLite3::escapeString($key);
        }
    }
    if (isset($line_data[1])){
        $value = trim($line_data[1]);
        $value = \SQLite3::escapeString($value);
    }
    if ($key !== ""){
        $entry[$key] = $value;
    }
}
$unique_keys = array_unique($keys);
$sql_create_cmd = 'CREATE TABLE IF NOT EXISTS records(id INTEGER PRIMARY KEY';
foreach ($unique_keys as $key) {
    if ($key == ''){
        continue;
    }
    $sql_create_cmd = $sql_create_cmd . ', "' . $key . '"' . " TEXT";
}
$sql_create_cmd = $sql_create_cmd . ', "Matched" TEXT, "Processed" TEXT)';
echo "Creating database table...\n";
$pdo->exec("$sql_create_cmd");
echo "Inserting data...\n";
foreach ($data as $entry) {
    $sql_insert_cmd = "INSERT INTO records(\"";
    $keys = [];
    $values = [];
    foreach ($entry as $key => $value) {
        $keys[] = $key;
    }
    foreach ($entry as $key => $value) {
        $values[] = $value;
    }
    $key_string = implode("\", \"", $keys);
    $value_string = implode("\", \"", $values);
    $sql_insert_cmd = $sql_insert_cmd . $key_string . "\") VALUES(\"" . $value_string . "\")";
    $pdo->exec("$sql_insert_cmd");
}
echo "Done.\n";