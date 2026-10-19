#!/usr/bin/env python3
"""Strict, dependency-free validation of approved SpaceMatch release artifacts."""
import argparse
import hashlib
import json
import os
from pathlib import Path, PurePosixPath
import re
import stat
import tarfile


class InvalidRelease(ValueError):
    pass


def require(ok, message):
    if not ok:
        raise InvalidRelease(message)


def sha256(path):
    h = hashlib.sha256()
    with open(path, 'rb') as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b''):
            h.update(chunk)
    return h.hexdigest()


def unique_object(pairs):
    obj = {}
    for key, value in pairs:
        require(key not in obj, 'Duplicate JSON key')
        obj[key] = value
    return obj


def read_json(path):
    require(path.is_file() and path.stat().st_size <= 16 * 1024 * 1024, 'Missing/oversized JSON file')
    try:
        return json.loads(path.read_text(encoding='utf-8'), object_pairs_hook=unique_object,
                          parse_constant=lambda x: require(False, 'Nonfinite JSON number'))
    except (ValueError, UnicodeError) as exc:
        raise InvalidRelease('Malformed JSON: ' + path.name) from exc


def safe_name(name):
    require(isinstance(name, str) and bool(name), 'Invalid path type')
    require(not name.startswith('/') and '\\' not in name and ':' not in name,
            'Absolute/Windows path rejected')
    require(all(part not in ('', '.', '..') for part in name.split('/')), 'Path traversal/noncanonical path')
    require(all(re.fullmatch(r'[A-Za-z0-9_.@+ -]+', p) and p.strip() == p and not p.endswith('.')
                for p in name.split('/')), 'Unsafe path characters')
    reserved = {'CON', 'PRN', 'AUX', 'NUL'} | {f'{p}{n}' for p in ('COM', 'LPT') for n in range(1, 10)}
    require(all(p.split('.')[0].upper() not in reserved for p in name.split('/')), 'Reserved device path')
    return name


SOURCE_PREFIXES = ('app/', 'config/', 'resources/', 'routes/', 'database/migrations/',
                   'public/assets/', 'public/vendor/')
SOURCE_EXACT = {'artisan', 'composer.json', 'composer.lock', 'bootstrap/app.php',
                'bootstrap/providers.php', 'public/favicon.ico', 'public/robots.txt'}
REQUIRED = SOURCE_EXACT | {'routes/web.php', 'public/build/manifest.json',
                           'public/vendor/bootstrap/bootstrap.min.css',
                           'public/vendor/bootstrap/bootstrap.bundle.min.js'}


def source_path(name):
    if any(p == '.env' or p.startswith('.env.') or p in ('.htaccess', '.user.ini', 'auth.json')
           for p in name.split('/')):
        return False
    if name.startswith('public/') and Path(name).suffix.lower() in ('.php', '.phtml', '.phar', '.cgi'):
        return False
    return name in SOURCE_EXACT or name.startswith(SOURCE_PREFIXES)


def tree_files(base):
    """lstat before recursion: never follow symlinks, junctions, special files or hardlinks."""
    require(base.is_dir(), 'Missing directory')
    require(all(not p.is_symlink() and not getattr(p, 'is_junction', lambda: False)()
                for p in (base, *base.parents)), 'Linked ancestor rejected')
    found = set()
    def walk(directory):
        for entry in directory.iterdir():
            info = entry.lstat()
            require(not entry.is_symlink() and not getattr(entry, 'is_junction', lambda: False)(),
                    'Symlink/junction rejected')
            if stat.S_ISDIR(info.st_mode):
                walk(entry)
            else:
                require(stat.S_ISREG(info.st_mode) and info.st_nlink == 1, 'Special file/hardlink rejected')
                found.add(entry.relative_to(base).as_posix())
    info = base.lstat()
    require(not base.is_symlink() and not getattr(base, 'is_junction', lambda: False)(), 'Linked root rejected')
    walk(base)
    return found


def validate_frontend(base, files):
    build = base / 'payload/public/build'
    manifest = read_json(build / 'manifest.json')
    require(isinstance(manifest, dict) and bool(manifest), 'Invalid Vite manifest')
    for entry in ('resources/css/app.css', 'resources/js/app.js'):
        require(isinstance(manifest.get(entry), dict) and manifest[entry].get('isEntry') is True,
                'Missing Vite application entry')
    referenced = set()
    for key, item in manifest.items():
        require(isinstance(item, dict) and isinstance(item.get('file'), str), 'Invalid Vite entry')
        for field in ('css', 'assets'):
            require(isinstance(item.get(field, []), list)
                    and all(isinstance(x, str) for x in item.get(field, [])), 'Invalid Vite asset list')
        for rel in [item['file']] + item.get('css', []) + item.get('assets', []):
            safe_name(rel)
            name = 'payload/public/build/' + rel
            require(name in files and (base / name).stat().st_size > 0, 'Missing/empty Vite asset')
            referenced.add(name)
        for field in ('imports', 'dynamicImports'):
            require(isinstance(item.get(field, []), list), 'Invalid Vite imports')
            require(all(isinstance(x, str) and x in manifest for x in item.get(field, [])), 'Missing Vite import')
    for entry, suffix in [('resources/css/app.css', '.css'), ('resources/js/app.js', '.js')]:
        require(manifest[entry]['file'].endswith(suffix), 'Wrong Vite entry type')
    # All emitted build files must be covered by Vite's graph, except optional source maps.
    built = {x for x in files if x.startswith('payload/public/build/')}
    require(all(x in referenced or x == 'payload/public/build/manifest.json'
                or (x.endswith('.map') and x[:-4] in referenced) for x in built), 'Unreferenced build file')


