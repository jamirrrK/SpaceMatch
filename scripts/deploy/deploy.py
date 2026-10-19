#!/usr/bin/env python3
"""Code-only state machine. CLI is pinned to the Z.com account; tests use isolated fixtures."""
import argparse
from contextlib import contextmanager
import gzip
import json
import os
from pathlib import Path
import shutil
import signal
import stat
import subprocess
import sys
import tarfile
import tempfile
import time
import urllib.request
import uuid
from verify import InvalidRelease, read_json, require, safe_name, sha256, tree_files, verify

APP_DIRS = ('app', 'config', 'resources', 'routes')
PUBLIC_DIRS = ('assets', 'vendor', 'build')  # public/vendor is static Bootstrap, NOT app/vendor.
APP_FILES = ('artisan', 'bootstrap/app.php', 'bootstrap/providers.php')
PUBLIC_FILES = ('favicon.ico', 'robots.txt')
CAPABILITIES = ('web_cli_php_match', 'private_modes_readable_by_web_php', 'entrypoint_maintenance_path_verified',
                'managed_paths_contain_no_uploads', 'file_maintenance_driver', 'writers_paused',
                'opcache_revalidates_and_no_persistent_workers', 'staging_recovery_rehearsed')
GUARD = """<?php
// Temporary deployment guard. No application bootstrap or credentials required.
if (is_file(__DIR__.'/down')) {
    http_response_code(503);
    header('Retry-After: 60');
    header('Cache-Control: no-store');
    echo 'SpaceMatch maintenance. Please try again later.';
    exit;
}
"""


def atomic_json(path, data):
    temp = path.with_name(path.name + '.tmp')
    with temp.open('x', encoding='utf-8') as stream:
        json.dump(data, stream, indent=2)
        stream.flush()
        os.fsync(stream.fileno())
    os.chmod(temp, 0o600)
    os.replace(temp, path)


def no_links(path):
    path = Path(path).absolute()
    for part in (path, *path.parents):
        require(not part.is_symlink() and not getattr(part, 'is_junction', lambda: False)(), 'Linked path rejected')


def command(args, cwd):
    result = subprocess.run(args, cwd=cwd, capture_output=True, text=True, timeout=90)
    # Deliberately do not echo PHP diagnostics: they can contain production configuration.
    require(result.returncode == 0, 'Server command failed: ' + Path(args[0]).name)
    return result.stdout


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def http_get(url):
    request = urllib.request.Request(url, headers={'Cache-Control': 'no-cache', 'User-Agent': 'SpaceMatch-release-check'})
    opener = urllib.request.build_opener(NoRedirect())
    with opener.open(request, timeout=10) as response:
        require(response.status == 200, 'Unexpected HTTP status')
        body = response.read(2 * 1024 * 1024 + 1)
        require(len(body) <= 2 * 1024 * 1024, 'Oversized health response')
        return body


