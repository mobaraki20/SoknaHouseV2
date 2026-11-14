#!/usr/bin/env python3
"""SoknaHouse *QA-only* offline-first bridge rehearsal with disposable MariaDB.

The first transition from dev24 CANNOT start with the legacy updater. This
runner simulates a separately enforced offline cutover and installs a three-
file guard first, keeping VERSION and schema unchanged. Real production needs
independent verified ingress isolation + in-flight request drain: the marker
used here is NOT a production proof of quiescence.
"""
import contextlib, hashlib, http.cookiejar, json, os, re, shutil, socket, subprocess, sys, tempfile, time, urllib.error, urllib.request, zipfile
from pathlib import Path
from staging_runner import ARCHIVE_PARTS, EXPECTED_KIT_SHA256, EXPECTED_CLEAN_SHA256, mysql, install, unpack
from update_staging_runner import php, loop

ROOT = Path(os.environ.get('SOKNA_QA_REPO_ROOT', str(Path(__file__).resolve().parents[2])))
REPORT = {'mode':'DISPOSABLE_CI_ONLY','release_approved':False,'independent_ingress_drain_proven':False,'checks':[]}
H=lambda b:hashlib.sha256(b).hexdigest()
PINNED_DEV24_BOOTSTRAP='c438a7d2237786fd'  # pinned prefix only; full ZIP verified externally
PINNED_DEV24_UPDATER='16a192c32ee6ae80'
TARGET_VERSION='2.6.19-dev.25-qa3-bridge'


def ck(label, okay, detail=''):
    REPORT['checks'].append({'name':label,'pass':bool(okay),'detail':str(detail)[:260]})
    print(('PASS' if okay else 'FAIL'),label,str(detail)[:100],flush=True)
    if not okay: raise AssertionError(label+': '+str(detail))


def build_bridge_source(old_bootstrap,qa2_bootstrap,guard_source):
    old=old_bootstrap.decode('utf-8');qa=qa2_bootstrap.decode('utf-8')
    require="require_once $root.'/app/ReportManager.php';\n"
    ck('bridge bootstrap unique insertion point',old.count(require)==1)
    old=old.replace(require,require+"require_once $root.'/app/RestoreGate.php';\n")
    start='// Restore gate is acquired BEFORE session/DB connection.'
    end='\nif(!$s2s){'
    ck('QA2 early gate uniquely located',qa.count(start)==1)
    guard=start+qa.split(start,1)[1].split(end,1)[0]
    # dev24 deliberately bypasses maintenance for login/update UI routes. During
    # the isolated bridge stage those routes must also return 503; this is an
    # application-level second line of defense, NOT the ingress drain proof.
    bridge_offline = """
if(PHP_SAPI!=='cli' && is_file($root.'/storage/maintenance.lock')){
    $offlineJson=json_decode((string)@file_get_contents($root.'/storage/maintenance.lock'),true);
    if(is_array($offlineJson) && ($offlineJson['state']??'')==='offline_bridge'){
        http_response_code(503);
        header('Retry-After: 60');
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');
        exit('سامانه برای ارتقای ایمن موقتاً از دسترس خارج است.');
    }
}
"""
    guard=guard.replace("if(PHP_SAPI!=='cli'){",bridge_offline+"\nif(PHP_SAPI!=='cli'){",1)
    ck('offline stage intercepts maintenance bypass before sessions',
       guard.index("$offlineJson=json_decode")<guard.index('RestoreGate::enterRequest($root)'))
    anchor="$s2s=defined('SOKNA_S2S_REQUEST')&&SOKNA_S2S_REQUEST===true;\n"
    ck('bridge pre-session gate location verified',old.count(anchor)==1)
    old=old.replace(anchor,anchor+'\n'+guard+'\n')
    ck('bridge does not require future AuthEpoch class','AuthEpochGuard' not in old)
    ck('bridge only adds early gate','RestoreGate::enterRequest($root)' in old)
    return old.encode('utf-8')


