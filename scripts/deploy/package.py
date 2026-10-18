#!/usr/bin/env python3
"""Package Git object bytes for the exact commit; never copy private runtime trees."""
import io
import hashlib
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tarfile
from verify import require, safe_name, sha256, source_path, tree_files, verify


def package(root, out):
    root, out = Path(root), Path(out).absolute()
    commit = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=root, text=True).strip()
    inputs = ['app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'composer.json',
              'composer.lock', 'public', 'artisan', 'package.json', 'package-lock.json',
              'vite.config.js', 'scripts/sync-frontend-assets.mjs']
    require(not subprocess.check_output(['git', 'diff', 'HEAD', '--', *inputs], cwd=root),
            'Dirty application/build inputs: use a clean committed checkout')
    require(not out.exists(), 'Output must be new')
    build = root / 'public/build'
    tree_files(build)  # Reject build links before any copy follows them.
    entries = subprocess.check_output(['git', 'ls-tree', '-rz', '--full-tree', 'HEAD'], cwd=root).split(b'\0')
    expected = {}
    for entry in filter(None, entries):
        info, raw_name = entry.split(b'\t', 1)
        name = raw_name.decode('utf-8')
        if source_path(name):
            mode, kind, blob = info.decode().split()
            safe_name(name)
            require(mode in ('100644', '100755') and kind == 'blob', 'Linked/submodule source rejected')
            expected[name] = blob
    raw = subprocess.check_output(['git', 'archive', '--format=tar', 'HEAD'], cwd=root)
    out.mkdir(mode=0o700, parents=True)
    sources = []
    try:
        with tarfile.open(fileobj=io.BytesIO(raw)) as archive:
            for item in archive:
                if item.isdir() or not source_path(item.name):
                    continue
                safe_name(item.name)
                require(item.isfile(), 'Nonregular source file')
                target = out / 'payload' / item.name
                target.parent.mkdir(parents=True, exist_ok=True)
                with archive.extractfile(item) as src, target.open('xb') as dst:
                    shutil.copyfileobj(src, dst)
                content = target.read_bytes()
                git_digest = hashlib.sha1(b'blob ' + str(len(content)).encode() + b'\0' + content).hexdigest()
                require(git_digest == expected.get(item.name), 'Git export changed source bytes')
                sources.append(item.name)
        require(set(sources) == set(expected), 'Git export omitted source files')
        shutil.copytree(build, out / 'payload/public/build')
        files = {name: sha256(out / name) for name in sorted(tree_files(out))}
        data = {'schema': 1, 'commit': commit, 'files': files, 'source_files': sorted(sources)}
        (out / 'release.json').write_text(json.dumps(data, indent=2) + '\n', encoding='utf-8')
        verify(out, commit)
        return commit
    except BaseException:
        # This is the new output directory we just created, never a caller's existing tree.
        shutil.rmtree(out)
        raise


if __name__ == '__main__':
    try:
        require(len(sys.argv) == 2, 'Usage: package.py NEW_OUTPUT_DIRECTORY')
        print(package(Path(__file__).resolve().parents[2], sys.argv[1]))
    except (ValueError, OSError, subprocess.CalledProcessError) as exc:
        raise SystemExit('Packaging failed: ' + str(exc))
