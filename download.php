<?php

use Face\Face;

if ($argc <= 1) {
    exit('参数不正确' . PHP_EOL);
}

$options = getopt('u:o:', [
    'url:',
    'output:'
]);

$url = $options['url'] ?? $options['u'] ?? null;
if (!$url) {
    //todo....
}

$downloadPath = $options['ouput'] ?? $options['o'] ?? null;
if (!$downloadPath) {
    $downloadPath = __DIR__ . '/Music';
}

include __DIR__ . '/Face.php';

$face = new Face($downloadPath);

$face->download($url);
