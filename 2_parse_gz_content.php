<?php

//$start_time = microtime(TRUE);
$outputPath = __DIR__ . '/cache/output';
if (!file_exists($outputPath)) {
    mkdir($outputPath, 0777, true);
}
$keywords = array();
foreach (glob(__DIR__ . '/cache/keywords/*.csv') AS $keywordFile) {
    $fh = fopen($keywordFile, 'r');
    while ($line = fgetcsv($fh, 512)) {
        $keywords[$line[0]] = $line[1];
    }
    fclose($fh);
}
foreach (glob(__DIR__ . '/cache/news/*/*/*.gz') AS $gzFile) {
    echo "processing {$gzFile}\n";
    ob_start();
    readgzfile($gzFile);
    $allNews = ob_get_contents();
    ob_end_clean();
    $nPos = strpos($allNews, '{"id"');
    while (false !== $nPos) {
        $nPosEnd = strpos($allNews, '}', $nPos) + 1;
        $meta = json_decode(substr($allNews, $nPos, $nPosEnd - $nPos), true);
        $nPos = strpos($allNews, '{"id"', $nPosEnd);
        if (false !== $nPos) {
            $content = explode("\n", trim(substr($allNews, $nPosEnd, $nPos - $nPosEnd)));
        } else {
            $content = explode("\n", trim(substr($allNews, $nPosEnd)));
        }
        $meta['keywords'] = array();
        if (count($content) === 2) {
            $meta['title'] = $content[0] = substr($content[0], 1, strlen($content[0]) - 2);
            $content[1] = substr($content[1], 1, strlen($content[1]) - 2);
            $content[1] = strip_tags(str_replace(array('\\n', '\\r', ' '), array('', '', ''), $content[1]));
            $content[1] = preg_replace('/http(.*?)jpg/', '', $content[1]);
            foreach ($keywords AS $keywordId => $keyword) {
                $titlePos = mb_strpos($content[0], $keyword, false, 'utf-8');
                $bodyPos = mb_strpos($content[1], $keyword, false, 'utf-8');
                $summary = '';
                if (false !== $bodyPos) {
                    if ($bodyPos > 40) {
                        $summary = mb_substr($content[1], $bodyPos - 40, 80, 'utf-8');
                    } else {
                        $summary = mb_substr($content[1], 0, 80, 'utf-8');
                    }
                    $meta['keywords'][$keywordId] = $summary;
                } elseif (false !== $titlePos) {
                    $summary = $content[0];
                    $meta['keywords'][$keywordId] = $summary;
                }
            }
        }
        if (!empty($meta['keywords'])) {
            file_put_contents("{$outputPath}/{$meta['created_at']}_{$meta['normalized_crc32']}.json", json_encode($meta));
        }
    }
//    $end_time = microtime(TRUE);
//    echo $end_time - $start_time . "\n";
//    exit();
}