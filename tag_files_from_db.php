<?php
namespace App;
require 'vendor/autoload.php';
ini_set('memory_limit', '-1');
date_default_timezone_set('Africa/Johannesburg');
use App\SQLiteConnection;
$config = new Config;
$config->setConfig();

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
    $result = $query->fetch(\PDO::FETCH_ASSOC);
    if ($result["Processed"]){
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
        echo("- Referenced file exists!\n");
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
        mark_entry_as_not_found_in_db($pdo, $entry["id"]);
        return False;
    }
}
function append_keywords($entry, $pdf){
    // First check if there is already a PDF file for this entry.
    //  Pull existing metadata out of existing file.
    $cmd = EXIFTOOL . " -subject " . escapeshellarg($pdf);
    $existing_meta_string = exec($cmd);
    if ($existing_meta_string !== ''){
        echo("- Current entry refers to a file that has been processed before! Adding new metadata keys...\n");
        $existing_meta_string = str_replace("Subject                         : ", '', $existing_meta_string);
        $existing_meta_array = explode(", ", $existing_meta_string);
        foreach($existing_meta_array as $meta_item){
            $meta_item_array = explode(': ', $meta_item);
            $meta_item_key = $meta_item_array[0];
            $meta_item_value = $meta_item_array[1];
            if (in_array($meta_item_value, $entry)){
                continue;
            } else {
                $entry[$meta_item_key] = $entry[$meta_item_key] . ", " . $meta_item_value;
            }
        }
    }
    foreach ($entry as $meta_key => $meta_value) {
        if (trim($meta_value) == '' || trim($meta_value) == "." || $meta_key == 'id'){
            continue; //Dont add blank or irellevant keys.
        }
        $key_name = $meta_key . ": ";
        $tag_string = $key_name . $meta_value;
        echo("- Appending " . $tag_string . "\n");
        $cmd = EXIFTOOL . " " . escapeshellarg($pdf) . " -ignoreMinorErrors -overwrite_original -subject+=\"" . $tag_string . "\"";
        exec($cmd);

    }
}
function mark_entry_as_done_in_db($pdo, $selected_entry_id){
    echo("- Marking entry as done in database.\n");
    $sql = "UPDATE records set \"Processed\" = " . "\"" . date ("Y/m/d H:i:s") . "\"" . " WHERE id is " . $selected_entry_id;
    $query = $pdo->prepare($sql);
    $result = $query->execute();
    return $result;
}
function mark_entry_as_corrupt_in_db($pdo, $selected_entry_id){
    echo ("- Failed to produce PDF! Marking entry as corrupt in database\n");
    $sql = "UPDATE records set \"Matched\" = \"Corrupt File\" WHERE id is " . $selected_entry_id;
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
function mark_entry_as_not_found_in_db($pdo, $selected_entry_id){
    $sql = "UPDATE records set \"Matched\" = " . "\"REFERENCED FILE MISMATCH!\"" . " WHERE id is " . $selected_entry_id;
    $query = $pdo->prepare($sql);
    $result = $query->execute();
    return $result;
}
function pick_an_entry($pdo){
    $sql = "SELECT id FROM \"records\" WHERE \"Processed\" is null ORDER by random() LIMIT 1";
    $query = $pdo->query($sql);
    $result = $query->fetch(\PDO::FETCH_ASSOC);
    return $result['id'];
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
        $pdf = $path_parts['dirname']. DIRECTORY_SEPARATOR . $path_parts['filename'] . ".pdf";
        if ($path_parts['extension'] == "tif" || $path_parts['extension'] == "TIF"){
            echo("- Converting TIFF to PDF...");
            if (PHP_OS === "WINNT"){
                $cmd = "magick.exe convert " . escapeshellarg($file) . " " . escapeshellarg($pdf);
                
                // die(var_dump($cmd));
            } else {
                $cmd = CONVERT . " " . escapeshellarg($file) . " " . escapeshellarg($pdf);
            }
            exec($cmd);
            if (file_exists($pdf)){
                echo(" [OK]\n");
                echo("- Storing " . basename($pdf));
                rename($pdf, $out_file); // Moves PDF to new location
            } else {
                $result = mark_entry_as_corrupt_in_db($pdo, $id);
                $result = mark_entry_as_done_in_db($pdo, $id);
                return $result;
            }
        }
        if ($path_parts['extension'] == "pdf" || $path_parts['extension'] == "PDF"){
            echo("- Storing " . basename($pdf));
            copy($pdf, $out_file); //Copy PDF to new location
        }
        if (file_exists($out_file)){
            echo(" [OK]\n");
        } else {
            $result = mark_entry_as_corrupt_in_db($pdo, $id);
            $result = mark_entry_as_done_in_db($pdo, $id);
            return $result;
        }
        $result = append_keywords($entry, $out_file);
    } else {
        echo("File " . $entry["File Name"] . " failed verification!\n");
    }
    $result = mark_entry_as_done_in_db($pdo, $id);
    return $result;
}
if (!isset($argv[1])){
    die("Please specify the root directory of the archive you wish to process.");
}
$archive_root = $argv[1];
$archive_name = basename($archive_root);
$output_root = $archive_root . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "Processed" . DIRECTORY_SEPARATOR . $archive_name;
if (!file_exists($output_root)){
    echo "Making new folder $output_root\n";
    mkdir($output_root, 0777, true);
}

$db_file = $archive_root . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . $archive_name . '.sqlite';
// $db_file = escapeshellarg($db_file);
// $db_file = "D:\Backup_2\WELKOM YIZANI V.2.0 beta 2\db\WELKOM YIZANI V.2.0 beta 2.sqlite";
if (!file_exists($db_file)){
    die("Unable to open DB file: " . $db_file);
}

$pdo = (new SQLiteConnection())->connect($db_file);
if ($pdo == null) {
    die("Error connecting to SQLite database.");
}
$entry_count = count_entries($pdo);
$done_entries_this_script_knows_about = [];
$completion_times = [];

echo("\n===========================================\n\n");
echo("ALCHEMY TAGGING SCRIPT\n\n");
echo("Archive Name:  " . $archive_name . "\n");
echo("Total entries: " . $entry_count . "\n");
echo("\n===========================================\n\n");

while (count_done_entries($pdo) <= $entry_count){
    $start_time = microtime(true);
    $selected_entry_id = pick_an_entry($pdo);
    $percent_complete = round(count_done_entries($pdo) / $entry_count * 100, 2);
    $remaining_entries = $entry_count - count_done_entries($pdo);
    echo("\n\nProcessing entry id " . $selected_entry_id . ". " . $remaining_entries . " of " . $entry_count . " entries remaining. (" . $percent_complete . "%)\n\n");
    $result = process_entry($archive_root, $pdo, $selected_entry_id);
    if ($result){
        $end_time = microtime(true);
        $elapsed_time = $end_time - $start_time;
        $completion_times[] = $elapsed_time;
        $average_completion_time = array_sum($completion_times) / count($completion_times);
        echo("Took " . round($elapsed_time, 2) . " seconds. Average is " . round($average_completion_time, 2) . ".\n");
    }
}
echo("Conversion script complete!\n\n");
?>