<?php
namespace App;
require 'vendor/autoload.php';
ini_set('memory_limit', '-1');
date_default_timezone_set('Africa/Johannesburg');
use App\SQLiteConnection;
Config::setConfig();

function count_entries($pdo){
    $sql = "SELECT COUNT(*) FROM \"records\"";
    $query = $pdo->query($sql);
    $result = $query->fetch(\PDO::FETCH_ASSOC);
    return $result["COUNT(*)"];
}
function count_done_entries($pdo){
    $sql = "SELECT COUNT(*) FROM \"records\" WHERE \"Processed\" IS NOT Null";
    $query = $pdo->query($sql);
    $result = $query->fetch(\PDO::FETCH_ASSOC);
    return $result["COUNT(*)"];
}
function check_when_entry_was_processed($pdo, $id){
    $sql = "SELECT \"Processed\" FROM \"records\" WHERE \"id\" IS " . $id;
    $query = $pdo->query($sql);
    if ($query->fetch(\PDO::FETCH_ASSOC)){
        $result = $query->fetch(\PDO::FETCH_ASSOC);
        return $result["Processed"];
    } else {
        return False;
    }
}
function retrieve_entry($pdo, $entry_id){
    $sql = "SELECT * FROM \"records\" WHERE \"id\" IS $entry_id";
    $query = $pdo->query($sql);
    $result = $query->fetch(\PDO::FETCH_ASSOC);
    return $result;
}
function verify_file($pdo, $archive_root, $entry){
    if ($archive_root == Null){
        die("Invalid Archive root!\n");
    }
    $dir = $entry['FOLDER'];
    $cleaned_dir_separator = str_replace("\\", DIRECTORY_SEPARATOR, $dir);
    $file = $archive_root . DIRECTORY_SEPARATOR . $cleaned_dir_separator . DIRECTORY_SEPARATOR . $entry['File Name'];
    if (file_exists($file)){
        echo("- File exists!\n");
        $file_exists = True;
    } else {
        echo("- File " . $file . " not found!\n");
        $file_exists = False;
    }
    if ($entry["File Size"] == filesize($file)){
        echo("- File size is a match!\n");
        $file_size_match = True;
    } else {
        echo("- File size mismatch!!!\n");
        $file_size_match = False;
    }
    $actual_file_date = date ("Y/m/d H:i:s", filemtime($file));
    if ($actual_file_date == $entry["File Date"]){
        echo("- File date is a match!\n");
        $file_date_match = True;
    } else {
        echo("- File date mismatch!!!\n");
        echo("- File date in DB:  " . $entry["File Date"] . "\n");
        echo("- Actual file date: " . $actual_file_date . "\n");
        $file_date_match = False;
    }
    if ($file_exists && $file_size_match && $file_date_match){
        mark_entry_as_found_in_db($pdo, $entry["id"]);
        return True;
    } else {
        return False;
    }
}
function append_keywords($entry, $pdf){
    foreach ($entry as $meta_key => $meta_value) {
        if (trim($meta_value) == '' || trim($meta_value) == "." || $meta_key == 'id'){
            continue; //Dont add blank or irellevant keys.
        }
        $key_name = $meta_key . ": ";
        $tag_string = $key_name . $meta_value;
        echo("- Appending " . $tag_string . "\n");
        $cmd = EXIFTOOL . " " . escapeshellarg($pdf) . " -ignoreMinorErrors -subject+=\"" . $tag_string . "\"";
        exec($cmd);
    }
}
function mark_entry_as_done_in_db($pdo, $selected_entry_id){
    $sql = "UPDATE records set \"Processed\" = " . "\"" . date ("Y/m/d H:i:s") . "\"" . " WHERE id is " . $selected_entry_id;
    $query = $pdo->prepare($sql);
    $result = $query->execute();
    return $result;
}
function mark_entry_as_found_in_db($pdo, $selected_entry_id){
    $sql = "UPDATE records set \"Matched\" = " . "\"" . date ("Y/m/d H:i:s") . "\"" . " WHERE id is " . $selected_entry_id;
    $query = $pdo->prepare($sql);
    $result = $query->execute();
    return $result;
}
function process_entry($archive_root, $pdo, $id){
    $entry = retrieve_entry($pdo, $id);
    if (verify_file($pdo, $archive_root, $entry)){
        $archive_name = basename($archive_root);
        $output_root = $archive_root . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "Processed" . DIRECTORY_SEPARATOR . $archive_name;
        $dir = $entry['FOLDER'];
        $dir = str_replace("\\", DIRECTORY_SEPARATOR, $dir);
        $file = $archive_root . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $entry['File Name'];
        $path_parts = pathinfo($file);
        $out_file = $output_root . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . $path_parts['filename'] . ".pdf";
        if (!file_exists(dirname($out_file))){
            mkdir(dirname($out_file), 0777, true);
        }
        if ($path_parts['extension'] == "tif" || $path_parts['extension'] == "TIF"){
            echo("- Converting TIFF to PDF...");
            $pdf = $path_parts['dirname']. DIRECTORY_SEPARATOR . $path_parts['filename'] . ".pdf";
            if (PHP_OS === "WINNT"){
                $cmd = CONVERT . " convert " . escapeshellarg($file) . " " . escapeshellarg($pdf);
            } else {
                $cmd = CONVERT . " " . escapeshellarg($file) . " " . escapeshellarg($pdf);
            }
            exec($cmd);
            if (file_exists($pdf)){
                echo(" [OK]\n");
            } else {
                echo(" [Failed to produce PDF!] $pdf\n");
            }
        }
        $result = append_keywords($entry, $pdf);
        if ($path_parts['extension'] == "tif" || $path_parts['extension'] == "TIF"){
            echo("- Storing " . basename($pdf));
            rename($pdf, $out_file); // Moves PDF to new location
            if (file_exists($out_file)){
                echo(" [OK]\n");
            } else {
                die("Unable to store processed PDF!");
            }
        }
    } else {
        echo("File " . $entry_details["File Name"] . " failed verification!\n");
        return False;
    }
    return True;
}
$archive_root = $argv[1];
if ($archive_root == null){
    die("Please specify the root directory of the archive you wish to process.");
}
$archive_name = basename($archive_root);
$output_root = $archive_root . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "Processed" . DIRECTORY_SEPARATOR . $archive_name;
if (!file_exists($output_root)){
    echo "Making new folder $output_root\n";
    mkdir($output_root, 0777, true);
}

