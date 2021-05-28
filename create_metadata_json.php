<?php
ini_set('memory_limit', '400M');
$search_cmd = "find ./dat -type f -name 'aa_folders_folders_folders.dat'";

$datfile = exec($search_cmd);
$datdir = dirname($datfile);
$outdata = [];

if ($datfile){
    echo "Processing dat file at $datfile in folder $datdir";
    $filehandle = fopen($datfile, "r");
    if($filehandle){
        while(($line = fgets($filehandle)) !== false) {
            if (strpos($line, "ID: " ) !== false){
                if (isset($newentry)){
                    $outdata[] = $newentry;
                }
                $newentry = [];
                $id = str_replace("ID:", '', $line);
                $newentry['ID'] = utf8_encode(trim($id));
            } else {
                $colonpos = strpos($line, ':');
                $key = utf8_encode(substr($line, 0, $colonpos));
                // remove pesky dollar signs from key names
                $key = str_replace('$', '', $key);
                $value = trim(substr($line, $colonpos+1, strlen($line)));
                if ($value){
                    $newentry[$key] = utf8_encode($value);
                }
            }

        }
        fclose($filehandle);
    }
}
$outjson = json_encode($outdata, JSON_PRETTY_PRINT);
$metatags_file_handle = fopen($datdir . "/metatags.json", "w");
fwrite($metatags_file_handle, $outjson);
fclose($metatags_file_handle);
?>