class Deployment:
    def __init__(self, app, public, state, release, commit, backup, readiness,
                 run=command, fetch=http_get, writable=os.access):
        self.app, self.public, self.state = map(Path, (app, public, state))
        self.release, self.backup, self.readiness = map(Path, (release, backup, readiness))
        self.commit, self.run, self.fetch, self.writable = commit, run, fetch, writable
        self.php = 'php'
        self.history = None
        self.phase = 'not-started'

    def target(self, name):
        safe_name(name)
        if name.startswith('public/'):
            rel = name[7:]
            require(rel in PUBLIC_FILES or any(rel.startswith(p + '/') for p in PUBLIC_DIRS), 'Unmanaged public target')
            return self.public / rel
        require(name in APP_FILES or any(name.startswith(p + '/') for p in APP_DIRS), 'Unmanaged app target')
        return self.app / name

    def inventory(self):
        result = {}
        for prefix, root, directories, files in (('', self.app, APP_DIRS, APP_FILES),
                                                ('public/', self.public, PUBLIC_DIRS, PUBLIC_FILES)):
            for directory in directories:
                for rel in tree_files(root / directory):
                    name = prefix + directory + '/' + rel
                    safe_name(name)
                    result[name] = sha256(root / directory / rel)
            for file in files:
                path = root / file
                no_links(path)
                require(path.is_file() and path.stat().st_nlink == 1, 'Missing/linked managed file')
                result[prefix + file] = sha256(path)
        return result

    def protected(self):
        return {str(p): sha256(p) for p in (self.app / '.env', self.public / 'index.php', self.public / '.htaccess')}

    def verify_backup(self):
        no_links(self.backup)
        require(tree_files(self.backup) == {'backup.json', 'database.sql.gz', 'runtime.tar.gz'}, 'Unexpected backup files')
        record = read_json(self.backup / 'backup.json')
        require(isinstance(record, dict) and record.get('schema') == 1, 'Invalid backup record')
        require(record.get('database') == 'tejzvrgp_spacematch_db' and record.get('commit') == self.commit,
                'Backup database/commit mismatch')
        require(record.get('writers_paused') is True and bool(record.get('reviewed_by')), 'Unreviewed backup')
        now = time.time()
        for field in ('created_at', 'restore_tested_at'):
            value = record.get(field)
            require(type(value) in (int, float) and 0 <= now - value <= 3600, 'Stale/unverified backup')
        require(record['restore_tested_at'] >= record['created_at'], 'Restore test predates backup')
        files = record.get('files')
        require(isinstance(files, dict) and set(files) == {'database.sql.gz', 'runtime.tar.gz'}, 'Incomplete backup checksums')
        for name, digest in files.items():
            require(sha256(self.backup / name) == digest, 'Backup integrity failure')
        with gzip.open(self.backup / 'database.sql.gz', 'rb') as stream:
            size = 0
            for chunk in iter(lambda: stream.read(1024 * 1024), b''):
                size += len(chunk)
            require(size > 0, 'Empty SQL backup')
        with tarfile.open(self.backup / 'runtime.tar.gz', 'r:gz') as archive:
            names = set()
            for item in archive:
                name = item.name.removeprefix('./').rstrip('/')
                safe_name(name)
                names.add(name)
                if item.isfile():
                    with archive.extractfile(item) as stream:
                        while stream.read(1024 * 1024):
                            pass
            require({'spacematch-app/.env', 'spacematch-app/vendor/autoload.php',
                     'public_html/spacematch/public/index.php', 'public_html/spacematch/public/.htaccess'} <= names,
                    'Runtime backup missing protected paths')

    def dependencies(self, probe, record_path):
        for file in ('composer.json', 'composer.lock'):
            require(sha256(self.app / file) == self.manifest['files']['payload/' + file], 'Composer file changed')
        require((self.app / 'vendor/autoload.php').is_file(), 'Missing production dependencies')
        # No Composer installation assumed. A separately verified production bundle is mandatory.
        record = read_json(Path(record_path))
        require(isinstance(record, dict) and record.get('schema') == 1
                and record.get('method') == 'verified-production-bundle'
                and record.get('platform_check') == 'passed' and bool(record.get('reviewed_by'))
                and bool(record.get('provisioning_record')), 'No verified dependency provisioning method')
        require(record.get('composer_lock_sha256') == sha256(self.app / 'composer.lock')
                and record.get('composer_json_sha256') == sha256(self.app / 'composer.json'), 'Dependency receipt lock mismatch')
        require(record.get('php_version_id') == probe['php_version_id']
                and record.get('extensions') == probe['extensions'], 'Dependency platform fingerprint changed')
        actual = {name: sha256(self.app / 'vendor' / name) for name in tree_files(self.app / 'vendor')}
        require(isinstance(record.get('vendor_files'), dict) and bool(actual)
                and actual == record['vendor_files'], 'Installed vendor differs from verified bundle')

    def health(self, files):
        last = None
        for attempt in range(3):
            try:
                require(b'Application up' in self.fetch('https://spacematch.bsitait21.tech/up'), 'Laravel health marker missing')
                require(b'spacematch' in self.fetch('https://spacematch.bsitait21.tech/').lower(), 'Homepage marker missing')
                for name, digest in files.items():
                    if name.startswith('public/build/') and name.endswith(('.js', '.css')):
                        require(__import__('hashlib').sha256(self.fetch('https://spacematch.bsitait21.tech/' + name[7:])).hexdigest() == digest,
                                'Served frontend asset mismatch')
                return
            except Exception as exc:
                last = exc
                if attempt < 2:
                    time.sleep(1)
        raise InvalidRelease('Health verification failed') from last

    def preflight(self):
        self.manifest = verify(self.release, self.commit)
        for path in (self.app, self.public, self.state, self.release, self.backup, self.readiness):
            no_links(path)
        paths = [self.app.absolute(), self.public.absolute(), self.state.absolute(), self.release.absolute(), self.backup.absolute()]
        require(all(a != b and a not in b.parents and b not in a.parents
                    for i, a in enumerate(paths) for b in paths[i + 1:]), 'Deployment roots overlap')
        ready = read_json(self.readiness)
        require(isinstance(ready, dict) and ready.get('schema') == 1 and ready.get('enabled') is True
                and bool(ready.get('reviewed_by')), 'Hosting readiness not explicitly approved')
        require(type(ready.get('valid_until')) in (int, float)
                and time.time() < ready['valid_until'] <= time.time() + 7 * 86400, 'Hosting readiness expired/invalid')
        require(all(ready.get('capabilities', {}).get(key) is True for key in CAPABILITIES), 'Unverified cPanel capabilities')
        self.php = ready.get('php_binary')
        require(isinstance(self.php, str) and Path(self.php).is_absolute()
                and shutil.which(self.php), 'Missing verified PHP CLI tool')
        require(sys.version_info >= (3, 10), 'Python 3.10+ required')
        import ssl  # A minimal shared-hosting Python build may omit this.
        require(ssl.OPENSSL_VERSION, 'Python TLS unavailable')
        for root in (self.app / 'storage/framework', self.app / 'storage/framework/cache',
                     self.app / 'storage/framework/sessions', self.app / 'storage/framework/views',
                     self.app / 'storage/logs', self.app / 'bootstrap/cache', self.state):
            no_links(root)
            require(root.is_dir() and self.writable(root, os.W_OK | os.X_OK), 'Runtime/state directory not writable')
            with tempfile.TemporaryFile(dir=root) as stream:
                stream.write(b'permission probe')
        for path in (self.app / '.env', self.public / 'index.php', self.public / '.htaccess'):
            no_links(path)
            require(path.is_file() and path.stat().st_nlink == 1, 'Missing protected application path')
        require(not (self.public / 'hot').exists() and not (self.app / 'public/hot').exists(), 'Production Vite hot file present')
        for name in ('down', 'maintenance.php'):
            require(not (self.app / 'storage/framework' / name).exists(), 'Application already in maintenance/recovery')
        probe = json.loads(self.run([self.php, str(Path(__file__).with_name('runtime-check.php')), str(self.app)], self.app))
        require(probe.get('php_version_id', 0) >= 80200, 'PHP 8.2+ required')
        require(isinstance(probe.get('public_path'), str) and Path(probe['public_path']).resolve() == self.public.resolve(),
                'Laravel public_path does not resolve to the real document root')
        require(isinstance(probe.get('storage_path'), str) and Path(probe['storage_path']).resolve() == (self.app / 'storage').resolve(),
                'Laravel storage_path differs from verified runtime directory')
        required = {'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'mbstring', 'openssl', 'pcre',
                    'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml'}
        require(isinstance(probe.get('extensions'), dict) and required <= set(probe['extensions']), 'Missing PHP extensions')
        require(probe.get('maintenance_driver') == 'file' and probe.get('classmap_authoritative') is False,
                'Unsupported maintenance/autoload configuration')
        require(probe.get('database') == 'tejzvrgp_spacematch_db', 'Unexpected production database')
        require(isinstance(probe.get('app_classmap_paths'), list)
                and all(isinstance(name, str) and 'payload/' + safe_name(name) in self.manifest['files']
                        for name in probe['app_classmap_paths']), 'Removed/moved classmap path requires dependency reprovisioning')
        record = ready.get('dependency_record')
        require(isinstance(record, str) and Path(record).is_absolute(), 'Missing verified dependency record')
        no_links(Path(record))
        self.dependencies(probe, record)
        no_links(self.app / 'database/migrations')
        expected_migrations = {n[8:]: v for n, v in self.manifest['files'].items() if n.startswith('payload/database/migrations/')}
        actual_migrations = {'database/migrations/' + n: sha256(self.app / 'database/migrations' / n)
                             for n in tree_files(self.app / 'database/migrations')}
        require(expected_migrations == actual_migrations, 'Migration files differ')
        names = sorted(Path(n).stem for n in actual_migrations)
        require(probe.get('migrations') == names, 'Pending/drifted migration history requires explicit review')
        self.old_inventory = self.inventory()
        require(ready.get('managed_inventory') == self.old_inventory, 'Unreviewed managed paths/files or production drift')
        self.new_inventory = {n[8:]: v for n, v in self.manifest['files'].items()
                              if not n.startswith('payload/database/') and n not in ('payload/composer.json', 'payload/composer.lock')}
        for name in self.new_inventory:
            target = self.target(name)
            require(self.writable(target.parent if target.parent.exists() else self.public if name.startswith('public/') else self.app,
                                  os.W_OK | os.X_OK), 'Managed destination not writable')
        for root in (self.app, self.public, self.state):
            require(shutil.disk_usage(root).free > sum(self.target(n).stat().st_size for n in self.old_inventory) * 3 + 64 * 1024 * 1024,
                    'Insufficient destination/snapshot space')
        self.verify_backup()
        self.protected_before = self.protected()
        self.health(self.old_inventory)
        return ready

    def status(self, phase):
        self.phase = phase
        atomic_json(self.history / 'status.json', {'phase': phase, 'commit': self.commit, 'time': time.time()})

    def guard(self):
        framework = self.app / 'storage/framework'
        (framework / 'maintenance.php').write_text(GUARD, encoding='utf-8')
        os.chmod(framework / 'maintenance.php', 0o600)
        atomic_json(framework / 'down', {'status': 503, 'retry': 60, 'time': int(time.time()),
                                       'template': 'SpaceMatch maintenance. Please try again later.'})

    def unguard(self):
        # Only remove files created for this transaction; existing maintenance blocks preflight.
        for name in ('down', 'maintenance.php'):
            (self.app / 'storage/framework' / name).unlink(missing_ok=True)

    def synchronize(self, source, desired, current):
        """Delete individual reviewed files only. No recursive deletion or rsync --delete."""
        for name in set(current) | set(desired):
            no_links(self.target(name))
        for name, digest in desired.items():
            target = self.target(name)
            target.parent.mkdir(mode=0o755 if name.startswith('public/') else 0o700, parents=True, exist_ok=True)
            temp = target.with_name(target.name + '.spacematch-new')
            require(not temp.exists() and not temp.is_symlink(), 'Unexpected staging file')
            try:
                with (source / name).open('rb') as src, temp.open('xb') as dst:
                    shutil.copyfileobj(src, dst)
                    dst.flush()
                    os.fsync(dst.fileno())
                require(sha256(temp) == digest, 'Copy integrity failure')
                os.chmod(temp, 0o644 if name.startswith('public/') else 0o600)
                os.replace(temp, target)
            finally:
                temp.unlink(missing_ok=True)
        for name in set(current) - set(desired):
            self.target(name).unlink(missing_ok=True)
        # Empty directories are deliberately retained; uploaded/uninventoried files never qualify for deletion.
        require(self.inventory() == desired, 'Post-copy inventory mismatch')

    def cache(self):
        for action in ('config:clear', 'route:clear', 'view:clear', 'config:cache', 'view:cache'):
            self.run([self.php, 'artisan', action], self.app)
        # Never cache:clear/optimize:clear here: production application data is not a deployment cache.

    def activate(self):
        self.preflight()
        self.history = self.state / ('release-' + str(int(time.time())) + '-' + self.commit + '-' + uuid.uuid4().hex[:8])
        self.history.mkdir(mode=0o700)
        self.status('snapshotting-previous-code')
        snapshot = self.history / 'previous'
        snapshot.mkdir(mode=0o700)
        for name, digest in self.old_inventory.items():
            target = snapshot / name
            target.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
            shutil.copyfile(self.target(name), target)
            os.chmod(target, 0o600)
            require(sha256(target) == digest, 'Snapshot integrity failure')
        atomic_json(self.history / 'previous-inventory.json', self.old_inventory)
        atomic_json(self.history / 'target-inventory.json', self.new_inventory)
        self.status('prepared')
        atomic_json(self.state / 'last-working.json', {'history': str(self.history), 'tree': 'previous',
                                                     'commit': None, 'inventory': self.old_inventory})
        try:
            self.guard()
            self.status('maintenance')
            self.synchronize(self.release / 'payload', self.new_inventory, self.old_inventory)
            self.status('code-copied')
            require(self.protected() == self.protected_before, 'Protected configuration/entrypoint changed')
            self.cache()
            self.status('checking-health')
            self.unguard()
            self.health(self.new_inventory)
            self.status('healthy-awaiting-functional-review')
            atomic_json(self.state / 'last-working.json', {'history': str(self.history), 'commit': self.commit,
                                                         'inventory': self.new_inventory})
            return 'healthy-awaiting-functional-review'
        except BaseException:
            # Restore code/assets only. Never restore databases, vendor, .env or uploaded files.
            try:
                self.guard()
                self.status('recovering-previous-code')
                require(tree_files(snapshot) == set(self.old_inventory), 'Recovery snapshot file set changed')
                for name, digest in self.old_inventory.items():
                    require(sha256(snapshot / name) == digest, 'Recovery snapshot corrupted')
                self.synchronize(snapshot, self.old_inventory, self.new_inventory)
                require(self.protected() == self.protected_before, 'Protected files changed during incident')
                self.cache()
                self.unguard()
                self.health(self.old_inventory)
                self.status('failed-previous-code-restored-healthy')
            except BaseException:
                try:
                    self.guard()
                    self.status('failed-closed-maintenance-operator-required')
                except BaseException:
                    # Never claim maintenance is active if filesystem access was lost.
                    print('CRITICAL: maintenance could not be confirmed; use cPanel to disable web access immediately.', file=sys.stderr)
                    try:
                        self.status('critical-maintenance-unconfirmed-operator-required')
                    except BaseException:
                        pass
                print('CRITICAL: recovery needs operator intervention; preserve history and backups. No database restored.', file=sys.stderr)
            raise InvalidRelease('Deployment failed; recovery status: ' + self.phase) from None