$db_file = $archive_root . DIRECTORY_SEPARATOR . "db" . DIRECTORY_SEPARATOR . $archive_name . ".sqlite";
if (!file_exists($db_file)){
    die("Unable to open DB file " . $db_file);
}

$pdo = (new SQLiteConnection())->connect($db_file);
if ($pdo == null) {
    die("Error connecting to SQLite database.");
}
$entry_count = count_entries($pdo);

echo("\n===========================================\n\n");
echo("ALCHEMY TAGGING SCRIPT\n\n");
echo("Archive Name:  " . $archive_name . "\n");
echo("Total entries: " . $entry_count . "\n");
echo("\n===========================================\n\n");

while (count_done_entries($pdo) < $entry_count){
    $start_time = microtime(true);
    $selected_entry_id = rand(1, $entry_count);
    $date_done = check_when_entry_was_processed($pdo, $selected_entry_id);
    if ($date_done){
        echo("- Entry already processed on " . $date_done . "\n");
        continue;
    }
    $percent_complete = round(count_done_entries($pdo) / $entry_count * 100, 2);
    echo("\n\nProcessing entry id " . $selected_entry_id . ". " . count_done_entries($pdo) . " of " . $entry_count . " entries complete. (" . $percent_complete . "%)\n\n");

    $result = process_entry($archive_root, $pdo, $selected_entry_id);
    if ($result){
        echo("- Marking entry as done in database.\n");
        $result = mark_entry_as_done_in_db($pdo, $selected_entry_id);
        $end_time = microtime(true);
        $elapsed_time = $end_time - $start_time;
        echo("Entry took " . round($elapsed_time, 2) . " seconds to process.\n");
    }
}
?>