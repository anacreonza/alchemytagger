<?php
namespace App;

class Config {
    public function setConfig(){
        if (PHP_OS === "WINNT"){
            define('EXIFTOOL', "exiftool.exe");
            define('TESS', "C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe");
            define('CONVERT', "C:\\Program Files\\ImageMagick-7.0.10-Q16-HDRI\\magick.exe");
        } else {
            define('EXIFTOOL', "/usr/local/bin/exiftool");
            define('TESS', "/usr/local/bin/tesseract");
            define('CONVERT', "/usr/local/bin/convert");
        }
    }
}