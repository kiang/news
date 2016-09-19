<?php

include __DIR__ . '/config.php';
$listFile = __DIR__ . '/cache/list';
$filePath = __DIR__ . '/cache/news';
$lastYear = 0;
$lastYearUrl = '';

for($i = 2013; $i <= date('Y'); $i ++) {
  $lastYearUrl = $listUrl . $i . '/';
  $list = file_get_contents($lastYearUrl);
  $maxUrlLength = 0;

  $pos = strpos($list, 'href="');
  while (false !== $pos) {
      $pos += 6;
      $posEnd = strpos($list, '"', $pos);
      $url = substr($list, $pos, $posEnd - $pos);
      if (false === strpos($url, '-diff')) {
          $dateParts = array(
              substr($url, 0, 4),
              substr($url, 4, 2),
              substr($url, 6, 2),
          );
          $targetFile = "{$filePath}/" . implode('/', $dateParts) . ".gz";
          if (!file_exists(dirname($targetFile))) {
              mkdir(dirname($targetFile), 0777, true);
          }
          if (!file_exists($targetFile) || filesize($targetFile) === 0) {
              $url = $lastYearUrl . $url;
              echo "getting {$url}\n";
              file_put_contents($targetFile, file_get_contents($url));
          }
      }
      $pos = strpos($list, 'href="', $posEnd);
  }
}
