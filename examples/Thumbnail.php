<?php

require __DIR__ . '/../vendor/autoload.php';

use Janmensik\Jmlib\Thumbnail;

$thumb = new Thumbnail();
$url = $thumb->from('test.jpg')
    ->width(200)
    ->height(100)
    ->generate();

$thumb->debugPrint('raw');

/*

Test images:
https://placehold.co/600x400/png
https://placehold.co/600x400/gif
https://placehold.co/600x400/jpeg
*/