def derive_sources(kit,work):
    base=kit/'Sokna-House-2.6.19-dev.24-clean-install.zip';target=kit/'SOKNAHOUSE_2.6.19-dev.25-qa2_STAGING_CLEAN_INSTALL.zip'
    with zipfile.ZipFile(base) as a,zipfile.ZipFile(target) as b:
        old={p:a.read(p) for p in a.namelist() if not p.endswith('/')}
        new={p:b.read(p) for p in b.namelist() if not p.endswith('/')}
    ck('dev24 baseline VERSION exact',old['VERSION'].strip()==b'2.6.19-dev.24')
    ck('base bootstrap fingerprint',H(old['app/bootstrap.php']).startswith(PINNED_DEV24_BOOTSTRAP))
    ck('base updater fingerprint',H(old['app/UpdateManagerV2.php']).startswith(PINNED_DEV24_UPDATER))
    ck('new RestoreGate does not exist in dev24','app/RestoreGate.php' not in old)
    qa3=work/'qa3';unpack(target,qa3)
    patch=ROOT/'qa/patches/QA3_SNAPSHOT_FAIL_CLOSED_REVIEW_ONLY.patch'
    run=subprocess.run(['patch','-p1','--batch','--forward','-i',str(patch)],cwd=qa3,capture_output=True,text=True)
    ck('QA3 exact-source patch applied',run.returncode==0,run.stderr[-220:])
    guard= (qa3/'app/UpdateManagerV2.php').read_bytes()
    ck('QA3 source contains fail-closed proof',b'snapshot_isolated' in guard)
    bridge={'app/RestoreGate.php':new['app/RestoreGate.php'],
            'app/UpdateManagerV2.php':guard,
            'app/bootstrap.php':build_bridge_source(old['app/bootstrap.php'],new['app/bootstrap.php'],guard)}
    ck('bridge has exactly 3 source files',len(bridge)==3)
    for n,data in bridge.items():
        file=work/('syntax-'+n.replace('/','_'));file.write_bytes(data)
        r=subprocess.run(['php','-l',str(file)],capture_output=True,text=True)
        ck('bridge PHP lint '+n,r.returncode==0,r.stderr[-100:])
    ck('bridge bootstrap preserves dev24 VERSION', 'VERSION' not in bridge)
    return old,new,bridge,guard


def stage_bridge(app,old,bridge,inject_failure=False):
    if os.getenv('SOKNA_QA_DISPOSABLE')!='YES': raise RuntimeError('STAGING ONLY: SOKNA_QA_DISPOSABLE is required')
    marker=app/'storage/maintenance.lock'
    data=json.loads(marker.read_text()) if marker.exists() else {}
    if data.get('operation')!='qa-simulated-offline-bridge':
        raise RuntimeError('no matching offline staging marker: fail closed')
    ck('offline staging marker exists with exact owner',True)
    ck('refuse bridge on non-dev24 site',(app/'VERSION').read_text().strip()=='2.6.19-dev.24')
    state=app/'storage/qa-offline-bridge';state.mkdir(exist_ok=True)
    backup=state/'original';backup.mkdir(exist_ok=True)
    manifest={'mode':'qa-simulated-offline','production_approved':False,'files':{},'state':'preparing'}
    staged=[]
    for name in ['app/RestoreGate.php','app/UpdateManagerV2.php','app/bootstrap.php']:
        dest=app/name
        if dest.is_symlink():raise RuntimeError('SYMLINK target blocked')
        was=dest.exists()
        if was and dest.read_bytes()!=old[name]: raise RuntimeError('Original changed from pinned dev24: '+name)
        if was:
            save=backup/name;save.parent.mkdir(parents=True,exist_ok=True)
            save.write_bytes(dest.read_bytes());staged.append((dest,save,True))
        else:staged.append((dest,None,False))
        manifest['files'][name]={'before_sha256':H(old[name]) if was else None,'after_sha256':H(bridge[name]),'existed':was}
    (state/'journal.json').write_text(json.dumps(manifest,indent=2))
    try:
        for i,(name,data) in enumerate(bridge.items()):
            dest=app/name
            temporary=dest.with_name(dest.name+'.qa-temp')
            temporary.write_bytes(data)
            os.replace(temporary,dest)
            if H(dest.read_bytes())!=H(data):raise AssertionError('staged byte mismatch')
            if inject_failure and i==1:raise RuntimeError('QA_SIMULATED_BRIDGE_STAGE_FAILURE')
        for n,d in bridge.items():ck('deployed bridge hash '+n,H((app/n).read_bytes())==H(d))
        ck('bridge did not alter app VERSION',(app/'VERSION').read_bytes()==old['VERSION'])
        manifest['state']='bridge_ready'
        (state/'journal.json').write_text(json.dumps(manifest,indent=2))
        return state
    except Exception:
        for dest,save,existed in reversed(staged):
            if existed:os.replace(save,dest)
            else:dest.unlink(missing_ok=True)
        manifest['state']='rolled_back_bridge';(state/'journal.json').write_text(json.dumps(manifest,indent=2))
        ck('failure retains offline maintenance',marker.exists())
        for n in bridge:ck('bridge failure restores original '+n,(app/n).read_bytes()==old[n] if n in old else not (app/n).exists())
        raise


