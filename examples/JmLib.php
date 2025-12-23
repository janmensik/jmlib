<?php

require __DIR__ . '/../vendor/autoload.php';

use Janmensik\Jmlib\JmLib;


echo (JmLib::parseFloat('12.34') . "\r\n"); // Outputs: 12.34
echo (JmLib::parseFloat('1,234.56') . "\r\n"); // Outputs: 1234.56
echo (JmLib::parseFloat('1.234,56') . "\r\n"); // Outputs: 1234.56
echo (JmLib::parseFloat('invalid') . "\r\n"); // Outputs: 0