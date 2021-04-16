<?php
ini_set('memory_limit', '2000M');
if (isset($argv[1])){
    $datfile = $argv[1];
} else {
    die("Please specify a dat file!");
}
if (isset($argv[2])){
    $dbfile = $argv[2];
} else {
    die("Please specify the name of the destination database.");
}
$data = [];
if ($datfile){
    echo "Scanning dat file at $datfile...\n";
    $keys = [];
    $filehandle = @fopen($datfile, "r");
    if($filehandle){
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
                    $keys[] = utf8_encode($key);
                }
            }
            if (isset($line_data[1])){
                $value = trim($line_data[1]);
                $value = htmlentities($value);
                $value = utf8_encode($value);
            }
            if ($key !== ""){
                $entry[$key] = $value;
            }
        }
    }
}
$unique_keys = array_unique($keys);
$sql_create_cmd = 'CREATE TABLE data(id INTEGER PRIMARY KEY';
foreach ($unique_keys as $key) {
    $sql_create_cmd = $sql_create_cmd . ', "' . $key . '"' . " TEXT";
}
$sql_create_cmd = $sql_create_cmd . ')';
echo "Creating database table...\n";
$db = new SQLite3($dbfile);
$db->exec("$sql_create_cmd");
echo "Inserting data...\n";
foreach ($data as $entry) {
    $sql_insert_cmd = "INSERT INTO data(\"";
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
    // echo $sql_insert_cmd . "\n";
    $db->exec("$sql_insert_cmd");
}
echo "Done.\n";