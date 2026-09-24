#!/bin/sh
# Run the block_dimensions JS harness in headless Chromium; see README.md.
#
#   tests/jsharness/run.sh                            the suite over amd/src
#   tests/jsharness/run.sh --mutants                  the suite, then every mutant in mutants.py
#   tests/jsharness/run.sh --mutants --only ID ...    the suite, then the named mutants
#
# Exits non-zero on any failed check, on a run that produced no verdict and, with --mutants, on a
# mutant that did not apply exactly once or that no check of its family caught.
#
# Development tooling only: excluded from the release zip through .gitattributes.
set -eu

# The image the harness runs in: selenium/standalone-chromium:4 (Chromium 150), pinned by digest so
# every run uses the same browser. JSHARNESS_IMAGE overrides it.
IMAGE="${JSHARNESS_IMAGE:-selenium/standalone-chromium@sha256:3400b92f1cddb2dfaaf358654e8f7d83d7be45192fb73c5f28c25faa28d36504}"

here=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
repo=$(CDPATH='' cd -- "$here/../.." && pwd)

# The repository is mounted read-only and the container has no network: the harness reads amd/src
# and writes mutant copies to the container's own /tmp, which goes with it.
exec docker run --rm --network none \
    -v "$repo":/repo:ro \
    --entrypoint python3 \
    "$IMAGE" /repo/tests/jsharness/runner.py "$@"
