<?php

require __DIR__ . '/../vendor/autoload.php';

use Janmensik\Jmlib\Thumbnail;

$thumb = new Thumbnail();
$url = $thumb->from('https://www.php.net/images/logos/php-logo.svg')
    ->width(200)
    ->height(100)
    ->generate();
