# from https://gist.github.com/kcwu/0d790254d4bdb3299999
import subprocess
import glob
import sys
import os
import csv
import time
import gzip
import json
import re

if 'freebsd' in sys.platform:
    path_zfgrep = '/usr/bin/zfgrep'
    re_linenum = r'(\d+)'
    re_highlight = r'\x1b\[01;31m(.+?)\x1b\[00m'
elif 'linux' in sys.platform:
    path_zfgrep = '/bin/zfgrep'
    re_linenum = r'\x1b\[32m\x1b\[K(\d+)\x1b\[m\x1b\[K\x1b\[36m\x1b\[K'
    re_highlight = r'\x1b\[01;31m\x1b\[K(.+?)\x1b\[m'
else:
    assert 0, 'unknown'

mapping = {}

def load_pattern():
    patterns = []
    for fn in os.listdir('cache/keywords'):
        path = os.path.join('cache', 'keywords', fn)
        reader = csv.reader(file(path))
        for row in reader:
            mapping[row[1]] = row[0]
            patterns.append(row[1])
    pattern = '\n'.join(patterns)
    return pattern

def main():
    pattern = load_pattern()
    pattern_fn = 'cache/pattern.txt'
    with file(pattern_fn, 'w') as f:
        f.write(pattern)

    for path in glob.glob('cache/news/*/*/*.gz'):
        t0 = time.time()

        # don't use .splitlines() due to U+2028
        content = gzip.open(path).read().decode('utf8').split('\n')

        allmeta = {}

        output = subprocess.check_output([
            path_zfgrep,
            '--line-number',
            '--color=always',
            '-f', pattern_fn,
            path,
        ])
        for line in output.splitlines():
            num, text = line.split(':', 1)
            m = re.match(re_linenum, num)
            assert m, 'line num not matched'
            num = m.group(1)

            num = int(num) - 1
            if num % 3 == 0:
                # matched in metadata, skip
                continue

            if num % 3 == 2:
                match_at = 'body'
                entry = num - 2
            else:
                match_at = 'title'
                entry = num - 1

            if entry not in allmeta:
                meta = json.loads(content[entry])
                allmeta[entry] = meta
            else:
                meta = allmeta[entry]

            if 'keywords' not in meta:
                meta['keywords'] = {}

            if 'title' not in meta:
                meta['title'] = content[entry + 1]
                meta['title'] = meta['title'][1:-1]

            for keyword in re.findall(re_highlight, text):
                keyword_id = mapping[keyword]
                if keyword_id in meta['keywords']:
                    continue

                if match_at == 'title':
                    title = content[entry + 1]
                    title = title[1:-1]
                    summary = title
                else:
                    body = content[entry + 2]
                    body = body[1:-1]
                    body = re.sub(r'[\n\r ]', '', body)
                    body = re.sub(r'\\[nr]', '', body)
                    # TODO strip tags
                    #body = re.sub(r'http.*?jpg', '', body)  # this is wrong
                    body = re.sub(r'http[\w._/%-]*?jpg', '', body)
                    idx = body.index(keyword.decode('utf8'))
                    if idx > 40:
                        summary = body[idx-40:idx+40]
                    else:
                        summary = body[:80]

                meta['keywords'][keyword_id] = summary

        for meta in allmeta.values():
            fn = '%s_%s.json' % (meta['created_at'], meta['normalized_crc32'])
            out_path = os.path.join('cache', 'output', fn)
            json.dump(meta, file(out_path, 'w'))


        t1 = time.time()

        print path, t1-t0


if __name__ == '__main__':
    main()
