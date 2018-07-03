# ISO9660 Stream Wrapper for PHP

PHP Stream Wrapper for ISO9660 files (.iso files)

[![Build Status](https://travis-ci.org/vdechenaux/iso-9660.svg?branch=master)](https://travis-ci.org/vdechenaux/iso-9660)

## Usage

Install using Composer:

```
$ composer require vdechenaux/iso-9660
```

Register the Stream Wrapper:

```php
\ISO9660\StreamWrapper::register();
```

Use it, with any function which supports stream wrappers:

```php
// Get the content
file_get_contents('iso9660://path/myIsoFile.iso#song.mp3');

// Get the size
filesize('iso9660://path/myIsoFile.iso#song.mp3');

// Check if ISO file contains a file
file_exists('iso9660://path/myIsoFile.iso#song.mp3');

// List files
$iterator = new RecursiveTreeIterator(new RecursiveDirectoryIterator('iso9660://myIsoFile.iso#'));
foreach ($iterator as $entry) {
    echo $entry.PHP_EOL;
}

// Get a stream on a file contained in the ISO file
// Here, a PNG file
$stream = fopen('iso9660://myIsoFile.iso#image.png', 'r');
fseek($stream, 1); // Skip 1st byte
$header = fread($stream, 3); // We should get "PNG"

// Etc...
```

You can use the `\ISO9660\Reader` class if you don't want to use native PHP functions.

## License

This project is released under [the MIT license](LICENSE).