def candidate_update_zip(kit,old,new,qa3_updater,work,fail_files=False):
    new=dict(new);new['app/UpdateManagerV2.php']=qa3_updater
    new['VERSION']=(TARGET_VERSION+'\n').encode()
    delta={n:d for n,d in sorted(new.items()) if old.get(n)!=d}
    ck('full QA3 target retains QA2 feature payload',len(delta)==35,len(delta))
    ck('payload contains QA3 fail-closed updater',delta['app/UpdateManagerV2.php']==qa3_updater)
    schema_h=H(new['database/schema.sql'])
    ck('target schema exactly QA2',schema_h=='150064edb884ccea26792e1301e8c555811d5aa6c6eab736a469000b9d51d7ea')
    migration=(kit/'migration_create_only.sql').read_text().strip()+"\n"
    migration+=("INSERT INTO `{{prefix}}schema_migrations` (`version`,`checksum`,`operation_id`,`status`) "
        f"VALUES ('2.6.0-rc7-qa1','{schema_h}','qa-bridge-stage','success') "
        "ON DUPLICATE KEY UPDATE `checksum`=VALUES(`checksum`), `status`=VALUES(`status`);\n")
    if fail_files:delta['zzqa_conflict/test.txt']=b'forced-qa-conflict'
    dest=work/('staging-cumulative-fail.zip' if fail_files else 'staging-cumulative.zip')
    manifest={'format':'sokna-v2-update','from_version':'2.6.19-dev.24','to_version':TARGET_VERSION,
              'files':{n:H(data) for n,data in delta.items()},'delete':[]}
    with zipfile.ZipFile(dest,'w',zipfile.ZIP_DEFLATED) as z:
        z.writestr('update.json',json.dumps(manifest,sort_keys=True))
        z.writestr('migration.sql',migration)
        z.writestr('rollback.sql','-- QA only: do not DROP idempotency evidence\nSELECT 1;\n')
        for n,data in delta.items():z.writestr('payload/'+n,data)
    with zipfile.ZipFile(dest) as z:
        ck('all staged update payload hashes match',z.testzip() is None and all(H(z.read('payload/'+n))==v for n,v in manifest['files'].items()))
    return dest,manifest


def http_locked(app,work,port,bridge_installed=False):
    log=work/f'http-{port}.log'
    with log.open('wb') as output:
        server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(app)],cwd=app,stdout=output,stderr=subprocess.STDOUT)
        try:
            def get_status(route):
                status=None
                for _ in range(25):
                    try:
                        with urllib.request.urlopen(f'http://127.0.0.1:{port}/{route}',timeout=2) as resp:
                            return resp.status
                    except urllib.error.HTTPError as err:return err.code
                    except Exception:time.sleep(0.2)
                return status
            ck('HTTP normal route blocked during offline bridge',get_status('index.php')==503)
            login=get_status('index.php?page=login')
            if bridge_installed:
                ck('bridge intercepts legacy login bypass',login==503,login)
                updates=get_status('index.php?page=updates')
                ck('bridge intercepts updater bypass',updates==503,updates)
            else:
                ck('legacy login bypass reproduces unsafe exposed route',login!=503 and login is not None,login)
        finally:
            server.terminate()
            with contextlib.suppress(subprocess.TimeoutExpired):server.wait(timeout=5)
            if server.poll() is None:server.kill();server.wait(timeout=5)


