<?php
ini_set('memory_limit', '-1');
require 'vendor/autoload.php';
use App\SQLiteConnection;

$db_file = $argv[1];
if ($db_file == null){
    die("Please specify an SQLite DB.");
}

$pdo = (new SQLiteConnection())->connect($db_file);
if ($pdo == null) {
    die("Error connecting to SQLite database.");
}

print_r("Scanning " . basename($db_file) . "\n");

$sql = 'SELECT COUNT(*) from records';

$query = $pdo->query($sql);
$result = $query->fetch(\PDO::FETCH_ASSOC);
$total = $result["COUNT(*)"];
$tiffs = [];

for ($i=0; $i < $total; $i++) { 
    $sql = 'SELECT * FROM RECORDS WHERE "id" IS ' . $i;
    $query = $pdo->query($sql);
    $result = $query->fetch(\PDO::FETCH_ASSOC);
    $tiff_filename = $result["File Name"];
    $tiffs[] = $tiff_filename;
};

$dupes = [];
foreach($tiffs as $selected_tiff){
    $count = 0;
    foreach($tiffs as $tiff){
        if ($selected_tiff == $tiff){
            $count++;
        }
    }
    if ($count >= 2){
        $dupes[] = $selected_tiff;
    }
}

echo(count($tiffs) . " TIFFs\n");
echo(count($dupes) . " Duplicates.\n");
?>