#!/usr/bin/env python3
"""Offline deployment tests. Fake PHP/HTTP; all filesystem mutations use temporary trees."""
import gzip
import hashlib
import io
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tarfile
import tempfile
import time
import unittest
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parent))
from deploy import APP_DIRS, APP_FILES, PUBLIC_DIRS, PUBLIC_FILES, CAPABILITIES, Deployment, GUARD, assert_no_unresolved, server_lock
from verify import InvalidRelease, extract_release, sha256, tree_files, verify
from package import package

SHA = 'a' * 40


def write(path, content):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_bytes(content if isinstance(content, bytes) else content.encode())


def metadata(release):
    files = {name: sha256(release / name) for name in sorted(tree_files(release)) if name != 'release.json'}
    sources = sorted(n[8:] for n in files if not n.startswith('payload/public/build/'))
    data = {'schema': 1, 'commit': SHA, 'files': files, 'source_files': sources}
    write(release / 'release.json', json.dumps(data))
    return data


class Fixture(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='spacematch-deploy-test-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.release = self.root / 'release'
        source = {
            'artisan': '<?php', 'composer.json': '{}', 'composer.lock': '{}',
            'bootstrap/app.php': '<?php', 'bootstrap/providers.php': '<?php',
            'app/Example.php': 'new application', 'config/app.php': 'new configuration',
            'resources/views/home.blade.php': 'new view', 'routes/web.php': 'new routes',
            'database/migrations/2026_01_01_000000_create_users.php': '<?php',
            'public/favicon.ico': b'icon', 'public/robots.txt': 'User-agent: *',
            'public/assets/style.css': 'new static CSS',
            'public/vendor/bootstrap/bootstrap.min.css': 'bootstrap css',
            'public/vendor/bootstrap/bootstrap.bundle.min.js': 'bootstrap js',
            'public/build/assets/app.css': 'new vite css', 'public/build/assets/app.js': 'new vite js',
        }
        for name, content in source.items():
            write(self.release / 'payload' / name, content)
        self.vite = {
            'resources/css/app.css': {'file': 'assets/app.css', 'isEntry': True},
            'resources/js/app.js': {'file': 'assets/app.js', 'isEntry': True},
        }
        write(self.release / 'payload/public/build/manifest.json', json.dumps(self.vite))
        self.data = metadata(self.release)
        self.app, self.public, self.state = [self.root / x for x in ('app-root', 'public-root', 'state')]
        self.state.mkdir()
        for name in self.data['files']:
            rel = name[8:]
            target = self.public / rel[7:] if rel.startswith('public/') else self.app / rel
            shutil.copyfile(self.release / name, target) if target.parent.exists() else write(target, (self.release / name).read_bytes())
        for root in ('storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'):
            (self.app / root).mkdir(parents=True, exist_ok=True)
        write(self.app / 'app/Example.php', 'last working application')
        write(self.app / 'app/obsolete.php', 'last working obsolete code')
        for path in (self.app / '.env', self.public / 'index.php', self.public / '.htaccess',
                     self.app / 'vendor/autoload.php', self.public / 'uploads/customer.jpg',
                     self.app / 'storage/app/private/customer.txt'):
            write(path, 'protected:' + path.name)
        self.probe = {
            'public_path': str(self.public), 'storage_path': str(self.app / 'storage'),
            'php_version_id': 80212,
            'extensions': {key: '8.2.12' for key in ('ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash',
                          'mbstring', 'openssl', 'pcre', 'pdo', 'pdo_mysql', 'session', 'tokenizer', 'xml')},
            'app_classmap_paths': ['app/Example.php'],
            'maintenance_driver': 'file', 'classmap_authoritative': False,
            'database': 'tejzvrgp_spacematch_db',
            'migrations': ['2026_01_01_000000_create_users'],
        }
        self.receipt = self.root / 'dependencies.json'
        self.dep_record = {'schema': 1, 'method': 'verified-production-bundle', 'platform_check': 'passed',
                           'reviewed_by': 'test reviewer', 'provisioning_record': 'isolated test provisioning',
                           'composer_lock_sha256': sha256(self.app / 'composer.lock'),
                           'composer_json_sha256': sha256(self.app / 'composer.json'),
                           'php_version_id': self.probe['php_version_id'], 'extensions': self.probe['extensions'],
                           'vendor_files': {'autoload.php': sha256(self.app / 'vendor/autoload.php')}}
        write(self.receipt, json.dumps(self.dep_record))
        self.backup = self.root / 'backup'
        self.backup.mkdir()
        with gzip.open(self.backup / 'database.sql.gz', 'wb') as stream:
            stream.write(b'-- isolated test fixture; no real database\n')
        with tarfile.open(self.backup / 'runtime.tar.gz', 'w:gz') as archive:
            for name in ('spacematch-app/.env', 'spacematch-app/vendor/autoload.php',
                         'public_html/spacematch/public/index.php', 'public_html/spacematch/public/.htaccess'):
                item = tarfile.TarInfo(name)
                item.size = 7
                archive.addfile(item, io.BytesIO(b'fixture'))
        self.backup_record = {'schema': 1, 'database': 'tejzvrgp_spacematch_db', 'commit': SHA,
                              'created_at': time.time(), 'restore_tested_at': time.time(),
                              'writers_paused': True, 'reviewed_by': 'test reviewer',
                              'files': {n: sha256(self.backup / n) for n in ('database.sql.gz', 'runtime.tar.gz')}}
        write(self.backup / 'backup.json', json.dumps(self.backup_record))
        self.ready_path = self.state / 'readiness.json'
        self.deploy = Deployment(self.app, self.public, self.state, self.release, SHA, self.backup,
                                 self.ready_path, run=self.fake_run, fetch=self.fake_http)
        self.ready = {'schema': 1, 'enabled': True, 'reviewed_by': 'test reviewer',
                      'valid_until': time.time() + 3600, 'php_binary': sys.executable,
                      'capabilities': {x: True for x in CAPABILITIES}, 'dependency_record': str(self.receipt),
                      'managed_inventory': self.deploy.inventory()}
        self.save_ready()
        self.protected = {str(path): path.read_bytes() for path in (
            self.app / '.env', self.app / 'vendor/autoload.php', self.public / 'index.php', self.public / '.htaccess',
            self.public / 'uploads/customer.jpg', self.app / 'storage/app/private/customer.txt',
            self.app / 'composer.lock', self.app / 'database/migrations/2026_01_01_000000_create_users.php')}
        self.sleep_patch = patch('deploy.time.sleep')
        self.sleep_patch.start()
        self.addCleanup(self.sleep_patch.stop)

    def fake_run(self, args, cwd):
        if args[1].endswith('runtime-check.php'):
            return json.dumps(self.probe)
        return ''

    def fake_http(self, url):
        if url.endswith('/up'):
            return b'<h2>Application up</h2>'
        if url.endswith('/'):
            return b'SpaceMatch homepage'
        return (self.public / url.split('.tech/')[1]).read_bytes()

    def save_ready(self):
        write(self.ready_path, json.dumps(self.ready))

    def assert_protected(self):
        for path, original in self.protected.items():
            self.assertEqual(Path(path).read_bytes(), original, path)

    def rejected_preflight(self):
        before = self.deploy.inventory()
        with self.assertRaises((InvalidRelease, OSError, ValueError)):
            self.deploy.preflight()
        self.assertEqual(self.deploy.inventory(), before)
        self.assertFalse((self.app / 'storage/framework/down').exists())
        self.assert_protected()


class ReleaseValidationTests(Fixture):
    def test_valid_release(self):
        self.assertEqual(verify(self.release, SHA)['commit'], SHA)

    def test_invalid_sha(self):
        for value in ('main', '', 'A' * 40, '../' + SHA, 'a' * 39, SHA + ';id'):
            with self.subTest(value=value), self.assertRaises(InvalidRelease):
                verify(self.release, value)

    def test_wrong_sha(self):
        with self.assertRaises(InvalidRelease):
            verify(self.release, 'b' * 40)

    def test_corrupted_file(self):
        write(self.release / 'payload/artisan', 'corruption')
        with self.assertRaises(InvalidRelease):
            verify(self.release, SHA)

    def test_missing_file(self):
        (self.release / 'payload/artisan').unlink()
        with self.assertRaises(InvalidRelease):
            verify(self.release, SHA)

    def test_unexpected_file_and_empty_directory(self):
        write(self.release / 'payload/.env', 'forbidden')
        with self.assertRaises(InvalidRelease):
            verify(self.release, SHA)
        (self.release / 'payload/.env').unlink()
        (self.release / 'unexpected').mkdir()
        with self.assertRaises(InvalidRelease):
            verify(self.release, SHA)

    def test_malformed_manifests(self):
        for data in ([], {}, {'schema': 1}, dict(self.data, files=[]), dict(self.data, source_files=[]),
                     dict(self.data, schema=True), dict(self.data, extra=True)):
            with self.subTest(data=type(data).__name__):
                write(self.release / 'release.json', json.dumps(data))
                with self.assertRaises(InvalidRelease):
                    verify(self.release, SHA)
        write(self.release / 'release.json', '{"schema":1,"schema":1}')
        with self.assertRaises(InvalidRelease):
            verify(self.release, SHA)

    def test_traversal_and_windows_paths(self):
        for name in ('payload/app/../.env', 'payload//app/a.php', '/etc/passwd',
                     'payload/app\\x.php', 'C:/a.php', 'payload/app/a:stream', 'payload/app/./x.php'):
            data = dict(self.data, files=dict(self.data['files']))
            data['files'][name] = 'a' * 64
            write(self.release / 'release.json', json.dumps(data))
            with self.subTest(name=name), self.assertRaises(InvalidRelease):
                verify(self.release, SHA)

    def test_link_detection_without_os_privileges(self):
        target = self.release / 'payload/app/Example.php'
        original = Path.is_symlink
        with patch.object(Path, 'is_symlink', lambda p: p == target or original(p)):
            with self.assertRaises(InvalidRelease):
                verify(self.release, SHA)

    def test_duplicate_archive_members(self):
        archive = self.root / 'duplicate.tar.gz'
        with tarfile.open(archive, 'w:gz') as tar:
            tar.addfile(tarfile.TarInfo('release.json'))
            tar.addfile(tarfile.TarInfo('release.json'))
        with self.assertRaises(InvalidRelease):
            extract_release(archive, self.root / 'duplicate-output', SHA, sha256(archive))
        self.assertFalse((self.root / 'duplicate-output').exists())

    def test_nested_protected_file_rejected(self):
        for name in ('app/.env', 'public/build/.env', 'public/assets/shell.php'):
            target = self.release / 'payload' / name
            write(target, 'forbidden')
            metadata(self.release)
            with self.subTest(name=name), self.assertRaises(InvalidRelease):
                verify(self.release, SHA)
            target.unlink()

    def test_source_inventory_incomplete(self):
        path = self.release / 'payload/app/Example.php'
        path.unlink()
        self.data['files'].pop('payload/app/Example.php')
        write(self.release / 'release.json', json.dumps(self.data))
        with self.assertRaises(InvalidRelease):
            verify(self.release, SHA)

    def test_missing_bootstrap_assets(self):
        (self.release / 'payload/public/vendor/bootstrap/bootstrap.min.css').unlink()
        metadata(self.release)
        with self.assertRaises(InvalidRelease):
            verify(self.release, SHA)

    def test_invalid_vite_graph(self):
        for entry in ({'file': '../outside.css', 'isEntry': True},
                      {'file': 'assets/missing.css', 'isEntry': True},
                      {'file': 'assets/app.css', 'isEntry': True, 'imports': ['missing']},
                      {'file': 'assets/app.css', 'isEntry': True, 'css': 'wrong-type'}):
            self.vite['resources/css/app.css'] = entry
            write(self.release / 'payload/public/build/manifest.json', json.dumps(self.vite))
            metadata(self.release)
            with self.subTest(entry=entry), self.assertRaises(InvalidRelease):
                verify(self.release, SHA)

    def test_unreferenced_frontend_file(self):
        write(self.release / 'payload/public/build/unknown.js', 'stale code')
        metadata(self.release)
        with self.assertRaises(InvalidRelease):
            verify(self.release, SHA)

    def test_symlinks_rejected(self):
        path = self.release / 'payload/app/link.php'
        try:
            path.symlink_to(self.root / 'dependencies.json')
        except OSError:
            self.skipTest('OS denies symlink creation; archive symlink rejection still tested')
        with self.assertRaises(InvalidRelease):
            verify(self.release, SHA)

    def test_hardlinks_rejected(self):
        import os
        os.link(self.release / 'payload/artisan', self.release / 'payload/app/hardlink.php')
        with self.assertRaises(InvalidRelease):
            verify(self.release, SHA)

    def test_unsafe_archive_members(self):
        for kind, name in ((tarfile.SYMTYPE, 'payload/app/link'), (tarfile.LNKTYPE, 'payload/app/hard'),
                           (tarfile.REGTYPE, '../escape'), (tarfile.REGTYPE, '/absolute')):
            archive = self.root / 'unsafe.tar.gz'
            with tarfile.open(archive, 'w:gz') as tar:
                item = tarfile.TarInfo(name)
                item.type = kind
                item.linkname = '../../outside'
                tar.addfile(item)
            with self.subTest(kind=kind), self.assertRaises(InvalidRelease):
                extract_release(archive, self.root / 'extracted', SHA, sha256(archive))
            self.assertFalse((self.root / 'extracted').exists())

    def test_valid_archive_and_bad_checksum(self):
        archive = self.root / 'valid.tar.gz'
        with tarfile.open(archive, 'w:gz') as tar:
            tar.add(self.release, arcname='.')
        with self.assertRaises(InvalidRelease):
            extract_release(archive, self.root / 'bad-checksum', SHA, '0' * 64)
        self.assertEqual(extract_release(archive, self.root / 'extracted', SHA, sha256(archive))['commit'], SHA)


class PreflightTests(Fixture):
    def test_complete_preflight(self):
        self.deploy.preflight()
        self.assert_protected()

    def test_missing_dependencies(self):
        (self.app / 'vendor/autoload.php').unlink()
        self.protected.pop(str(self.app / 'vendor/autoload.php'))
        self.rejected_preflight()

    def test_no_verified_provisioning_method(self):
        self.dep_record['method'] = 'unverified-existing-vendor'
        write(self.receipt, json.dumps(self.dep_record))
        self.rejected_preflight()

    def test_missing_dependency_receipt(self):
        self.receipt.unlink()
        self.rejected_preflight()

    def test_vendor_drift(self):
        write(self.app / 'vendor/unreviewed.php', 'drift')
        self.rejected_preflight()

    def test_dependency_platform_drift(self):
        self.dep_record['php_version_id'] = 80300
        write(self.receipt, json.dumps(self.dep_record))
        self.rejected_preflight()

    def test_removed_classmap_path(self):
        self.probe['app_classmap_paths'] = ['app/removed-class.php']
        self.rejected_preflight()

    def test_changed_composer_lock(self):
        write(self.release / 'payload/composer.lock', '{"changed":true}')
        metadata(self.release)
        self.rejected_preflight()

    def test_wrong_backup_commit(self):
        self.backup_record['commit'] = 'b' * 40
        write(self.backup / 'backup.json', json.dumps(self.backup_record))
        self.rejected_preflight()

    def test_missing_backups(self):
        (self.backup / 'database.sql.gz').unlink()
        self.rejected_preflight()

    def test_corrupt_backups(self):
        write(self.backup / 'runtime.tar.gz', 'broken')
        self.rejected_preflight()

    def test_stale_backup(self):
        self.backup_record['created_at'] -= 7200
        write(self.backup / 'backup.json', json.dumps(self.backup_record))
        self.rejected_preflight()

    def test_permission_failure(self):
        self.deploy.writable = lambda *args: False
        self.rejected_preflight()

    def test_real_write_probe_failure(self):
        with patch('deploy.tempfile.TemporaryFile', side_effect=PermissionError('fixture permission failure')):
            self.rejected_preflight()

    def test_missing_php_tool(self):
        with patch('deploy.shutil.which', return_value=None):
            self.rejected_preflight()

    def test_missing_php_extension(self):
        self.probe['extensions'].pop('pdo_mysql')
        self.rejected_preflight()

    def test_laravel_document_root_mismatch(self):
        self.probe['public_path'] = str(self.app / 'public')
        self.rejected_preflight()

    def test_old_php(self):
        self.probe['php_version_id'] = 80100
        self.rejected_preflight()

    def test_unreviewed_hosting(self):
        self.ready['capabilities']['web_cli_php_match'] = False
        self.save_ready()
        self.rejected_preflight()

    def test_unreviewed_upload_in_managed_directory(self):
        write(self.public / 'assets/customer.jpg', 'must never delete')
        self.rejected_preflight()
        self.assertEqual((self.public / 'assets/customer.jpg').read_text(), 'must never delete')

    def test_pending_migrations(self):
        self.probe['migrations'] = []
        self.rejected_preflight()

    def test_changed_migration_file(self):
        write(self.app / 'database/migrations/unreviewed.php', 'new migration')
        self.rejected_preflight()

    def test_existing_maintenance(self):
        write(self.app / 'storage/framework/down', '{}')
        with self.assertRaises(InvalidRelease):
            self.deploy.preflight()
        self.assertEqual((self.app / 'storage/framework/down').read_text(), '{}')

    def test_unhealthy_baseline(self):
        self.deploy.fetch = lambda url: b'Not the application'
        self.rejected_preflight()

    def test_overlapping_roots(self):
        self.deploy.release = self.app
        self.rejected_preflight()


class RecoveryTests(Fixture):
    def test_success_preserves_protected_files(self):
        self.assertEqual(self.deploy.activate(), 'healthy-awaiting-functional-review')
        self.assertEqual(self.deploy.inventory(), self.deploy.new_inventory)
        self.assertFalse((self.app / 'app/obsolete.php').exists())
        self.assertFalse((self.app / 'storage/framework/down').exists())
        self.assertTrue((self.deploy.history / 'previous/app/obsolete.php').is_file())
        self.assertEqual(json.loads((self.state / 'last-working.json').read_text())['commit'], SHA)
        self.assert_protected()

    def test_partial_copy_failure_restores_previous_code(self):
        original_replace = __import__('os').replace
        fired = []
        def fail_once(source, target):
            if Path(target) == self.app / 'routes/web.php' and not fired:
                fired.append(True)
                raise OSError('Injected partial deployment failure')
            return original_replace(source, target)
        with patch('deploy.os.replace', side_effect=fail_once), self.assertRaises(InvalidRelease):
            self.deploy.activate()
        self.assertTrue(fired)
        self.assertEqual(self.deploy.phase, 'failed-previous-code-restored-healthy')
        self.assertEqual(self.deploy.inventory(), self.ready['managed_inventory'])
        self.assertFalse((self.app / 'storage/framework/down').exists())
        self.assert_protected()

    def test_cache_failure_restores_previous_code(self):
        failed = []
        def fail_once(args, cwd):
            if 'artisan' in args and not failed:
                failed.append(True)
                raise InvalidRelease('Injected cache failure')
            return self.fake_run(args, cwd)
        self.deploy.run = fail_once
        with self.assertRaises(InvalidRelease):
            self.deploy.activate()
        self.assertEqual(self.deploy.phase, 'failed-previous-code-restored-healthy')
        self.assertEqual(self.deploy.inventory(), self.ready['managed_inventory'])
        self.assert_protected()

    def test_failed_recovery_remains_in_maintenance(self):
        def fail_cache(args, cwd):
            if 'artisan' in args:
                raise InvalidRelease('Injected persistent cache failure')
            return self.fake_run(args, cwd)
        self.deploy.run = fail_cache
        with self.assertRaises(InvalidRelease):
            self.deploy.activate()
        self.assertEqual(self.deploy.phase, 'failed-closed-maintenance-operator-required')
        self.assertTrue((self.app / 'storage/framework/down').is_file())
        self.assertEqual((self.app / 'storage/framework/maintenance.php').read_text(), GUARD)
        self.assert_protected()

    def test_bad_new_health_recovers_old_code(self):
        def fetch(url):
            if (self.app / 'app/Example.php').read_text() == 'new application':
                return b'broken response'
            return self.fake_http(url)
        self.deploy.fetch = fetch
        with self.assertRaises(InvalidRelease):
            self.deploy.activate()
        self.assertEqual(self.deploy.phase, 'failed-previous-code-restored-healthy')
        self.assert_protected()

    def test_interrupt_recovers(self):
        original = self.deploy.synchronize
        fired = []
        def interrupted(source, desired, current):
            if not fired:
                fired.append(True)
                write(self.app / 'app/Example.php', 'interrupted partial update')
                raise KeyboardInterrupt()
            return original(source, desired, current)
        self.deploy.synchronize = interrupted
        with self.assertRaises(InvalidRelease):
            self.deploy.activate()
        self.assertEqual(self.deploy.phase, 'failed-previous-code-restored-healthy')
        self.assert_protected()

    def test_recovery_permission_loss_reports_unconfirmed_maintenance(self):
        original = self.deploy.guard
        calls = []
        def guard():
            calls.append(True)
            if len(calls) > 1:
                raise PermissionError('Injected guard write failure')
            original()
        self.deploy.guard = guard
        self.deploy.synchronize = lambda *args: (_ for _ in ()).throw(OSError('copy failure'))
        with self.assertRaises(InvalidRelease):
            self.deploy.activate()
        self.assertEqual(self.deploy.phase, 'critical-maintenance-unconfirmed-operator-required')
        self.assert_protected()


    def test_incomplete_transaction_blocks_following_deployments(self):
        history = self.state / 'release-interrupted'
        history.mkdir()
        with self.assertRaises(InvalidRelease):
            assert_no_unresolved(self.state)
        write(history / 'status.json', json.dumps({'phase': 'code-copied'}))
        with self.assertRaises(InvalidRelease):
            assert_no_unresolved(self.state)
        write(history / 'status.json', json.dumps({'phase': 'failed-closed-maintenance-operator-required'}))
        with self.assertRaises(InvalidRelease):
            assert_no_unresolved(self.state)
        write(history / 'status.json', json.dumps({'phase': 'failed-previous-code-restored-healthy'}))
        assert_no_unresolved(self.state)

    @unittest.skipUnless(__import__('os').name == 'posix', 'Native POSIX flock requires Linux; runs in GitHub CI')
    def test_native_server_lock_excludes_second_deployer(self):
        with server_lock(self.state):
            with self.assertRaises(InvalidRelease):
                with server_lock(self.state):
                    self.fail('Concurrent deployment obtained lock')



class PackagingTests(Fixture):
    def test_package_git_objects_and_reject_dirty_inputs(self):
        repo = self.root / 'git-fixture'
        shutil.copytree(self.release / 'payload', repo)
        def git(*args):
            subprocess.run(['git', *args], cwd=repo, check=True, capture_output=True)
        git('init')
        git('add', '.')
        git('-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-m', 'fixture')
        out = self.root / 'packaged'
        commit = package(repo, out)
        self.assertEqual(verify(out, commit)['commit'], commit)
        write(repo / 'app/Second.php', 'tracked source omitted by export')
        write(repo / '.gitattributes', 'app/Second.php export-ignore')
        git('add', '.')
        git('-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.invalid', 'commit', '-m', 'export-ignore fixture')
        with self.assertRaises(InvalidRelease):
            package(repo, self.root / 'incomplete-output')
        self.assertFalse((self.root / 'incomplete-output').exists())
        write(repo / 'app/Example.php', 'dirty source')
        with self.assertRaises(InvalidRelease):
            package(repo, self.root / 'dirty-output')
        self.assertFalse((self.root / 'dirty-output').exists())


if __name__ == '__main__':
    unittest.main(verbosity=2)
