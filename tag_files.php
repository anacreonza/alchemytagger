<?php
/*
Metadata mapping

'Document Title'    = EXIF/title
'File Date'         = EXIF/date
'Practice Number'   = keyword
'Doctor's Name'     = keyword
'FOLDER'            = broken up into keywords
*/

if (PHP_OS === "WINNT"){
    $exif_path_string = "c:\Program Files\exiftool.exe";
    $tess_path_string = "c:\Program Files\tesseract.exe";
    $magick_path_string = "c:\Program Files\convert.exe";
} else {
    $exif_path_string = "/usr/local/bin/exiftool";
    $tess_path_string = "/usr/local/bin/tesseract";
    $magick_path_string = "/usr/local/bin/convert";
}
define('EXIFTOOL', escapeshellarg($exif_path_string));
define('TESSERACT', escapeshellarg($tess_path_string));
define('CONVERT', escapeshellarg($magick_path_string));

$input_dir = getcwd();
$json_file = "dat/metatags.json";
if (file_exists($json_file)){
    $metadatajson = file_get_contents("dat/metatags.json");
} else {
    die("Unable to read $json_file");
}
$metadata = json_decode($metadatajson);
// tesseract imagename basename
$entrycount = count($metadata);
echo("$entrycount entries in metadata.json\n");
$filecounter = 1;

function add_keyword_tag($tag, $file){
    echo("  Adding $tag.\n");
    $exif_cmd = EXIFTOOL . " '" . $file . "' -ignoreMinorErrors -overwrite_original -keywords+='" . $tag . "'";
    $exif_cmd = EXIFTOOL . " '" . $file . "' -ignoreMinorErrors -overwrite_original -subject+='" . $tag . "'";
    exec($exif_cmd);
}

foreach ($metadata as $entry) {
    
    $folder = str_replace('\\', DIRECTORY_SEPARATOR , $entry->FOLDER);
    $file = $input_dir . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $entry->{'File Name'};
    $out_folder = $input_dir . DIRECTORY_SEPARATOR . "tagged" . DIRECTORY_SEPARATOR . $folder;
    if (!file_exists($out_folder)){
        echo "Making new folder $out_folder";
        mkdir($out_folder, 0777, true);
    }
    if (!file_exists($file)){
        echo("Source file not found: $file\n");
        $filecounter++;
        continue;
    }
    $out_file = $out_folder . DIRECTORY_SEPARATOR . $path_parts['filename'] . ".pdf";
    if (!file_exists($out_file)){
        echo("\n\nProcessing file $filecounter of $entrycount\n");
        echo("Input File: " . $file . "\n");
        $path_parts = pathinfo($file);
        $ocrfile = $path_parts['dirname'] . DIRECTORY_SEPARATOR . $path_parts['filename'];
        // Run Tesseract to do ORC on file
        echo("Running OCR on image...\n");
        $tesseract_cmd = TESSERACT . " '" . $file . "' '" . $ocrfile . "'";
        exec($tesseract_cmd);
        // Convert file to PDF
        echo("Converting TIFF to PDF...\n");
        $pdf = $path_parts['filename'] . ".pdf";
        exec(CONVERT . " -quiet '" . $file . "' '" . $pdf . "'");
        // Add OCR text to doc as metadata
        echo("Adding OCR text to file description metadata.\n");
        $ocrfilename = $ocrfile . ".txt";
        $exif_cmd = EXIFTOOL . " '" . $pdf . "' -ignoreMinorErrors -overwrite_original '-imagedescription<=" . $ocrfilename . "'";
        exec($exif_cmd);
        // Now delete OCR file as we no longer need it.
        unlink($ocrfilename);
        // Add document title to metadata
        echo("Tagging image with metadata extracted from .dat file...\n");
        $doctitle = $pdf;
        echo("  Adding document title: $doctitle.\n");
        $exif_cmd = EXIFTOOL . " '" . $pdf . "' -ignoreMinorErrors -overwrite_original -title='" . $pdf . "'";
        exec($exif_cmd);
        // Add the parts of the path to metadata as keywords
        $keywords = explode(DIRECTORY_SEPARATOR, $folder);
        // First whack the keywords to prevent duplicates
        // exec(EXIFTOOL . " '" . $pdf . "' -ignoreMinorErrors -overwrite_original -keywords=''");
        foreach($keywords as $keyword){
            add_keyword_tag($keyword, $pdf);
        }
        // Add practice number
        if ($entry->{'Practice Number'}){
            $tag = "Practice No: " . $entry->{'Practice Number'};
            add_keyword_tag($tag, $pdf);
        }
        // Add doctor's name
        if ($entry->{'Doctors Name'}){
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
        // Declared Record
        if (isset($entry->{'Declared Record'})){
            $tag = "Declared Record: " . $entry->{'Declared Record'};
            add_keyword_tag($tag, $pdf);
        }
        // Add file date to metadata
        $filedate = $entry->{'File Date'};
        echo("  Adding date $filedate.\n");
        $exif_cmd = EXIFTOOL . " '" . $pdf . "' -ignoreMinorErrors -overwrite_original -date='" . $filedate ."'";
        exec($exif_cmd);
        echo("Output File: $out_file\n");
        rename($pdf, $out_file);
        $filecounter++;
    } else {
        echo("File already processed: $out_file \n");
        $filecounter++;
    }
}

?>