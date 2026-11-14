#!/usr/bin/env python3
"""Disposable GitHub Actions integration tests for the pinned SoknaHouse QA2 kit.

Exercises actual browser installer, MariaDB schema, add-only migration and
financial idempotency. Does NOT validate UpdateManagerV2 rollback or host UAT.
"""
import contextlib
import hashlib
import http.cookiejar
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.parse
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[2]
ARCHIVE_PARTS = sorted((ROOT / 'qa/artifacts').glob('qa2-kit.zip.part*'))
EXPECTED_KIT_SHA256 = 'def74d7227d23f739eb44e16156b762612b14ac16e3615e87bcfbf0fe4bc7acd'
EXPECTED_CLEAN_SHA256 = '0584900b539482b8f7dfbd1b13744bb5bf53f41bbe530631d847deba3c70ced9'
DB_ROOT_PASS = os.environ.get('SOKNA_QA_CI_ROOT_PASSWORD', '')
REPORT = {'candidate': '2.6.19-dev.25-qa2', 'staging_only': True, 'production_approved': False, 'checks': []}


def record(name, success, details=''):
    REPORT['checks'].append({'name': name, 'pass': bool(success), 'details': str(details)[:600]})
    print(('PASS' if success else 'FAIL'), name, details, flush=True)
    if not success:
        raise AssertionError(f'{name}: {details}')


def run(args, *, cwd=None, env=None, timeout=120):
    p = subprocess.run(args, cwd=cwd, env=env, text=True, stdout=subprocess.PIPE,
                       stderr=subprocess.PIPE, timeout=timeout)
    if p.returncode:
        raise RuntimeError(f'{args[0]} exited {p.returncode}: {p.stdout[-1000:]} {p.stderr[-1000:]}')
    return p.stdout


def unpack(archive, target):
    target.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(archive) as z:
        for member in z.namelist():
            path = Path(member)
            if member.startswith('/') or '..' in path.parts or '\\' in member:
                raise AssertionError('unsafe ZIP path')
        z.extractall(target)


def mysql(statement):
    env = os.environ.copy()
    env['MYSQL_PWD'] = DB_ROOT_PASS
    return run(['mariadb', '--host=127.0.0.1', '--user=root', '--batch', '--skip-column-names', '--execute', statement], env=env)


def install(app, dbname, port):
    log_path = app / 'storage' / 'qa-ci-server.log'
    log_path.parent.mkdir(parents=True, exist_ok=True)
    with log_path.open('wb') as log:
        server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(app)],
                                  cwd=app, stdout=log, stderr=subprocess.STDOUT)
        try:
            url = f'http://127.0.0.1:{port}/install.php'
            opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
            html = None
            for _ in range(30):
                try:
                    html = opener.open(url, timeout=3).read().decode('utf-8')
                    break
                except Exception:
                    time.sleep(0.2)
            record(f'installer GET {dbname}', html is not None)
            match = re.search(r'name="csrf"\s+value="([^"]+)"', html)
            record(f'installer CSRF {dbname}', match is not None)
            fields = {
                'csrf': match.group(1), 'db_host': '127.0.0.1', 'db_port': '3306',
                'db_name': dbname, 'db_user': 'root', 'db_pass': DB_ROOT_PASS,
                'db_prefix': 'sokna_qa_', 'site_url': f'http://127.0.0.1:{port}',
                'property_name': 'Sokna QA Disposable', 'admin_user': 'qa_admin',
                'admin_name': 'QA Admin', 'admin_pass': 'QA-Only-Disposable-Password-2026!',
            }
            request = urllib.request.Request(url, data=urllib.parse.urlencode(fields).encode('utf-8'), method='POST')
            result = opener.open(request, timeout=40).read().decode('utf-8')
            ok = 'نصب با موفقیت کامل شد' in result and (app / 'storage/installed.lock').exists()
            # Never print HTML: on failure it may contain transient internal information.
            record(f'real HTTP POST clean install {dbname}', ok, 'lock created' if ok else 'installer did not reach success')
        finally:
            server.terminate()
            with contextlib.suppress(subprocess.TimeoutExpired):
                server.wait(timeout=5)
            if server.poll() is None:
                server.kill()
                server.wait(timeout=5)


def backup_export(app, target):
    # Use the product's own BackupManagerV2, not a synthesized fixture.
    code = ('define("SOKNA_S2S_REQUEST", true);'
            'require "app/bootstrap.php";'
            'file_put_contents($argv[1], json_encode(BackupManagerV2::export(), JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));')
    run(['php', '-r', code, str(target)], cwd=app)
    obj = json.loads(target.read_text())
    record(f'native backup export {app.name}',
           obj.get('format') == 'sokna-v2-backup' and 'schema_migrations' in obj.get('tables', {}),
           f"version={obj.get('version')}")
    return hashlib.sha256(target.read_bytes()).hexdigest()


