<?php
require_once __DIR__ . '/vendor/autoload.php';

use CuminLo\Index;

php_sapi_name() !== 'cli' ? exit('不支持') : '' ;

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

$face = new Index($downloadPath);

$face->download($url);
