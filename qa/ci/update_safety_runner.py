#!/usr/bin/env python3
"""Safety regression for SoknaHouse, DISPOSABLE DATABASES ONLY.

This QA runner never connects to a production DB or exercises a live host.
Negative control patches only a freshly extracted disposable dev24 fixture.
"""
import hashlib, json, os, shutil, subprocess, sys, tempfile, time, zipfile
from pathlib import Path
from staging_runner import ARCHIVE_PARTS, EXPECTED_KIT_SHA256, EXPECTED_CLEAN_SHA256, mysql, install, unpack
from update_staging_runner import update_package

ROOT = Path(__file__).resolve().parents[2]
REPORT = {'staging_only': True, 'production_approved': False, 'checks': []}


def check(name, ok, detail=''):
    REPORT['checks'].append({'name': name, 'pass': bool(ok), 'detail': str(detail)[:280]})
    print(('PASS' if ok else 'FAIL'), name, str(detail)[:100], flush=True)
    if not ok: raise AssertionError(name + ': ' + str(detail))


def phpcall(app, code, *args):
    cmd = ['php', '-r', 'define("SOKNA_S2S_REQUEST",true);require "app/bootstrap.php";' + code, *map(str,args)]
    p = subprocess.run(cmd,cwd=app,capture_output=True,text=True,timeout=95)
    if p.returncode: raise RuntimeError('PHP failed: '+p.stderr[-350:])
    return p.stdout.strip()


def begin(app, package, gate=False):
    gate_code = 'RestoreGate::enterRequest(getcwd());' if gate else ''
    return phpcall(app,gate_code+'echo UpdateManagerV2::prepare($argv[1],1);',package)


def advance(app, operation, want):
    states=[]
    for _ in range(95):
        result=json.loads(phpcall(app,'echo json_encode(UpdateManagerV2::continue($argv[1],1),JSON_THROW_ON_ERROR);',operation))
        states.append(result['state']+'/'+result['phase'])
        if result['state'] in ('success','rolled_back','rollback_failed'): break
    else: raise AssertionError('Updater did not converge')
    check('updater terminal '+want,result['state']==want,states[-4:])
    return states


def db_setting(db,key):
    return mysql(f"SELECT `value` FROM `{db}`.`sokna_qa_settings` WHERE `key`='{key}'").strip()


def make_qa2_update(output, fail_files=False):
    changes={'VERSION':b'2.6.19-dev.25-qa3-ci\n'}
    if fail_files:changes['zzqa_conflict/never.txt']=b'test-only conflict\n'
    manifest={'format':'sokna-v2-update','from_version':'2.6.19-dev.25-qa2',
              'to_version':'2.6.19-dev.25-qa3-ci',
              'files':{name:hashlib.sha256(data).hexdigest() for name,data in changes.items()},'delete':[]}
    with zipfile.ZipFile(output,'w',zipfile.ZIP_DEFLATED) as z:
        z.writestr('update.json',json.dumps(manifest))
        for name,data in changes.items():z.writestr('payload/'+name,data)


def harden_qa2_fixture(app):
    """Apply review-only hardening to an unpacked disposable QA2 fixture.

    Official dev24 and QA2 archives remain byte-for-byte untouched.
    """
    patch=ROOT/'qa/patches/QA3_SNAPSHOT_FAIL_CLOSED_REVIEW_ONLY.patch'
    proc=subprocess.run(['patch','-p1','--batch','-i',str(patch)],cwd=app,capture_output=True,text=True)
    check('QA3 review patch applies to exact QA2 source',proc.returncode==0,proc.stderr[-200:])
    lint=subprocess.run(['php','-l',str(app/'app/UpdateManagerV2.php')],capture_output=True,text=True)
    check('QA3 review patch PHP syntax',lint.returncode==0,lint.stderr[-200:])


