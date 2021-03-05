# A tagger script for exports from Alchemy content databases.

This script processes the .dat files produced by an export from an Alchemy database.

1. First you must convert the .dat file to a JSON file to make it easier to process. Use the `create_metadata_json.php` script to do that.
2. Then run the `tag_files.php` script.

## Requirements

This script makes use of a few OSS components to process the file:

1. PHP (version 7 upwards)
2. Tesseract - this is used to perform OCR on the files to extract any readable text.
3. ImageMagick - this is used to convert the TIFF files to PDFs
4. ExifTool - this is used to embed the metadata into the final output files.

The locations of these components is specified in the `tag_files.php` script.
