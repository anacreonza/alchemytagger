<?php
/*
Metadata mapping

'Document Title'    = EXIF/title
'File Date'         = EXIF/date
'Practice Number'   = keyword
'Doctor's Name'     = keyword
'FOLDER'            = broken up into keywords
*/

require 'vendor/autoload.php';
ini_set('memory_limit', '-1');

$input_dir = getcwd();
$output_root = dirname($input_dir) . DIRECTORY_SEPARATOR . "Processed" . DIRECTORY_SEPARATOR . basename($input_dir) . DIRECTORY_SEPARATOR;
define('LOGFILE' , $input_dir . DIRECTORY_SEPARATOR . "tagging.log");

$json_file = "dat" . DIRECTORY_SEPARATOR . "metatags.json";
if (file_exists($json_file)){
    $metadatajson = file_get_contents($json_file);
} else {
    die("Unable to read $json_file");
}
$metadata = json_decode($metadatajson);
// tesseract imagename basename
$totalentrycount = count($metadata);
echo("\n\nStarting Alchemy tagging script...\n");
echo("\n$totalentrycount total entries found in DB\n\n");
$completed_file_ids = [];

function write_logentry($entrytext){
	$date = new DateTime();
	$log = fopen(LOGFILE, "a");
	$entry = "\n" . $date->format('Y-m-d H:i:s') . " " . $entrytext;
	fwrite($log, $entry);
	fclose($log);
}
function validate_entry($entry, $input_dir, $output_root){
    if (!isset($entry->FOLDER)){
        return false;
    }
    $folder = str_replace('\\', DIRECTORY_SEPARATOR , $entry->FOLDER);
    if (!isset($entry->{'File Name'})){
        $message = "Entry " . $entry->ID . " has no file reference.\n";
        print_r($message);
        $errors_file = fopen(ERRORS, "a");
        $line_found = false;
        while($buffer = fgets($errors_file, 5000)){
            if ($buffer == $message){
                $line_found = true;
            }
        }
        if (!$line_found){
            fwrite($errors_file, $message);
        }
        fclose($errors_file);
        return false;
    }
    $file = $input_dir . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $entry->{'File Name'};
    if (is_dir($file)){
        // Sometimes the actual file is in a subdir with the same name as the file.
        $file = $input_dir . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $entry->{'File Name'} . DIRECTORY_SEPARATOR . $entry->{'File Name'};
    }
    if (!file_exists($file)){
        $message = "Missing file in entry id " . $entry->ID . ": " . $folder . DIRECTORY_SEPARATOR . $entry->{'File Name'} . "\n";
        print_r($message);
        write_logentry($message);
        return false;
    } else {
        return true;
    }
}
function validate_file($entry, $input_dir, $output_root){
    // Validate actual found file against metadata to prevent tagging the wrong file.
    die(var_dump($entry));
    $file = $entry->FILE;
    $actual_file_exists = file_exists($file);
    $actual_filedate = filemtime($file);
    $actual_filesize = filesize($file);
    if ($actual_file_exists && ($actual_filesize == $entry->SIZE) && ($actual_filedate == $entry->DATE)){
        return true;
    }
    return false;

}
function check_if_output_file_exists($entry, $output_root){
    $folder = str_replace('\\', DIRECTORY_SEPARATOR , $entry->FOLDER);
    if (isset($entry->{'File Name'})){
        $output_file = str_replace(".tif", ".pdf", $output_root . $folder . DIRECTORY_SEPARATOR . $entry->{'File Name'});
        if (file_exists($output_file)){
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function add_keyword_tag($tag, $file){
    echo("  Adding $tag.\n");
    // $exif_cmd = EXIFTOOL . " " . escapeshellarg($file) . " -ignoreMinorErrors -overwrite_original -keywords+='" . $tag . "'";
    $cmd = EXIFTOOL . " " . escapeshellarg($file) . " -ignoreMinorErrors -overwrite_original -subject+=\"" . $tag . "\"";
    // echo($cmd . "\n");
    exec($cmd);
}
function process_entry($entry, $input_dir, $output_root){
    $start_time = microtime(true);
    $folder = str_replace('\\', DIRECTORY_SEPARATOR , $entry->FOLDER);
    $file = $input_dir . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $entry->{'File Name'};
    $out_folder = $output_root . $folder;
    $path_parts = pathinfo($file);
    echo "Processing entry ID:" . $entry->ID . "\n";
    if (!file_exists($out_folder)){
        echo "Making new folder $out_folder\n";
        mkdir($out_folder, 0777, true);
    }
    if (is_dir($file)){
        $subfile = $file . DIRECTORY_SEPARATOR . $path_parts['filename'] . "." . $path_parts['extension'];
        if (!file_exists($subfile)){
            write_logentry("Error: Metadata for item ID: " . $entry->ID . " specifies file as " . $file . " but no such file exists! Also checked " . $subfile);
            return;
        } else {
            $file = $subfile;
        }
    }
    if (!file_exists($file)){
        write_logentry("Error: Metadata for item ID:" . $entry->ID . " specifies file as " . $file . " - but no such file exists!");
        return;
    }
    $out_file = $out_folder . DIRECTORY_SEPARATOR . $path_parts['filename'] . ".pdf";
    if (!file_exists($out_file)){
        echo("Input File: " . $file . "\n");
        $ocrfile = $path_parts['dirname'] . DIRECTORY_SEPARATOR . $path_parts['filename'] . ".ocr";
        // Try to dig deeper for the ocr file - sometimes they are stored inside a folder with the same name as the file.
        if (!file_exists($ocrfile)){
            $ocrfile = $path_parts['dirname'] . DIRECTORY_SEPARATOR . $path_parts['filename'] . "." . $path_parts['extension'] . DIRECTORY_SEPARATOR . $path_parts['filename'] . ".ocr";
        }
        // Run Tesseract to do ORC on file
        // echo("Running OCR on image... \n");
        // $cmd = TESSERACT . " " . escapeshellarg($file) . " " . escapeshellarg($ocrfile);
        // exec($cmd);
        // $ocrtextfile = $ocrfile . ".ocr";
        // rename file that comes out of tesseract to .ocr
        // if (file_exists($ocrtextfile)){
        //     echo("  [OCR OK]\n");
        // } else {
        //     die("[Failed to produce OCR txt $ocrtextfile");
        // }
        // Convert file to PDF if it is a TIFF file
        if ($path_parts['extension'] == "tif" || $path_parts['extension'] == "TIF"){
            echo("Converting TIFF to PDF... \n");
            $pdf = $path_parts['dirname']. DIRECTORY_SEPARATOR . $path_parts['filename'] . ".pdf";
            if (PHP_OS === "WINNT"){
                $cmd = CONVERT . " convert " . escapeshellarg($file) . " " . escapeshellarg($pdf);
            } else {
                $cmd = CONVERT . " " . escapeshellarg($file) . " " . escapeshellarg($pdf);
            }
            exec($cmd);
            if (file_exists($pdf)){
                echo("  [PDF OK]\n");
            } else {
                echo("[Failed to produce pdf!] $pdf\n");
                write_logentry("! Failed to produce pdf: $pdf !");
                return false;
            }
            // Add OCR text to doc as metadata if it exists
            if (file_exists($ocrfile)){
                echo("Found OCR text. Adding it to file description metadata.\n");
                $cmd = EXIFTOOL . " " . escapeshellarg($pdf) . " -ignoreMinorErrors -overwrite_original \"-imagedescription<=" . $ocrfile . "\"";
                exec($cmd);

            }
        }
        echo("Tagging image with metadata extracted from .dat file...\n");
        $doctitle = basename($pdf);
        echo("  Adding document title: $doctitle.\n");
        $exif_cmd = EXIFTOOL . " " . escapeshellarg($pdf) . " -ignoreMinorErrors -overwrite_original -title=\"" . $doctitle . "\"";
        exec($exif_cmd);
        // Add the parts of the path to metadata as keywords
        $keywords = explode(DIRECTORY_SEPARATOR, $folder);
        // First whack the keywords to prevent duplicates
        // exec(EXIFTOOL . " '" . $pdf . "' -ignoreMinorErrors -overwrite_original -keywords=''");
        foreach($keywords as $keyword){
            add_keyword_tag($keyword, $pdf);
        }
        // Add practice number
        if (isset($entry->{'Practice Number'})){
            $tag = "Practice No: " . $entry->{'Practice Number'};
            add_keyword_tag($tag, $pdf);
        }
        // Add doctor's name
        if (isset($entry->{'Doctors Name'})){
            $tag = "Doctor: " . $entry->{'Doctors Name'};
            add_keyword_tag($tag, $pdf);
        }
        // Add surname
        if (isset($entry->{'Surname'})){
            $tag = "Surname: " . $entry->{'Surname'};
            add_keyword_tag($tag, $pdf);
        }
        // Add surname/Groupname
        if (isset($entry->{'Surname/Group_Name'})){
            $tag = "Surname/Group_Name: " . $entry->{'Surname/Group_Name'};
            add_keyword_tag($tag, $pdf);
        }
        // Old Number
        if (isset($entry->{'Old Number'})){
            $tag = "Old Number: " . $entry->{'Old Number'};
            add_keyword_tag($tag, $pdf);
        }
        // Number
        if (isset($entry->{'Number'})){
            $tag = "Number: " . $entry->{'Number'};
            add_keyword_tag($tag, $pdf);
        }
        // NAS
        if (isset($entry->{'NAS'})){
            $tag = "NAS: " . $entry->{'NAS'};
            add_keyword_tag($tag, $pdf);
        }
        // // Declared Record - always seems to be No
        // if (isset($entry->{'Declared Record'})){
        //     $tag = "Declared Record: " . $entry->{'Declared Record'};
        //     add_keyword_tag($tag, $pdf);
        // }
        // Initial
        if (isset($entry->{'Initials'})){
            $tag = "Initials: " . $entry->{'Initials'};
            add_keyword_tag($tag, $pdf);
        }
        // First Names
        if (isset($entry->{'First Names'})){
            $tag = "First Names: " . $entry->{'First Names'};
            add_keyword_tag($tag, $pdf);
        }
        // Document Type
        if (isset($entry->{'Document Type'})){
            $tag = "Type: " . $entry->{'Document Type'};
            add_keyword_tag($tag, $pdf);
        }
        // FICA No
        if (isset($entry->{'Fica No'})){
            $tag = "Fica No: " . $entry->{'Fica No'};
            add_keyword_tag($tag, $pdf);
        }
        // ID No
        if (isset($entry->{'ID_No'})){
            $tag = "ID No: " . $entry->{'ID_No'};
            add_keyword_tag($tag, $pdf);
        }
        // Batch
        if (isset($entry->{'BATCH NO'})){
            $tag = "Batch No: " . $entry->{'BATCH NO'};
            add_keyword_tag($tag, $pdf);
        }
        // Sub-Batch
        if (isset($entry->{'SubBatch'})){
            $tag = "SubBatch: " . $entry->{'SubBatch'};
            add_keyword_tag($tag, $pdf);
        }
        // SH Number
        if (isset($entry->{'SH Number'})){
            $tag = "SH Number: " . $entry->{'SH Number'};
            add_keyword_tag($tag, $pdf);
        }
        // Alt SH Number
        if (isset($entry->{'Alt SH Number'})){
            $tag = "Alt SH Number: " . $entry->{'Alt SH Number'};
            add_keyword_tag($tag, $pdf);
        }
        // Trade Mark Name
        if (isset($entry->{'Trade Mark Name'})){
            $tag = "Trade Mark Name: " . $entry->{'Trade Mark Name'};
            add_keyword_tag($tag, $pdf);
        }
        // FCTEC Archive
        if (isset($entry->{'FCTEC Archive'})){
            $tag = "FCTEC Archive: " . $entry->{'FCTEC Archive'};
            add_keyword_tag($tag, $pdf);
        }
        // Trademark Class(es)
        if (isset($entry->{'Trademark Class(es)'})){
            $tag = "Trademark Class(es): " . $entry->{'Trademark Class(es)'};
            add_keyword_tag($tag, $pdf);
        }
        // Country of Registration
        if (isset($entry->{'Country of Registration'})){
            $tag = "Country of Registration: " . $entry->{'Country of Registration'};
            add_keyword_tag($tag, $pdf);
        }
        // FCTEC Batch Nr
        if (isset($entry->{'FCTEC Batch Nr'})){
            $tag = "FCTEC Batch Nr: " . $entry->{'FCTEC Batch Nr'};
            add_keyword_tag($tag, $pdf);
        }
        // FCTEC Batch Nr
        if (isset($entry->{'Retrieval Reference'})){
            $tag = "Retrieval Reference: " . $entry->{'Retrieval Reference'};
            add_keyword_tag($tag, $pdf);
        }
        // Matched TM Name
        if (isset($entry->{'Matched TM Name'})){
            $tag = "Matched TM Name: " . $entry->{'Matched TM Name'};
            add_keyword_tag($tag, $pdf);
        }
        // Add file date to metadata
        if (isset($entry->{'File Date'})){
            $filedate = $entry->{'File Date'};
            echo("  Adding date $filedate.\n");
            $exif_cmd = EXIFTOOL . " " . escapeshellarg($pdf) . " -ignoreMinorErrors -overwrite_original -date=\"" . $filedate ."\"";
            exec($exif_cmd);
        }
        if (isset($entry->{'Document Date'})){
            $docdate = $entry->{'Document Date'};
            $tag = "Document Date: " . $docdate;
            add_keyword_tag($tag, $pdf);
        }
        if (isset($entry->OCR)){
            echo("Entry refers to OCR text\n");
            if (file_exists($ocrfile)){
                echo("Found OCR text file. Adding it to file description metadata.\n");
                $cmd = EXIFTOOL . " " . escapeshellarg($pdf) . " -ignoreMinorErrors -overwrite_original \"-imagedescription<=" . $ocrfile . "\"";
                exec($cmd);
            } else {
                write_logentry("Error: Entry ID: " . $entry->ID . ". Specified OCR file $ocrfile not found!\n");
            }
        }
        $end_time = microtime(true);
        $elapsed_time = $end_time - $start_time;
        echo("File took " . round($elapsed_time, 2) . " seconds to process.\n");
        echo("Output File: $out_file\n");
        if ($path_parts['extension'] == "tif" || $path_parts['extension'] == "TIF"){
            rename($pdf, $out_file); // Moves PDF to new location
        }
        if ($path_parts['extension'] == "pdf"){
            copy($pdf, $out_file);
        }
        if (file_exists($out_file)){
            $message = "Stored file: " . $out_file;
            write_logentry($message);
            return true;
        }
        return false;
    }
}
$entries_to_process = [];
$valid_entries_count = 0;
$errored_entries_count = 0;
$succeeded_entries_count = 0;
//  First loop though entire DB to see how far we are
print_r("Validating DB entries...\n");
for ($i=0; $i < $totalentrycount; $i++) { 
    $entry = $metadata[$i];
    $entry_validates = validate_entry($entry, $input_dir, $output_root);
    if ($entry_validates){
        $valid_entries_count++;
        $file_already_done = check_if_output_file_exists($entry, $output_root);
        if (!$file_already_done){
            $entries_to_process[] = $entry->ID;
        }
    }
}
print_r("\n" . $valid_entries_count . " valid entries found.\n");

//  Then start tagging
print_r("\nTagging files...\n");
while (count($entries_to_process) > 1){
    $selected_entry_index = rand(0, $totalentrycount - 1);
    $selected_entry = $metadata[$selected_entry_index];
    $selected_entry_id = $selected_entry->ID;
    if (!in_array($selected_entry_id, $entries_to_process)){
        continue;
    }
    $output_file_exists = check_if_output_file_exists($selected_entry, $output_root);
    if ($output_file_exists){
        $file_verified = validate_file($selected_entry);
        $entries_to_process_id = array_search($selected_entry_id, $entries_to_process);
        unset($entries_to_process[$entries_to_process_id]);
    } else {
        // Process the entry
        $result = process_entry($selected_entry, $input_dir, $output_root);
        $entries_to_process_id = array_search($selected_entry_id, $entries_to_process);
        unset($entries_to_process[$entries_to_process_id]);
        // Check again if any more files have been done while we were busy
        foreach($metadata as $entry){
            $file_already_done = check_if_output_file_exists($entry, $output_root);
            if ($file_already_done){
                $entry_done_id = $entry->ID;
                $entries_to_process_id = array_search($entry_done_id, $entries_to_process);
                unset($entries_to_process[$entries_to_process_id]);
            }
        }
        $remaining_entries_count = count($entries_to_process);
        $completed_file_total = $valid_entries_count - $remaining_entries_count;
        $completion_percent = round($completed_file_total / $valid_entries_count * 100, 2);
        if ($result){
            print_r("Entry conversion successful.\n");
            $succeeded_entries_count++;
        } else {
            $errored_entries_count++;
            print_r("Entry failed. See errors log.\n");
        }
        echo("\n$completed_file_total files of $valid_entries_count completed ($completion_percent%).\n");
    }
}
print_r("Script complete.\nValid entries: " . $valid_entries_count . "\nFiles converted: " . $succeeded_entries_count . ".\nEntry errors: " . $errored_entries_count);
?>