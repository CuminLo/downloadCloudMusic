<?php
require_once __DIR__ . '/vendor/autoload.php';

use CuminLo\Index;

php_sapi_name() !== 'cli' ? exit('不支持') : '' ;

if ($argc <= 1) {
    exit('参数不正确' . PHP_EOL);
}

$options = getopt('u:o:', [
    'url:',
    'output:',
    'metadata::',
    'process::',
]);

$isMetadata      = $options['metadata'] ?? false; //是否需要添加元数据信息
$process         = $options['process'] ?? 1;//进程数

$url = $options['url'] ?? $options['u'] ?? null;
if (!$url) {
    //todo....
    die;
}

$downloadPath = $options['ouput'] ?? $options['o'] ?? null;
if (!$downloadPath) {
    $downloadPath = __DIR__ . '/Music';
}

$face = new Index($downloadPath);

if (is_string($url)) {
    $url = [$url];
}

$face
    ->setMetadata(boolval($isMetadata))
    ->setProcess(intval($process))
    ->download($url);
