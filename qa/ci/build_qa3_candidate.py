#!/usr/bin/env python3
"""Deterministic staged (NOT production-approved) paired clean/update package builder.
Only uses pinned SoknaHouse archives and reviewed QA3 source bytes; rejects source drift.
"""
from pathlib import Path
from zipfile import ZipFile,ZipInfo,ZIP_DEFLATED
import hashlib,json,re,sys,io,subprocess,tempfile,os
ROOT=Path(__file__).resolve().parent
KIT=Path('/mnt/data/soknahouse-work/SOKNAHOUSE_STAGING_DB_VALIDATION_KIT_QA2_20261114.zip')
BRIDGE=Path('/mnt/data/soknahouse-work/SOKNAHOUSE_DEV24_OFFLINE_BRIDGE_REVIEW_ONLY_NOT_INSTALLABLE_20261114.zip')
PROJECT_ROOT=Path(__file__).resolve().parents[2] if __file__.endswith('qa/ci/build_qa3_candidate.py') else None

def source_kit():
    if KIT.is_file():return KIT.read_bytes()
    assert PROJECT_ROOT is not None
    from staging_runner import ARCHIVE_PARTS
    assert ARCHIVE_PARTS
    return b''.join(p.read_bytes() for p in ARCHIVE_PARTS)

def reviewed_updater(qa2_bytes):
    if BRIDGE.is_file():
        assert H(BRIDGE.read_bytes())==BRIDGE_SHA
        with ZipFile(BRIDGE) as z: return z.read('review_source/app/UpdateManagerV2.php')
    assert PROJECT_ROOT is not None
    patch=PROJECT_ROOT/'qa/patches/QA3_SNAPSHOT_FAIL_CLOSED_REVIEW_ONLY.patch'
    assert patch.is_file()
    with tempfile.TemporaryDirectory(prefix='qa3-reviewed-source-') as t:
        target=Path(t)/'app/UpdateManagerV2.php';target.parent.mkdir(parents=True);target.write_bytes(qa2_bytes)
        result=subprocess.run(['patch','--batch','--forward','-p1','-i',str(patch)],cwd=t,capture_output=True,text=True)
        assert result.returncode==0, result.stderr
        return target.read_bytes()
KIT_SHA='def74d7227d23f739eb44e16156b762612b14ac16e3615e87bcfbf0fe4bc7acd'
BRIDGE_SHA='3606f1698fffc1cb7ab75dd228b5460735de7b3c4e1cb922b1722c44a8025a5a'
BASE='Sokna-House-2.6.19-dev.24-clean-install.zip'
QA2='SOKNAHOUSE_2.6.19-dev.25-qa2_STAGING_CLEAN_INSTALL.zip'
TARGET='2.6.19-dev.25-qa3-bridge'
STAMP=(2026,11,14,0,0,0)
H=lambda b:hashlib.sha256(b).hexdigest()
assert H(source_kit())==KIT_SHA
if BRIDGE.is_file():assert H(BRIDGE.read_bytes())==BRIDGE_SHA

def bytes_archive(src):
    with ZipFile(io.BytesIO(src) if isinstance(src, bytes) else src) as f:
        assert f.testzip() is None
        return {k:f.read(k) for k in f.namelist() if not k.endswith('/')}

def writezip(path, data):
    with ZipFile(path,'w') as z:
        for key,raw in sorted(data.items()):
            assert key and not key.startswith('/') and '..' not in Path(key).parts
            zi=ZipInfo(key,date_time=STAMP);zi.compress_type=ZIP_DEFLATED;zi.external_attr=0o100644<<16
            z.writestr(zi,raw,compress_type=ZIP_DEFLATED,compresslevel=9)
    with ZipFile(path) as z:assert z.testzip() is None

