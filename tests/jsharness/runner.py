"""Run the block_dimensions JS harness in headless Chromium.

run.sh starts this inside the Chromium image, with the repository mounted read-only; see README.md.

    runner.py                    the suite over amd/src
    runner.py --mutants          the suite, then every mutant in mutants.py
    runner.py --mutants --only ID [ID ...]

Exits 0 only when the suite produces a verdict with no failure and, with --mutants, when every
mutant applies exactly once and reddens a check of a family it names.

Development tooling only: excluded from the release zip through .gitattributes.
"""

import argparse
import concurrent.futures
import html
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import urllib.parse

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(os.path.dirname(os.path.dirname(HERE)), 'amd', 'src')
PAGE = 'file://' + os.path.join(HERE, 'harness.html')
MODULES = ('state.js', 'filter_tabs_nav.js', 'filters.js')
# Virtual time the page may use: the scenarios sleep through debounces and announcement delays.
VIRTUAL_TIME_MS = 120000
# Real time one run may take before it counts as producing no verdict.
TIMEOUT_S = 180


def run_suite(src=None):
    """Run every scenario once, over amd/src or over the directory src.

    Returns (verdict, problem): the verdict the page wrote, or None and why there is none.
    """
    url = PAGE + '?auto=1'
    if src:
        url += '&src=' + urllib.parse.quote('file://' + src.rstrip('/') + '/', safe='')
    profile = tempfile.mkdtemp(prefix='jsharness-profile-')
    command = [
        'chromium', '--headless=new', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage',
        '--no-first-run', '--allow-file-access-from-files', '--user-data-dir=' + profile,
        '--virtual-time-budget=%d' % VIRTUAL_TIME_MS, '--dump-dom', url,
    ]
    try:
        done = subprocess.run(command, capture_output=True, text=True, timeout=TIMEOUT_S)
    except subprocess.TimeoutExpired:
        return None, 'Chromium did not finish within %d s' % TIMEOUT_S
    finally:
        shutil.rmtree(profile, ignore_errors=True)
    # The verdict is the last element of the page; see the auto parameter in harness.html.
    found = re.search(r'<pre id="out">((?:RESULT|RESULT-ERROR) .*?)</pre>', done.stdout, re.S)
    if not found:
        tail = (done.stderr.strip() or done.stdout.strip())[-800:]
        return None, 'no verdict in the page (Chromium exit %d): %s' % (done.returncode, tail)
    text = html.unescape(found.group(1))
    if not text.startswith('RESULT '):
        return None, text[:2000]
    return json.loads(text[len('RESULT '):], strict=False), ''


def family(name):
    """The family of a check: its name up to the first ': '."""
    return name.split(': ', 1)[0]


def print_verdict(label, verdict):
    print('%s: total=%d failed=%d' % (label, verdict['total'], verdict['failed']))
    for failure in verdict['failures']:
        print('  FAIL %s  %s' % (failure['name'], json.dumps(failure.get('detail'))[:300]))


def load_mutants(only):
    sys.path.insert(0, HERE)
    from mutants import MUTANTS
    ids = [m['id'] for m in MUTANTS]
    duplicates = sorted({i for i in ids if ids.count(i) > 1})
    if duplicates:
        raise SystemExit('Duplicate mutant ids: ' + ', '.join(duplicates))
    if only:
        unknown = sorted(set(only) - set(ids))
        if unknown:
            raise SystemExit('Unknown mutant ids: ' + ', '.join(unknown))
        return [m for m in MUTANTS if m['id'] in only]
    return MUTANTS


def make_mutant(mutant, root):
    """Copy amd/src into root/<id> with the substitution applied; return the directory or a problem."""
    if mutant['file'] not in MODULES:
        return None, 'names %s, which the harness does not load' % mutant['file']
    with open(os.path.join(SRC, mutant['file']), encoding='utf-8') as handle:
        source = handle.read()
    count = source.count(mutant['old'])
    if count != 1:
        return None, 'its pattern matches %d times in amd/src/%s, not once' % (count, mutant['file'])
    if mutant['old'] == mutant['new']:
        return None, 'its substitution changes nothing'
    directory = os.path.join(root, mutant['id'])
    os.makedirs(directory)
    for module in MODULES:
        shutil.copy(os.path.join(SRC, module), directory)
    with open(os.path.join(directory, mutant['file']), 'w', encoding='utf-8') as handle:
        handle.write(source.replace(mutant['old'], mutant['new']))
    return directory, ''


def judge(mutant, verdict, problem, families):
    """Return (killed, line) for one mutant run."""
    unknown = [f for f in mutant['reddens'] if f not in families]
    if unknown:
        return False, 'BAD FAMILY  %s: no check family named %s' % (mutant['id'], ', '.join(unknown))
    if verdict is None:
        return False, 'NO VERDICT  %s: %s' % (mutant['id'], problem)
    red = {}
    for failure in verdict['failures']:
        red[family(failure['name'])] = red.get(family(failure['name']), 0) + 1
    hit = [f for f in mutant['reddens'] if f in red]
    summary = ', '.join('%s x%d' % (f, n) for f, n in sorted(red.items()))
    if hit:
        return True, 'KILLED      %s (%s)' % (mutant['id'], summary)
    if red:
        return False, 'WRONG FAMILY %s: reddened %s, expected %s' % (
            mutant['id'], summary, ', '.join(mutant['reddens']))
    return False, 'SURVIVED    %s: no check failed; %s' % (mutant['id'], mutant['why'])


def run_mutants(mutants, families, jobs):
    root = tempfile.mkdtemp(prefix='jsharness-mutants-')
    try:
        prepared, problems = [], []
        for mutant in mutants:
            directory, problem = make_mutant(mutant, root)
            if directory:
                prepared.append((mutant, directory))
            else:
                problems.append('NOT APPLIED %s: %s' % (mutant['id'], problem))
        with concurrent.futures.ThreadPoolExecutor(max_workers=jobs) as pool:
            runs = list(pool.map(lambda pair: run_suite(pair[1]), prepared))
        killed = 0
        for (mutant, _), (verdict, problem) in zip(prepared, runs):
            ok, line = judge(mutant, verdict, problem, families)
            killed += ok
            print(line)
            if not ok:
                problems.append(line)
        for line in problems:
            if line.startswith('NOT APPLIED'):
                print(line)
        print('mutants: %d defined, %d killed, %d problems' % (len(mutants), killed, len(problems)))
        return not problems
    finally:
        shutil.rmtree(root, ignore_errors=True)


def main():
    parser = argparse.ArgumentParser(description='Run the block_dimensions JS harness.')
    parser.add_argument('--mutants', action='store_true', help='also run every mutant in mutants.py')
    parser.add_argument('--only', nargs='+', metavar='ID', help='with --mutants, run only these mutants')
    parser.add_argument('--jobs', type=int, default=4, help='mutants run in parallel (default 4)')
    args = parser.parse_args()
    if args.only and not args.mutants:
        parser.error('--only needs --mutants')

    verdict, problem = run_suite()
    if verdict is None:
        print('suite: NO VERDICT: ' + problem)
        return 1
    print_verdict('suite', verdict)
    if verdict['failed'] or not verdict['total']:
        # A red suite would make every mutant look caught.
        return 1
    if not args.mutants:
        return 0
    return 0 if run_mutants(load_mutants(args.only), set(verdict['families']), max(1, args.jobs)) else 1


if __name__ == '__main__':
    sys.exit(main())
