# selective/archive-bomb-scanner

Archive (ZIP) and PNG bomb scanner for PHP.

[![Latest Version on Packagist](https://img.shields.io/github/release/selective-php/archive-bomb-scanner.svg?style=flat-square)](https://packagist.org/packages/selective/archive-bomb-scanner)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/selective-php/archive-bomb-scanner/master.svg?style=flat-square)](https://travis-ci.org/selective-php/archive-bomb-scanner)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/selective-php/archive-bomb-scanner.svg?style=flat-square)](https://scrutinizer-ci.com/g/selective-php/archive-bomb-scanner/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/quality/g/selective-php/archive-bomb-scanner.svg?style=flat-square)](https://scrutinizer-ci.com/g/selective-php/archive-bomb-scanner/?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/selective/archive-bomb-scanner.svg?style=flat-square)](https://packagist.org/packages/selective/archive-bomb-scanner/stats)


## Features

* Detection of the ZIP archive bombs
* No dependencies
* Very fast

### Supported formats

* ZIP
* PNG

## Requirements

* PHP 7.2+

## Installation

```
composer require selective/archive-bomb-scanner
```

## Usage

### Scan ZIP file

```php
use Selective\ArchiveBomb\Scanner\ArchiveBombScanner;
use Selective\ArchiveBomb\Engine\ZipBombEngine;
use SplFileObject;

$file = new SplFileObject('42.zip');

$scanner = new ArchiveBombScanner();
$scanner->addEngine(new ZipBombEngine());

$scannerResult = $scanner->scanFile($file);

if ($scannerResult->isBomb()) {
    echo 'Archive bomb detected!';
} else {
    echo 'File is clean';
}
```

### Scan in-memory file

```php
use Selective\ArchiveBomb\ArchiveBombScanner;
use Selective\ArchiveBomb\Engine\ZipBombEngine;
use SplTempFileObject;

$file = new SplTempFileObject();

$file->fwrite('my file content');

$scanner = new ArchiveBombScanner();
$scanner->addEngine(new ZipBombEngine());

$isBomb = $detector->scanFile($file)->isBomb(); // true or false
```

### Scan PNG file

```php
use Selective\ArchiveBomb\Scanner\ArchiveBombScanner;
use Selective\ArchiveBomb\Engine\PngBombEngine;
use SplFileObject;

$file = new SplFileObject('example.png');

$scanner = new ArchiveBombScanner();
$scanner->addEngine(new PngBombEngine());

$scannerResult = $scanner->scanFile($file);

if ($scannerResult->isBomb()) {
    echo 'PNG bomb detected!';
} else {
    echo 'File is clean';
}
```

## License

* MIT