def create_packages(out=ROOT):
    out=Path(out);out.mkdir(parents=True,exist_ok=True)
    with ZipFile(io.BytesIO(source_kit())) as z:
        baseline=bytes_archive(z.read(BASE));candidate=bytes_archive(z.read(QA2))
        migration=z.read('migration_create_only.sql').decode().strip()
    updater=reviewed_updater(candidate['app/UpdateManagerV2.php'])
    assert H(updater)=='b34617a61645c85255af790ea9ef4c1d72f037805f3c89edf7cfa0e3bdac52a9'
    assert baseline['VERSION'].strip()==b'2.6.19-dev.24'
    assert candidate['VERSION'].strip()==b'2.6.19-dev.25-qa2'
    assert b'snapshot_isolated' in updater
    assert len([n for n in candidate if candidate.get(n)!=baseline.get(n)])==35
    assert H(candidate['database/schema.sql'])=='150064edb884ccea26792e1301e8c555811d5aa6c6eab736a469000b9d51d7ea'
    candidate['app/UpdateManagerV2.php']=updater
    candidate['VERSION']=(TARGET+'\n').encode()
    notice='''# SoknaHouse {version} — STAGING REVIEW ONLY\n\n**اجازه نصب روی سامانه عملیاتی صادر نشده است.**\n\nنسخه رسمی مبدا: 2.6.19-dev.24. این Clean Install فقط برای دیتابیس خالی آزمایشی است؛ بسته Update تنها پس از اجرای صحیح پل سه‌فایلی و قطع مستقل تمام درخواست‌های هاست در محیط آزمایشی قابل استفاده است. اجرای مستقیم Update با موتور قدیمی dev.24 می‌تواند داده ثبت‌شده را هنگام Rollback از بین ببرد.\n\nروش نصب Staging: PHP 8.3 و MariaDB 11.4، ریشه سایت و دیتابیس **غیرعملیاتی** خالی، استخراج Clean Install، اجرای install.php، ارزیابی گزارش صحت و UAT.\n\nهیچ فایل تنظیمات، اطلاعات کاربران، کلید دسترسی یا DB واقعی در بسته‌ها وجود ندارد.\n\nآزمون موفق MariaDB/HTML HTTP به معنای آزمون مرورگرهای متعدد، پرداخت و چاپ واقعی، یا احراز ایمنی هاست جاری نیست.\nگیت انتقال واقعی: اثبات ایزوله‌شدن ورودی‌ها از بالادست، پایان همه درخواست‌های قبلی، توقف worker/cron، پشتیبان مستقل و کنترل‌شده، Rollback آزمایشی.\n\nبرای انتقال رسمی از dev24، **ابتدا پل مستقل مورد تأیید و مجوز انتشار** لازم است. صرف بارگذاری این ZIP در صفحه Update مجاز نیست.\n'''.format(version=TARGET)
    candidate['README_FA.md']=notice.encode()
    candidate['INSTALL_FA.md']=notice.encode()
    candidate['RELEASE_CHECK_FA.md']=('## '+TARGET+' — QA evidence not production approval\n\n'
      '- Exact QA2 source plus QA3 reviewed fail-closed UpdateManager; same source between clean/update.\n'
      '- CI database tests and HTTP smoke previously passed; this exact paired archive requires separate CI acceptance.\n'
      '- Official first-transition dev24 updater unsafe. A separately qualified offline bridge is mandatory.\n'
      '- Real-host ingress/drain and browser/transactional UAT not assumed. Production=BLOCKED.\n').encode()
    # Compute checksums after all content edits; checksum file is excluded from itself.
    checks=''.join(f'{H(v)}  {k}\n' for k,v in sorted(candidate.items()) if k!='CHECKSUMS.sha256')
    candidate['CHECKSUMS.sha256']=checks.encode()
    assert all(len(line.split('  ',1)[0])==64 for line in checks.splitlines())
    clean_path=out/f'SOKNAHOUSE_{TARGET}_STAGING_CLEAN_INSTALL.zip'
    writezip(clean_path,candidate)
    changed={k:v for k,v in candidate.items() if baseline.get(k)!=v}
    assert all(not k.startswith(('config/','storage/','uploads/')) for k in changed)
    marker=f"INSERT INTO `{{{{prefix}}}}schema_migrations` (`version`,`checksum`,`operation_id`,`status`) VALUES ('2.6.0-rc7-qa1','{H(candidate['database/schema.sql'])}','qa3-bridge-package','success') ON DUPLICATE KEY UPDATE `checksum`=VALUES(`checksum`),`status`=VALUES(`status`);"
    manifest={'format':'sokna-v2-update','from_version':'2.6.19-dev.24','to_version':TARGET,'files':{k:H(v) for k,v in sorted(changed.items())},'delete':[]}
    update={'update.json':(json.dumps(manifest,ensure_ascii=False,indent=2,sort_keys=True)+'\n').encode(),
       'migration.sql':(migration+'\n'+marker+'\n').encode(),
       'rollback.sql':b'-- do not destroy financial idempotency rows\nSELECT 1;\n',
       'IMPORTANT_STAGING_ONLY.txt':notice.encode()}
    update.update({'payload/'+k:v for k,v in changed.items()})
    upd_path=out/f'SOKNAHOUSE_dev24-to-{TARGET}_STAGING_UPDATE_DO_NOT_DEPLOY.zip'
    writezip(upd_path,update)
    with ZipFile(clean_path) as z:
        assert all(H(z.read(k))==s for s,k in (line.split('  ',1) for line in z.read('CHECKSUMS.sha256').decode().splitlines()))
    with ZipFile(upd_path) as z:
        assert all(H(z.read('payload/'+k))==v for k,v in manifest['files'].items())
        assert all(z.read('payload/'+k)==candidate[k] for k in manifest['files'])
        assert 'DROP' not in z.read('rollback.sql').decode().upper()
    report={'production_approved':False,'target_version':TARGET,'clean_file':clean_path.name,
        'update_file':upd_path.name,'clean_sha256':H(clean_path.read_bytes()),'update_sha256':H(upd_path.read_bytes()),
        'qa2_kit_sha256':KIT_SHA,'qa3_review_source_sha256':H(updater),'payload_files':len(changed),
        'manifest_hashes_verified':True,'payload_matches_clean':True,'offline_bridge_required':True,
        'update_engine_old_dev24_must_not_be_used':True,'data_not_included':True}
    (out/'SOKNAHOUSE_QA3_STAGING_PACKAGE_MANIFEST_20261114.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
    print(json.dumps(report,ensure_ascii=False,indent=2));return clean_path,upd_path,report

if __name__=='__main__':create_packages(Path(sys.argv[1]) if len(sys.argv)>1 else ROOT)