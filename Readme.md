# A tagger script for exports from Alchemy content databases.

This script tags the files exported from an FirstCoast Alchemy database.

## Requirements

This script makes use of a few OSS components to process the file:

1. PHP (version 7 upwards)
2. Tesseract - this is used to perform OCR on the files to extract any readable text. (currently disabled for performance reasons).
3. ImageMagick - this is used to convert the TIFF files to PDFs.
4. ExifTool - this is used to embed the metadata into the final output files.

The locations of these components is specified in the `Config.php` file.

First convert the Alchemy DAT file into an SQLite DB with the `Build_db.php` script. Then place that DB into a subdirectory of the exported files directory named DB.

Start the tagging by running `tag_files_from_db.php` and specifying the root directory of the exported files.

The script picks a random entry in the database instead of processing the files sequentially - which allows many copies of the script to be run simultaneously. The record of which entries are completed is kept in the database.

TODO:
- Make script add additional metadata to a file when multiple entries refer to the same file.
- Harden script with all DB exports, many have inconsistent references, duplicate files, duplicate entries etc.