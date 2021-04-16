<?php
ini_set('memory_limit', '-1');
$json_file = "dat" . DIRECTORY_SEPARATOR . "metatags.json";
if (file_exists($json_file)){
    $metadatajson = file_get_contents($json_file);
} else {
    die("Unable to read $json_file");
}
echo("Scanning $json_file for duplicates.");
$metadata = json_decode($metadatajson);
$data = [];
foreach($metadata as $current_entry){
    $current_details = [];
    if (isset($current_entry->{'FILE'})){
        $current_details['path'] = $current_entry->{'FILE'};
        $current_details['filename'] = $current_entry->{'File Name'};
        array_push($data, $current_details);
    }
}
$duplicates = [];
foreach($data as $item){
    $current_filename = $item['filename'];
    $instances = [];
    foreach($data as $i){
        $instances = [];
        $selected_filename = $i['filename'];
        $selected_filepath = $i['path'];
        if ($selected_filename === $current_filename){
            array_push($instances, $selected_filepath);
        }
    }
    if (count($instances) > 1){
        array_push($duplicates, $instances);
    }
}
var_dump($duplicates);
?>