<?php
/*
Metadata mapping

'Document Title'    = EXIF/title
'File Date'         = EXIF/date
'Practice Number'   = keyword
'Doctor's Name'     = keyword
'FOLDER'            = broken up into keywords
*/
ini_set('memory_limit', '-1');

if (PHP_OS === "WINNT"){
    $exif_path_string = "exiftool.exe";
    $tess_path_string = "C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe";
    $magick_path_string = "C:\\Program Files\\ImageMagick-7.0.10-Q16-HDRI\\magick.exe";
} else {
    $exif_path_string = "/usr/local/bin/exiftool";
    $tess_path_string = "/usr/local/bin/tesseract";
    $magick_path_string = "/usr/local/bin/convert";
}
define('EXIFTOOL', escapeshellarg($exif_path_string));
define('TESSERACT', escapeshellarg($tess_path_string));
define('CONVERT', escapeshellarg($magick_path_string));

$input_dir = getcwd();
$output_root = dirname($input_dir) . DIRECTORY_SEPARATOR . "Processed" . DIRECTORY_SEPARATOR . basename($input_dir) . DIRECTORY_SEPARATOR;
define('LOGFILE' , $input_dir . DIRECTORY_SEPARATOR . "tagging.log");
define('MISSINGFILES', $input_dir . DIRECTORY_SEPARATOR . "missing.txt");
if (file_exists(MISSINGFILES)){
    unlink(MISSINGFILES); //whack the existing missing files file in case there is already one.
}
$json_file = "dat" . DIRECTORY_SEPARATOR . "metatags.json";
if (file_exists($json_file)){
    $metadatajson = file_get_contents($json_file);
} else {
    die("Unable to read $json_file");
}
$metadata = json_decode($metadatajson);
// tesseract imagename basename
$entrycount = count($metadata);
echo("\n\nStarting Alchemy tagging script...\n");
echo("\n$entrycount total entries found in DB\n\n");
$completed_file_ids = [];

function write_logentry($entrytext){
	$date = new DateTime();
	$log = fopen(LOGFILE, "a");
	$entry = "\n" . $date->format('Y-m-d H:i:s') . " " . $entrytext;
	fwrite($log, $entry);
	fclose($log);
}
function verify_inputfile($entry, $input_dir){
    $folder = str_replace('\\', DIRECTORY_SEPARATOR , $entry->FOLDER);
    $file = $input_dir . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $entry->{'File Name'};
    if (file_exists($file)){
        return;
    } else {
        $message = "Missing file: " . $folder . DIRECTORY_SEPARATOR . $entry->{'File Name'} . "\n";
        print_r($message);
        $missing_files_file = fopen(MISSINGFILES, "a");
        fwrite($missing_files_file, $message);
        fclose($missing_files_file);
        return;
    }
}
function check_if_output_file_exists($entry, $output_root){
    $folder = str_replace('\\', DIRECTORY_SEPARATOR , $entry->FOLDER);
    $output_file = str_replace(".tif", ".pdf", $output_root . $folder . DIRECTORY_SEPARATOR . $entry->{'File Name'});
    if (file_exists($output_file)){
        return true;
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
    if (!file_exists($out_folder)){
        echo "Making new folder $out_folder";
        mkdir($out_folder, 0777, true);
    }
    if (!file_exists($file)){
        return;
    }
    if (is_dir($file)){
        echo "Error: Specified file is a directory: " . $file . "\n";
        return;
    }
    $path_parts = pathinfo($file);
    $out_file = $out_folder . DIRECTORY_SEPARATOR . $path_parts['filename'] . ".pdf";
    if (!file_exists($out_file)){
        echo("Input File: " . $file . "\n");
        $ocrfile = $path_parts['dirname'] . DIRECTORY_SEPARATOR . $path_parts['filename'];
        // Run Tesseract to do ORC on file
        // echo("Running OCR on image... \n");
        // $cmd = TESSERACT . " " . escapeshellarg($file) . " " . escapeshellarg($ocrfile);
        // exec($cmd);
        // $ocrtextfile = $ocrfile . ".txt";
        // if (file_exists($ocrtextfile)){
        //     echo("  [OCR OK]\n");
        // } else {
        //     die("[Failed to produce OCR txt $ocrtextfile");
        // }
        // Convert file to PDF
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
        // Add OCR text to doc as metadata
        // echo("Adding OCR text to file description metadata.\n");
        // $cmd = EXIFTOOL . " " . escapeshellarg($pdf) . " -ignoreMinorErrors -overwrite_original \"-imagedescription<=" . $ocrtextfile . "\"";
        // exec($cmd);
        // // Now delete OCR file as we no longer need it.
        // // echo("Removing OCR file $ocrtextfile\n");
        // unlink($ocrtextfile);
        // Add document title to metadata
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
        $end_time = microtime(true);
        $elapsed_time = $end_time - $start_time;
        echo("File took " . round($elapsed_time, 2) . " seconds to process.\n");
        echo("Output File: $out_file\n");
        rename($pdf, $out_file);
        if (file_exists($out_file)){
            $message = "Stored file: " . $out_file;
            write_logentry($message);
            return true;
        }
        return false;
    }
}
//  First loop though entire DB to see how far we are
print_r("Validating DB...\n");
for ($i=0; $i < $entrycount; $i++) { 
    verify_inputfile($metadata[$i], $input_dir);
    $result = check_if_output_file_exists($metadata[$i], $output_root);
    if ($result){
        $completed_file_ids[] = $i;
    }
}

//  Then start tagging
print_r("\nTagging items...\n");
while (count($completed_file_ids) < $entrycount){
    $selected_entry_id = rand(0, $entrycount - 1);
    if (in_array($selected_entry_id, $completed_file_ids)){
        continue;
    }
    $entry = $metadata[$selected_entry_id];
    $output_file_exists = check_if_output_file_exists($entry, $output_root);
    if ($output_file_exists){
        $completed_file_ids[] = $selected_entry_id;
    } else {
        $selected_entry = $metadata[$selected_entry_id];
        $result = process_entry($selected_entry, $input_dir, $output_root);
        if ($result){
            $completed_file_ids[] = $selected_entry_id;
            $completed_file_total = count($completed_file_ids);
            $completion_percent = round($completed_file_total / $entrycount * 100, 2);
            echo("\n$completed_file_total files of $entrycount completed ($completion_percent%).\n");
            $title = str_replace('.tif', '.pdf', $selected_entry->{'Document Title'});
        }
    }
}
print_r("Script complete.");
?>