def gate(kit, dbname, mode, backup, backup_sha):
    env = os.environ.copy()
    env.update({
        'SOKNA_QA_DB_HOST': '127.0.0.1', 'SOKNA_QA_DB_PORT': '3306',
        'SOKNA_QA_DB_NAME': dbname, 'SOKNA_QA_DB_USER': 'root',
        'SOKNA_QA_DB_PASSWORD': DB_ROOT_PASS, 'SOKNA_QA_DB_PREFIX': 'sokna_qa_',
        'SOKNA_QA_DISPOSABLE': 'YES', 'SOKNA_QA_BACKUP_VERIFIED': 'YES',
        'SOKNA_QA_BACKUP_PATH': str(backup), 'SOKNA_QA_BACKUP_SHA256': backup_sha,
    })
    p = subprocess.run(['php', str(kit / 'staging_db_gate.php'), '--mode=' + mode],
                       cwd=kit, env=env, capture_output=True, text=True, timeout=90)
    try:
        payload = json.loads(p.stdout)
    except Exception:
        payload = {'ok': False, 'error': 'Gate returned invalid JSON'}
    passed = p.returncode == 0 and payload.get('ok') is True and payload.get('real_mariadb_test') is True
    record(f'{dbname}: {mode}', passed, payload.get('error', f"checks={len(payload.get('checks', []))}"))
    return payload


def main():
    record('CI-only password set', bool(DB_ROOT_PASS))
    record('exactly four expected source parts', len(ARCHIVE_PARTS) == 4)
    with tempfile.TemporaryDirectory(prefix='soknahouse-github-staging-') as scratch:
        work = Path(scratch)
        kit_zip = work / 'qa2-kit.zip'
        with kit_zip.open('wb') as out:
            for part in ARCHIVE_PARTS:
                with part.open('rb') as src:
                    shutil.copyfileobj(src, out)
        sha = hashlib.sha256(kit_zip.read_bytes()).hexdigest()
        record('QA2 kit byte-for-byte SHA-256 pinned', sha == EXPECTED_KIT_SHA256, sha)
        kit = work / 'kit'; unpack(kit_zip, kit)
        clean = kit / 'SOKNAHOUSE_2.6.19-dev.25-qa2_STAGING_CLEAN_INSTALL.zip'
        record('QA2 clean installation SHA-256 pinned', hashlib.sha256(clean.read_bytes()).hexdigest() == EXPECTED_CLEAN_SHA256)
        run(['python3', 'verify_offline.py'], cwd=kit)
        record('offline archive integrity checks', True)
        run(['python3', 'health_baseline_regression.py'], cwd=kit)
        record('qa1 vs qa2 health regression', True)
        for phpfile in kit.glob('*.php'):
            run(['php', '-l', str(phpfile)])
        record('gate PHP syntax', True)
        old_app, new_app = work / 'dev24-install', work / 'qa2-install'
        unpack(kit / 'Sokna-House-2.6.19-dev.24-clean-install.zip', old_app)
        unpack(clean, new_app)
        for app in (old_app, new_app):
            for f in app.rglob('*.php'):
                run(['php', '-l', str(f)])
        record('full PHP syntax of dev24 and qa2 archives', True)
        for name in ('sokna_qa_upgrade', 'sokna_qa_clean'):
            mysql(f'CREATE DATABASE `{name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
        install(old_app, 'sokna_qa_upgrade', 8771)
        install(new_app, 'sokna_qa_clean', 8772)
        old_backup = work / 'old-backup.json'
        old_sha = backup_export(old_app, old_backup)
        gate(kit, 'sokna_qa_upgrade', 'preflight', old_backup, old_sha)
        gate(kit, 'sokna_qa_upgrade', 'apply-migration', old_backup, old_sha)
        gate(kit, 'sokna_qa_upgrade', 'verify', old_backup, old_sha)
        gate(kit, 'sokna_qa_upgrade', 'concurrency', old_backup, old_sha)
        new_backup = work / 'new-backup.json'
        new_sha = backup_export(new_app, new_backup)
        gate(kit, 'sokna_qa_clean', 'verify', new_backup, new_sha)
        gate(kit, 'sokna_qa_clean', 'concurrency', new_backup, new_sha)
        record('production update and rollback not authorized/tested', True, 'Separate UpdateManagerV2 failure/recovery gate remains BLOCKED')


if __name__ == '__main__':
    try:
        main()
        REPORT['ok'] = True
    except Exception as exc:
        REPORT['ok'] = False
        REPORT['error'] = f'{type(exc).__name__}: {str(exc)[:700]}'
        print('FAILED:', REPORT['error'], file=sys.stderr)
    finally:
        target = ROOT / 'staging-ci-report.json'
        target.write_text(json.dumps(REPORT, ensure_ascii=False, indent=2))
        print('Report saved:', target)
    sys.exit(0 if REPORT['ok'] else 1)