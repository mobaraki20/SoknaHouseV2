#!/usr/bin/env python3
"""QA-only disposable MariaDB tests of actual dev24 UpdateManagerV2 path."""
import hashlib, json, os, shutil, subprocess, sys, tempfile, zipfile
from pathlib import Path
from staging_runner import ARCHIVE_PARTS, EXPECTED_KIT_SHA256, EXPECTED_CLEAN_SHA256, DB_ROOT_PASS, mysql, install, unpack

ROOT=Path(__file__).resolve().parents[2]
REPORT={'staging_only':True,'production_approved':False,'checks':[]}
def check(label,ok,detail=''):
    REPORT['checks'].append({'name':label,'pass':bool(ok),'detail':str(detail)[:400]})
    print('PASS' if ok else 'FAIL',label,str(detail)[:150],flush=True)
    if not ok:raise AssertionError(label+': '+str(detail))
def h(data):return hashlib.sha256(data).hexdigest()
def php(app,code,*args):
    p=subprocess.run(['php','-r',"define('SOKNA_S2S_REQUEST',true);require 'app/bootstrap.php';"+code,*map(str,args)],cwd=app,capture_output=True,text=True,timeout=90)
    if p.returncode:raise RuntimeError('php CLI error: '+p.stderr[-500:])
    return p.stdout.strip()
def update_package(kit,target,failure=None):
    old=kit/'Sokna-House-2.6.19-dev.24-clean-install.zip'
    new=kit/'SOKNAHOUSE_2.6.19-dev.25-qa2_STAGING_CLEAN_INSTALL.zip'
    with zipfile.ZipFile(old) as a,zipfile.ZipFile(new) as b:
        old_hash={n:h(a.read(n)) for n in a.namelist() if not n.endswith('/')}
        new_data={n:b.read(n) for n in b.namelist() if not n.endswith('/')}
    changed={n:data for n,data in sorted(new_data.items()) if old_hash.get(n)!=h(data)}
    check('exact 35 file differences',len(changed)==35)
    schema_h=h(new_data['database/schema.sql'])
    check('exact QA2 schema hash',schema_h=='150064edb884ccea26792e1301e8c555811d5aa6c6eab736a469000b9d51d7ea')
    mig=(kit/'migration_create_only.sql').read_text().strip()+"\n"
    mig+="INSERT INTO `{{prefix}}schema_migrations` (`version`,`checksum`,`operation_id`,`status`) VALUES ('2.6.0-rc7-qa1','"+schema_h+"','qa-update-ci','success') ON DUPLICATE KEY UPDATE `checksum`=VALUES(`checksum`),`status`=VALUES(`status`);\n"
    if failure=='ddl':mig+='SELECT * FROM `{{prefix}}qa_missing_table_failure_injection`;\n'
    if failure=='files':changed['zzqa_conflict/never.txt']=b'forced-conflicting-parent-file'
    manifest={'format':'sokna-v2-update','from_version':'2.6.19-dev.24','to_version':'2.6.19-dev.25-qa2','files':{n:h(v) for n,v in changed.items()},'delete':[]}
    with zipfile.ZipFile(target,'w',compression=zipfile.ZIP_DEFLATED) as z:
        z.writestr('update.json',json.dumps(manifest))
        z.writestr('migration.sql',mig)
        z.writestr('rollback.sql','-- Do not drop durable financial idempotency evidence.\nSELECT 1;\n')
        for n,data in changed.items():z.writestr('payload/'+n,data)
    return manifest

def loop(app,operation,expected):
    history=[]
    for i in range(100):
        out=php(app,"echo json_encode(UpdateManagerV2::continue($argv[1],1),JSON_THROW_ON_ERROR);",operation)
        state=json.loads(out)
        history.append((state['state'],state['phase']))
        if state['state'] in ('success','rolled_back','rollback_failed'):break
    else:raise AssertionError('update did not converge within 100 steps')
    check('Updater state '+expected,state['state']==expected,state)
    return history