@contextmanager
def server_lock(state):
    import fcntl
    lock = Path(state) / 'production.lock'
    no_links(lock)
    with lock.open('a') as stream:
        os.chmod(lock, 0o600)
        try:
            fcntl.flock(stream, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            raise InvalidRelease('Another deployment/recovery holds the lock') from None
        yield



def assert_no_unresolved(state):
    for history in Path(state).glob('release-*'):
        require(history.is_dir() and not history.is_symlink(), 'Unsafe deployment history')
        record = history / 'status.json'
        require(record.is_file(), 'Interrupted deployment without status requires operator recovery')
        phase = read_json(record).get('phase')
        require(phase in ('healthy-awaiting-functional-review', 'failed-previous-code-restored-healthy'),
                'Unresolved deployment history requires operator recovery')


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('release')
    parser.add_argument('commit')
    parser.add_argument('backup')
    parser.add_argument('approval')
    parser.add_argument('--preflight', action='store_true')
    args = parser.parse_args()
    require(os.name == 'posix' and __import__('pwd').getpwuid(os.getuid()).pw_name == 'tejzvrgp', 'Z.com cPanel account required')
    require(args.approval == 'APPROVED-CODE-ONLY', 'Explicit approval token required')
    state = Path('/home/tejzvrgp/spacematch-deploy')
    require(state.is_dir(), 'Provision private deployment state and readiness record first')
    no_links(state)
    require(stat.S_IMODE(state.stat().st_mode) == 0o700 and state.stat().st_uid == os.getuid(), 'State ownership/mode must be owner-only')
    deploy = Deployment('/home/tejzvrgp/spacematch-app', '/home/tejzvrgp/public_html/spacematch/public',
                        state, args.release, args.commit, args.backup, state / 'readiness.json')
    def interrupted(signum, frame):
        raise InterruptedError('Deployment interrupted')
    signal.signal(signal.SIGTERM, interrupted)
    with server_lock(state):
        # Never start another release over an interrupted/unknown transaction.
        assert_no_unresolved(state)
        if args.preflight:
            deploy.preflight()
            print('Preflight passed; no code activation performed')
        else:
            print(deploy.activate())


if __name__ == '__main__':
    try:
        main()
    except (Exception, KeyboardInterrupt) as exc:
        # Config, SQL and command output are intentionally omitted.
        print('BLOCKED/FAILED: ' + str(exc), file=sys.stderr)
        sys.exit(1)