def verify(base, commit):
    base = Path(base).absolute()
    require(isinstance(commit, str) and re.fullmatch(r'[0-9a-f]{40}', commit), 'Invalid release SHA')
    actual = tree_files(base)
    require('release.json' in actual, 'Missing release manifest')
    data = read_json(base / 'release.json')
    require(isinstance(data, dict) and set(data) == {'schema', 'commit', 'files', 'source_files'},
            'Invalid manifest schema')
    require(type(data['schema']) is int and data['schema'] == 1 and data['commit'] == commit,
            'Schema/commit mismatch')
    files, sources = data['files'], data['source_files']
    require(isinstance(files, dict) and bool(files), 'Invalid files map')
    require(isinstance(sources, list) and all(isinstance(x, str) for x in sources)
            and sources == sorted(set(sources)), 'Invalid source inventory')
    for name, digest in files.items():
        safe_name(name)
        require(name.startswith('payload/'), 'Unexpected release path')
        rel = name[8:]
        require(not any(p == '.env' or p.startswith('.env.') or p in ('.htaccess', '.user.ini', 'auth.json')
                        for p in rel.split('/')), 'Protected release filename')
        require(not rel.startswith('public/') or Path(rel).suffix.lower() not in ('.php', '.phtml', '.phar', '.cgi'),
                'Executable public asset rejected')
        require(source_path(rel) or rel.startswith('public/build/'), 'Unmanaged release path')
        require(isinstance(digest, str) and re.fullmatch(r'[0-9a-f]{64}', digest), 'Invalid file digest')
    require(actual == set(files) | {'release.json'}, 'Unexpected or missing release files')
    # Empty unexpected directories are rejected too.
    expected_dirs = {str(p) for name in files for p in PurePosixPath(name).parents if str(p) != '.'}
    require(all(p.relative_to(base).as_posix() in expected_dirs
                for p in base.rglob('*') if p.is_dir()), 'Unexpected directory')
    require(set(sources) == {n[8:] for n in files if source_path(n[8:])}, 'Incomplete source inventory')
    require(all('payload/' + x in files for x in REQUIRED), 'Incomplete release')
    for prefix in ('app/', 'config/', 'resources/', 'database/migrations/'):
        require(any(x.startswith(prefix) for x in sources), 'Missing application directory')
    for name, digest in files.items():
        require(sha256(base / name) == digest, 'Corrupted release file: ' + name)
    for file in ('composer.json', 'composer.lock'):
        require(isinstance(read_json(base / 'payload' / file), dict), 'Invalid Composer metadata')
    for name in ('public/vendor/bootstrap/bootstrap.min.css', 'public/vendor/bootstrap/bootstrap.bundle.min.js'):
        require((base / 'payload' / name).stat().st_size > 0, 'Empty Bootstrap asset')
    validate_frontend(base, files)
    return data


def extract_release(archive, destination, commit, digest):
    """Verify trusted archive digest, reject links/traversal before creating an extraction tree."""
    require(re.fullmatch(r'[0-9a-f]{64}', digest or ''), 'Invalid trusted archive digest')
    require(sha256(archive) == digest, 'Archive checksum mismatch')
    destination = Path(destination)
    require(not destination.exists() and not destination.is_symlink(), 'Extraction directory must be new')
    require(all(not p.is_symlink() and not getattr(p, 'is_junction', lambda: False)()
                for p in destination.absolute().parents), 'Linked extraction ancestor')
    with tarfile.open(archive, 'r:gz') as tar:
        members, seen, total = [], set(), 0
        for item in tar:
            name = item.name[2:] if item.name.startswith('./') else item.name
            if name in ('', '.') and item.isdir():
                continue
            name = name.rstrip('/') if item.isdir() else name
            safe_name(name)
            require(name not in seen and (item.isfile() or item.isdir()), 'Duplicate/linked/special archive member')
            require(item.size >= 0 and item.size <= 128 * 1024 * 1024, 'Oversized archive member')
            seen.add(name)
            total += item.size
            require(len(seen) <= 100000 and total <= 1024 ** 3, 'Oversized archive')
            members.append((item, name))
        destination.mkdir(mode=0o700, parents=False)
        for item, name in members:
            path = destination / name
            path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
            if item.isdir():
                path.mkdir(mode=0o700, exist_ok=True)
            else:
                with tar.extractfile(item) as source, path.open('xb') as target:
                    import shutil
                    shutil.copyfileobj(source, target)
                os.chmod(path, 0o600)
    return verify(destination, commit)


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('release')
    parser.add_argument('commit')
    parser.add_argument('--archive')
    parser.add_argument('--sha256')
    args = parser.parse_args()
    try:
        if args.archive:
            extract_release(args.archive, args.release, args.commit, args.sha256)
        else:
            verify(args.release, args.commit)
        print('Verified ' + args.commit)
    except (ValueError, OSError, tarfile.TarError, TypeError, KeyError) as exc:
        parser.exit(1, 'REJECTED: ' + str(exc) + '\n')