def scenario(kit,work,name,dbname,port,failure=None):
    app=work/name;unpack(kit/'Sokna-House-2.6.19-dev.24-clean-install.zip',app)
    mysql('CREATE DATABASE `'+dbname+'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
    install(app,dbname,port)
    mysql("INSERT INTO `"+dbname+"`.`sokna_qa_settings` (`key`,`value`) VALUES ('qa_update_preserve','fixed-before-upgrade') ON DUPLICATE KEY UPDATE `value`='fixed-before-upgrade'")
    if failure=='files':(app/'zzqa_conflict').write_text('conflict')
    update_zip=work/(name+'.zip');update_package(kit,update_zip,failure)
    op=php(app,"echo UpdateManagerV2::prepare($argv[1],1);",update_zip)
    check(name+' operation initialized',op.startswith('upd-'),op)
    check(name+' maintenance acquired',(app/'storage/maintenance.lock').is_file())
    expected='success' if failure is None else 'rolled_back'
    history=loop(app,op,expected)
    got=php(app,"echo UpdateManagerV2::currentVersion();")
    want='2.6.19-dev.25-qa2' if failure is None else '2.6.19-dev.24'
    check(name+' final VERSION',got==want,got)
    value=mysql("SELECT `value` FROM `"+dbname+"`.`sokna_qa_settings` WHERE `key`='qa_update_preserve'").strip()
    check(name+' pre-existing data preserved',value=='fixed-before-upgrade',value)
    record=mysql("SELECT COUNT(*) FROM `"+dbname+"`.`sokna_qa_schema_migrations` WHERE version='2.6.0-rc7-qa1' AND status='success'").strip()
    check(name+' schema marker',record==('1' if failure is None else '0'),record)
    check(name+' maintenance cleaned',not (app/'storage/maintenance.lock').exists())
    if failure=='ddl':
        # Non-destructive rollback intentionally leaves a harmless empty additive table.
        check(name+' additive table retained',mysql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='"+dbname+"' AND TABLE_NAME='sokna_qa_financial_commit_keys'").strip()=='1')
    return {'name':name,'failure':failure,'history':history}

def main():
    check('disposable database password',bool(DB_ROOT_PASS))
    check('four pinned QA2 kit parts',len(ARCHIVE_PARTS)==4)
    with tempfile.TemporaryDirectory(prefix='soknahouse-updater-ci-') as tmp:
        work=Path(tmp);kitz=work/'kit.zip'
        with kitz.open('wb') as out:
            for part in ARCHIVE_PARTS:
                with part.open('rb') as src:shutil.copyfileobj(src,out)
        check('pinned kit',h(kitz.read_bytes())==EXPECTED_KIT_SHA256)
        kit=work/'kit';unpack(kitz,kit)
        check('pinned QA2 clean',h((kit/'SOKNAHOUSE_2.6.19-dev.25-qa2_STAGING_CLEAN_INSTALL.zip').read_bytes())==EXPECTED_CLEAN_SHA256)
        REPORT['scenarios']=[
            scenario(kit,work,'success','sokna_qa_upd_success',8791),
            scenario(kit,work,'fail_files','sokna_qa_upd_files',8792,'files'),
            scenario(kit,work,'fail_after_ddl','sokna_qa_upd_ddl',8793,'ddl')]
        # This runner does not assert atomic prepare snapshot, after-success rewinds, or browser UAT.
        check('production release remains blocked',True,'prepare() in official dev24 lacks QA2 exclusive snapshot lock')
if __name__=='__main__':
    try:main();REPORT['ok']=True
    except Exception as e:REPORT['ok']=False;REPORT['error']=type(e).__name__+': '+str(e)[:600];print('FAILED',REPORT['error'],file=sys.stderr)
    finally:
        (ROOT/'staging-update-ci-report.json').write_text(json.dumps(REPORT,ensure_ascii=False,indent=2))
    sys.exit(0 if REPORT['ok'] else 1)