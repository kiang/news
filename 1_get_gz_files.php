<?php

include __DIR__ . '/config.php';
$listFile = __DIR__ . '/cache/list';
$filePath = __DIR__ . '/cache/news';
if (!file_exists($listFile) || date('Ymd', filemtime($listFile)) !== date('Ymd')) {
    file_put_contents($listFile, file_get_contents($listUrl));
}
$list = file_get_contents($listFile);
$maxUrlLength = 0;

$newFiles = array();
$pos = strpos($list, 'href="');
while (false !== $pos) {
    $pos += 6;
    $posEnd = strpos($list, '"', $pos);
    $url = substr($list, $pos, $posEnd - $pos);
    if ((false !== strpos($url, 'dl=0')) && (false === strpos($url, '-diff'))) {
        $urlParts = pathinfo($url);
        $dateParts = array(
            substr($urlParts['filename'], 0, 4),
            substr($urlParts['filename'], 4, 2),
            substr($urlParts['filename'], 6, 2),
        );
        $targetFile = "{$filePath}/" . implode('/', $dateParts) . ".gz";
        if (!file_exists(dirname($targetFile))) {
            mkdir(dirname($targetFile), 0777, true);
        }
        if (!file_exists($targetFile) || filesize($targetFile) === 0) {
            $newFiles[] = $targetFile;
            echo "getting {$urlParts['dirname']}/{$urlParts['filename']}.gz?dl=1\n";
            file_put_contents($targetFile, file_get_contents("{$urlParts['dirname']}/{$urlParts['filename']}.gz?dl=1"));
        }
    }
    $pos = strpos($list, 'href="', $posEnd);
}
file_put_contents("{$filePath}/newfiles", implode("\n", $newFiles));