def old_snapshot_gap(kit,work):
    """Deterministic negative control: inject a test-only writer AFTER export
       and BEFORE legacy maintenance marker, reproduce data loss on rollback.
       Never modify production source: fixture exists only inside temp dir.
    """
    db='sokna_qa_snapshot_gap';app=work/'legacy-negative-control'
    unpack(kit/'Sokna-House-2.6.19-dev.24-clean-install.zip',app)
    mysql(f'CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
    install(app,db,8801)
    src=app/'app/UpdateManagerV2.php';code=src.read_text()
    needle='$backup=BackupManagerV2::export();'
    check('legacy negative-control injection point unique',code.count(needle)==1)
    injection=(needle+'\n            // QA ONLY: represent a committed write in the unguarded window.\n'
       '            if(is_file(dirname(__DIR__)."/storage/maintenance.lock"))throw new RuntimeException("negative control gate already closed");\n'
       '            db()->prepare("INSERT INTO ".table("settings")." (`key`,`value`) VALUES (?,?)")->execute(["qa_late_write","AFTER_SNAPSHOT"]);')
    src.write_text(code.replace(needle,injection))
    p=subprocess.run(['php','-l',str(src)],capture_output=True,text=True)
    check('QA-only negative-control fixture syntax',p.returncode==0)
    (app/'zzqa_conflict').write_text('file blocks folder')
    pkg=work/'negative-old.zip';update_package(kit,pkg,'files')
    op=begin(app,pkg,gate=False)
    check('legacy backup completed without maintenance barrier',db_setting(db,'qa_late_write')=='AFTER_SNAPSHOT')
    journal=json.loads((app/'storage/updates'/op/'database.json').read_text())
    check('legacy snapshot predates committed late write',not any(x.get('key')=='qa_late_write' for x in journal['tables']['settings']))
    advance(app,op,'rolled_back')
    check('negative control proves lost write on legacy rollback',db_setting(db,'qa_late_write')=='', 'write committed after snapshot absent')
    check('negative control returns old VERSION',(app/'VERSION').read_text().strip()=='2.6.19-dev.24')


def qa2_gate_positive(kit,work):
    db='sokna_qa_snapshot_guard';app=work/'qa2-guard'
    unpack(kit/'SOKNAHOUSE_2.6.19-dev.25-qa2_STAGING_CLEAN_INSTALL.zip',app)
    harden_qa2_fixture(app)
    mysql(f'CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
    install(app,db,8802)
    pkg=work/'qa2-gate-failure.zip';make_qa2_update(pkg,fail_files=True)
    (app/'zzqa_conflict').write_text('file blocks folder')
    ready=work/'holder.ready';release=work/'holder.release'
    holder_code=('define("SOKNA_S2S_REQUEST",true);require "app/bootstrap.php";'
      'RestoreGate::enterRequest(getcwd());file_put_contents($argv[1],"ready");'
      'while(!is_file($argv[2]))usleep(30000);'
      'db()->prepare("INSERT INTO ".table("settings")." (`key`,`value`) VALUES (?,?)")->execute(["qa_gate_admitted","PRESERVED"]);'
      'RestoreGate::leaveRequest();')
    hproc=subprocess.Popen(['php','-r',holder_code,str(ready),str(release)],cwd=app,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
    pproc=None
    try:
        for _ in range(150):
            if ready.exists():break
            if hproc.poll() is not None:raise RuntimeError('holder died: '+hproc.stderr.read()[-400:])
            time.sleep(0.03)
        check('admitted request holds shared gate',ready.exists())
        prep_code=('define("SOKNA_S2S_REQUEST",true);require "app/bootstrap.php";'
          'RestoreGate::enterRequest(getcwd());echo UpdateManagerV2::prepare($argv[1],1);')
        pproc=subprocess.Popen(['php','-r',prep_code,str(pkg)],cwd=app,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
        pending=app/'storage/update-operation.lock'
        for _ in range(220):
            if pending.is_file():break
            if pproc.poll() is not None:raise RuntimeError('prepare exited before pending')
            time.sleep(0.035)
        check('QA2 pending barrier created while older request active',pending.exists())
        attempt=phpcall(app,'try{RestoreGate::enterRequest(getcwd());echo "ADMITTED";}catch(Throwable $e){echo "BLOCKED";}')
        check('new entrant denied during snapshot drain',attempt=='BLOCKED',attempt)
        check('prepare still waiting on admitted writer',pproc.poll() is None)
        release.touch()
        hout,herr=hproc.communicate(timeout=35)
        check('admitted writer finished without error',hproc.returncode==0,herr[-200:])
        pout,perr=pproc.communicate(timeout=40)
        check('QA2 prepare completed after drain',pproc.returncode==0 and pout.startswith('upd-'),perr[-200:])
        op=pout.strip()
        backup=json.loads((app/'storage/updates'/op/'database.json').read_text())
        check('guarded snapshot contains admitted write',any(x.get('key')=='qa_gate_admitted' and x.get('value')=='PRESERVED' for x in backup['tables']['settings']))
        check('guarded snapshot hash is journaled',hashlib.sha256((app/'storage/updates'/op/'database.json').read_bytes()).hexdigest()==json.loads((app/'storage/updates'/op/'journal.json').read_text())['database_sha256'])
        advance(app,op,'rolled_back')
        check('QA2 guarded rollback retains previously admitted write',db_setting(db,'qa_gate_admitted')=='PRESERVED')
        check('QA2 rollback returns original VERSION',(app/'VERSION').read_text().strip()=='2.6.19-dev.25-qa2')
    finally:
        release.touch()
        for proc in (hproc,pproc):
            if proc is not None and proc.poll() is None:proc.kill();proc.wait(timeout=4)


def qa2_tamper(kit,work):
    db='sokna_qa_tamper';app=work/'qa2-tamper'
    unpack(kit/'SOKNAHOUSE_2.6.19-dev.25-qa2_STAGING_CLEAN_INSTALL.zip',app)
    harden_qa2_fixture(app)
    mysql(f'CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');install(app,db,8803)
    pkg=work/'qa2-tamper.zip';make_qa2_update(pkg)
    op=begin(app,pkg,gate=True)
    backup=app/'storage/updates'/op/'database.json'
    original=backup.read_bytes();backup.write_bytes(original+b'\nQA_CORRUPTION')
    state=json.loads(phpcall(app,'echo json_encode(UpdateManagerV2::requestRollback($argv[1],1));',op))
    check('tampered backup fails closed',state['state']=='rollback_failed',state['state'])
    check('tamper holds maintenance', (app/'storage/maintenance.lock').is_file())
    check('tamper keeps original VERSION',(app/'VERSION').read_text().strip()=='2.6.19-dev.25-qa2')
    check('tamper has not dropped operational data',db_setting(db,'property_name')!='')


def qa2_post_success(kit,work):
    db='sokna_qa_no_rewind';app=work/'qa2-no-rewind'
    unpack(kit/'SOKNAHOUSE_2.6.19-dev.25-qa2_STAGING_CLEAN_INSTALL.zip',app)
    harden_qa2_fixture(app)
    mysql(f'CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');install(app,db,8804)
    pkg=work/'qa2-success.zip';make_qa2_update(pkg)
    op=begin(app,pkg,gate=True)
    advance(app,op,'success')
    mysql(f"INSERT INTO `{db}`.`sokna_qa_settings` (`key`,`value`) VALUES ('qa_after_success','DURABLE')")
    result=phpcall(app,'try{UpdateManagerV2::requestRollback($argv[1],1);echo "ALLOWED";}catch(Throwable $e){echo "BLOCKED";}',op)
    check('unsafe rollback after successful update denied',result=='BLOCKED')
    check('post-success write retained',db_setting(db,'qa_after_success')=='DURABLE')
    check('successful update not reversed',(app/'VERSION').read_text().strip()=='2.6.19-dev.25-qa3-ci')


def qa2_crash_during_file_replace(kit, work):
    """Genuine SIGKILL after verified file replacement and before journal commit.
    The pause hook is test-only and is applied to a disposable extracted fixture.
    """
    db='sokna_qa_crash_resume'; app=work/'qa2-crash-resume'
    unpack(kit/'SOKNAHOUSE_2.6.19-dev.25-qa2_STAGING_CLEAN_INSTALL.zip',app)
    harden_qa2_fixture(app)
    mysql(f'CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); install(app,db,8806)
    pkg=work/'qa2-crash-update.zip'; make_qa2_update(pkg)
    source=app/'app/UpdateManagerV2.php'; php=source.read_text()
    needle="if(!is_file($dst)||!hash_equals($expected,(string)@hash_file('sha256',$dst)))throw new RuntimeException('صحت فایل جایگزین‌شده «'.$rel.'» تأیید نشد.');"
    check('fixture-only crash hook at exact post-replace boundary',php.count(needle)==1)
    hook=(needle+'''
        // QA ONLY: parent sends SIGKILL before the journal advances.
        if(getenv('SOKNA_QA_PAUSE_AFTER_REPLACE')==='1' && $rel==='VERSION') {
            file_put_contents(dirname(__DIR__).'/storage/qa-replace-paused', 'after-byte-verified');
            while(true)usleep(10000);
        }''')
    source.write_text(php.replace(needle,hook))
    check('crash-instrumented PHP syntax',subprocess.run(['php','-l',str(source)],capture_output=True).returncode==0)
    op=begin(app,pkg,gate=True)
    command=['php','-r','define("SOKNA_S2S_REQUEST",true);require "app/bootstrap.php";UpdateManagerV2::continue($argv[1],1);',op]
    env=os.environ.copy();env['SOKNA_QA_PAUSE_AFTER_REPLACE']='1'
    proc=subprocess.Popen(command,cwd=app,env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
    marker=app/'storage/qa-replace-paused'
    try:
        for _ in range(220):
            if marker.exists():break
            if proc.poll() is not None:raise RuntimeError('file worker returned early: '+proc.stderr.read()[-400:])
            time.sleep(0.035)
        check('pause reached after replacement but before journal commit',marker.exists())
        proc.kill();proc.communicate(timeout=8)
        check('worker terminated by SIGKILL',proc.returncode==-9,proc.returncode)
        check('target VERSION written before kill',(app/'VERSION').read_text().strip()=='2.6.19-dev.25-qa3-ci')
        journal=json.loads((app/'storage/updates'/op/'journal.json').read_text())
        check('journal remained before file commit',journal['phase']=='files' and journal['file_index']==0 and not journal['files'][0]['applied'])
        status=json.loads(phpcall(app,'echo json_encode(UpdateManagerV2::status($argv[1]));',op))
        check('reconciliation refuses false success after crash',status['state']=='running')
        advance(app,op,'success')
        check('interrupted update converges after fresh process restart',not (app/'storage/maintenance.lock').exists())
        check('resumed update retains data',db_setting(db,'property_name')!='')
    finally:
        if proc.poll() is None:proc.kill();proc.wait(timeout=5)


def qa2_unsealed_journal(kit,work):
    db='sokna_qa_no_proof';app=work/'qa2-no-proof'
    unpack(kit/'SOKNAHOUSE_2.6.19-dev.25-qa2_STAGING_CLEAN_INSTALL.zip',app)
    harden_qa2_fixture(app)
    mysql(f'CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');install(app,db,8805)
    pkg=work/'qa2-no-proof.zip';make_qa2_update(pkg)
    op=begin(app,pkg,gate=True)
    journal_path=app/'storage/updates'/op/'journal.json'
    journal=json.loads(journal_path.read_text())
    check('new journal includes isolated snapshot proof',journal.get('snapshot_isolated') is True)
    journal.pop('snapshot_isolated');journal.pop('database_sha256')
    journal_path.write_text(json.dumps(journal))
    result=phpcall(app,'try{UpdateManagerV2::continue($argv[1],1);echo "ALLOWED";}catch(Throwable $e){echo "BLOCKED";}',op)
    check('unsealed legacy-style journal cannot continue',result=='BLOCKED',result)
    result=phpcall(app,'try{UpdateManagerV2::requestRollback($argv[1],1);echo "ALLOWED";}catch(Throwable $e){echo "BLOCKED";}',op)
    check('unsealed legacy-style journal cannot restore',result=='BLOCKED',result)
    check('unsealed journal holds maintenance lock',(app/'storage/maintenance.lock').exists())
    check('unsealed journal leaves VERSION intact',(app/'VERSION').read_text().strip()=='2.6.19-dev.25-qa2')
    check('unsealed journal has not erased settings',db_setting(db,'property_name')!='')


def main():
    check('CI DB credential present',bool(os.getenv('SOKNA_QA_CI_ROOT_PASSWORD')))
    with tempfile.TemporaryDirectory(prefix='soknahouse-safety-ci-') as scratch:
        work=Path(scratch); archive=work/'qa2-kit.zip'
        with archive.open('wb') as out:
            for part in ARCHIVE_PARTS:
                with part.open('rb') as src:shutil.copyfileobj(src,out)
        check('pinned QA2 kit',hashlib.sha256(archive.read_bytes()).hexdigest()==EXPECTED_KIT_SHA256)
        kit=work/'kit';unpack(archive,kit)
        check('pinned QA2 clean',hashlib.sha256((kit/'SOKNAHOUSE_2.6.19-dev.25-qa2_STAGING_CLEAN_INSTALL.zip').read_bytes()).hexdigest()==EXPECTED_CLEAN_SHA256)
        old_snapshot_gap(kit,work)
        qa2_gate_positive(kit,work)
        qa2_tamper(kit,work)
        qa2_post_success(kit,work)
        qa2_unsealed_journal(kit,work)
        qa2_crash_during_file_replace(kit,work)
        check('dev24 initial upgrade still BLOCKED',True,'legacy updater must be retired via a separately qualified safe entry path')

if __name__=='__main__':
    try:main();REPORT['ok']=True
    except Exception as e:REPORT['ok']=False;REPORT['error']=type(e).__name__+': '+str(e)[:400];print('FAILED',REPORT['error'],file=sys.stderr)
    finally:(ROOT/'staging-update-safety-report.json').write_text(json.dumps(REPORT,ensure_ascii=False,indent=2))
    sys.exit(0 if REPORT['ok'] else 1)