def scenario(kit,work,old,new,bridge,qa3_updater,name,dbname,port,fail_update=False):
    app=work/name
    unpack(kit/'Sokna-House-2.6.19-dev.24-clean-install.zip',app)
    mysql(f'CREATE DATABASE `{dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
    install(app,dbname,port)
    mysql(f"INSERT INTO `{dbname}`.`sokna_qa_settings` (`key`,`value`) VALUES ('qa_bridge_preserve','PREEXISTING')")
    marker=app/'storage/maintenance.lock'
    # CI simulates ingress isolation. No production host is touched.
    marker.write_text(json.dumps({'operation':'qa-simulated-offline-bridge','state':'offline_bridge'}))
    http_locked(app,work,port+101,bridge_installed=False)
    stage_bridge(app,old,bridge)
    http_locked(app,work,port+101,bridge_installed=True)
    ck('bridge after installing still dev24',(app/'VERSION').read_text().strip()=='2.6.19-dev.24')
    ck('bridge schema unchanged before update',mysql(f"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='{dbname}' AND TABLE_NAME='sokna_qa_financial_commit_keys'").strip()=='0')
    # The upstream ingress is already stopped by the test controller. Remove its
    # simulated marker only after guard source is installed. This is NOT a live
    # drain proof. The new updater then owns maintenance from prepare onward.
    marker.unlink()
    pkg,manifest=candidate_update_zip(kit,old,new,qa3_updater,work,fail_files=fail_update)
    if fail_update:(app/'zzqa_conflict').write_text('directory collision injected')
    prep=('RestoreGate::enterRequest(getcwd());echo UpdateManagerV2::prepare($argv[1],1);')
    op=php(app,prep,pkg)
    ck('bridge-target prepare uses sealed snapshot',json.loads((app/'storage/updates'/op/'journal.json').read_text()).get('snapshot_isolated') is True)
    ck('bridge-target prepare owns maintenance',json.loads(marker.read_text()).get('operation')==op)
    hist=loop(app,op,'rolled_back' if fail_update else 'success')
    result_version='2.6.19-dev.24' if fail_update else TARGET_VERSION
    ck('bridge-target final VERSION',(app/'VERSION').read_text().strip()==result_version)
    ck('bridge-target preserves seeded data',mysql(f"SELECT `value` FROM `{dbname}`.`sokna_qa_settings` WHERE `key`='qa_bridge_preserve'").strip()=='PREEXISTING')
    ck('bridge-target finished maintenance',not marker.exists())
    ck('bridge-target source safety persists',b'snapshot_isolated' in (app/'app/UpdateManagerV2.php').read_bytes())
    ck('bridge-target financial schema expected',mysql(f"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='{dbname}' AND TABLE_NAME='sokna_qa_financial_commit_keys'").strip()==('0' if fail_update else '1'))
    ck('bridge-target journal records safe admission',json.loads((app/'storage/updates'/op/'journal.json').read_text()).get('snapshot_isolated') is True)
    return {'name':name,'history':hist,'version':result_version}


def fail_injection_scenario(kit,work,old,bridge):
    app=work/'bridge-partial-failure';unpack(kit/'Sokna-House-2.6.19-dev.24-clean-install.zip',app)
    app.joinpath('storage').mkdir(exist_ok=True)
    (app/'storage/maintenance.lock').write_text(json.dumps({'operation':'qa-simulated-offline-bridge'}))
    caught=False
    try:stage_bridge(app,old,bridge,inject_failure=True)
    except RuntimeError as ex:caught='QA_SIMULATED_BRIDGE_STAGE_FAILURE' in str(ex)
    ck('bridge partial failure injected and caught',caught)
    ck('offline lock remains on partial failure',(app/'storage/maintenance.lock').exists())


def main():
    ck('CI disposable flag set',os.getenv('SOKNA_QA_DISPOSABLE')=='YES')
    ck('MariaDB disposable credential set',bool(os.getenv('SOKNA_QA_CI_ROOT_PASSWORD')))
    with tempfile.TemporaryDirectory(prefix='soknahouse-offline-bridge-qa-') as tmp:
        work=Path(tmp);kit_zip=work/'kit.zip'
        with kit_zip.open('wb') as out:
            for part in ARCHIVE_PARTS:
                with part.open('rb') as src:shutil.copyfileobj(src,out)
        ck('source kit SHA pinned',H(kit_zip.read_bytes())==EXPECTED_KIT_SHA256)
        kit=work/'kit';unpack(kit_zip,kit)
        ck('target archive SHA pinned',H((kit/'SOKNAHOUSE_2.6.19-dev.25-qa2_STAGING_CLEAN_INSTALL.zip').read_bytes())==EXPECTED_CLEAN_SHA256)
        old,new,bridge,qa3=derive_sources(kit,work)
        # Never use an unguarded old update path: the only permitted approach
        # starts with a QA-simulated isolated code bridge.
        offline=work/'no-offline';unpack(kit/'Sokna-House-2.6.19-dev.24-clean-install.zip',offline)
        refused=False
        try:stage_bridge(offline,old,bridge)
        except (RuntimeError,AssertionError):refused=True
        ck('bridge refuses absent offline QA marker',refused)
        fail_injection_scenario(kit,work,old,bridge)
        REPORT['scenarios']=[scenario(kit,work,old,new,bridge,qa3,'qa-bridge-success','sokna_qa_bridge_success',8861),
                             scenario(kit,work,old,new,bridge,qa3,'qa-bridge-rollback','sokna_qa_bridge_rollback',8862,True)]
        ck('no production ingress isolation proof supplied', True, 'CI-only offline simulation does not qualify a real host')

if __name__=='__main__':
    try:main();REPORT['ok']=True
    except Exception as ex:
        REPORT['ok']=False;REPORT['error']=type(ex).__name__+': '+str(ex)[:550];print('FAILED',REPORT['error'],file=sys.stderr)
    finally:(ROOT/'staging-offline-bridge-ci-report.json').write_text(json.dumps(REPORT,ensure_ascii=False,indent=2))
    sys.exit(0 if REPORT['ok'] else 1)