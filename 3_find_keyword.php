<?php

if (empty($argv[1])) {
    die('please input keyword');
}

$moreKeywords = array();
foreach ($argv AS $k => $v) {
    if ($k > 1) {
        $v = trim($v);
        if (!empty($v)) {
            $moreKeywords[] = $v;
        }
    }
}

$outputPath = __DIR__ . '/cache/output';
if (!file_exists($outputPath)) {
    mkdir($outputPath, 0777, true);
}

$result = fopen($outputPath . '/' . $argv[1] . '.csv', 'w');
fputcsv($result, array(
    '日期', '標題', '網址', '內容'
));

foreach (glob(__DIR__ . '/cache/news/*/*/*.gz') AS $gzFile) {
    $lines = array();
    exec("/bin/zfgrep --line-number {$argv[1]} $gzFile", $lines);
    if (!empty($lines)) {
        $file = new SplFileObject('compress.zlib://' . $gzFile);
        foreach ($lines AS $line) {
            $pos = strpos($line, ':');
            $lineNumber = substr($line, 0, $pos);
            $lineNumber -= 1;
            if ($lineNumber % 3 === 0) {
                //matched in metadata, skip
                continue;
            }
            if ($lineNumber % 3 === 2) {
                //matched in body
                $lineNumber -= 2;
            } else {
                //matched in title
                $lineNumber -= 1;
            }
            $file->seek($lineNumber);
            $meta = $file->current();
            $file->seek($lineNumber + 1);
            $title = trim(str_replace('"', '', $file->current()));
            $file->seek($lineNumber + 2);
            $body = strip_tags(trim(str_replace(array('"', '\\n'), array('', ' '), $file->current())));
            if (substr($meta, 0, 1) === '{') {
                $meta = json_decode($meta);
                $moreKeywordsCheck = true;
                foreach ($moreKeywords AS $moreKeyword) {
                    if (false === strpos($title, $moreKeyword)) {
                        $moreKeywordsCheck = false;
                    }
                }
                if ($moreKeywordsCheck) {
                    fputcsv($result, array(
                        date('Y-m-d H:i:s', $meta->created_at), $title, $meta->url, $body
                    ));
                }
            }
        }
    }
}
