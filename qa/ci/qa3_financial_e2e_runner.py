#!/usr/bin/env python3
"""SoknaHouse QA3 financial/browser-controller E2E on a disposable MariaDB only."""
import contextlib,hashlib,http.cookiejar,json,os,re,shutil,subprocess,sys,tempfile,time,urllib.parse,urllib.request,zipfile
from pathlib import Path
from staging_runner import mysql,install,unpack
from build_qa3_candidate import create_packages,TARGET,H
from update_staging_runner import php,loop
from dev24_offline_bridge_runner import derive_sources,stage_bridge,build_bridge_source
from staging_runner import ARCHIVE_PARTS, EXPECTED_KIT_SHA256

ROOT=Path(__file__).resolve().parents[2]
R={'production_approved':False,'env':'DISPOSABLE_MARIADB_ONLY','checks':[]}
def ck(name,ok,detail=''):
    R['checks'].append({'name':name,'pass':bool(ok),'detail':str(detail)[:250]})
    print(('PASS ' if ok else 'FAIL ')+name+' '+str(detail)[:100],flush=True)
    if not ok:raise AssertionError(name+': '+str(detail))
def scalar(db,query):
    return mysql(f'SELECT {query} FROM `{db}`.`sokna_qa_reservations`')
def php_eval(app,source,*args):
    cmd=['php','-r','define("SOKNA_S2S_REQUEST",true);require "app/bootstrap.php";'+source,*map(str,args)]
    r=subprocess.run(cmd,cwd=app,capture_output=True,text=True,timeout=75)
    if r.returncode:raise RuntimeError('PHP CLI error: '+r.stderr[-500:])
    return r.stdout.strip()

def financial_e2e(app,work,dbname,port):
    mysql(f"INSERT INTO `{dbname}`.`sokna_qa_rooms` (name,slug,base_rate,standard_capacity,max_capacity,active) VALUES ('QA3 Test Room','qa3-e2e-room',500000,2,2,1)")
    room_id=mysql(f"SELECT id FROM `{dbname}`.`sokna_qa_rooms` WHERE slug='qa3-e2e-room'").strip()
    ck('room fixture seeded',room_id.isdigit())
    log=work/'financial-e2e-http.log';cookies=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cookies));base=f'http://127.0.0.1:{port}/index.php'
    with log.open('wb') as out:
        server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(app)],cwd=app,stdout=out,stderr=subprocess.STDOUT)
        try:
            for i in range(40):
                try:
                    page=opener.open(base+'?page=login',timeout=4).read().decode();break
                except Exception:time.sleep(.2)
            else:raise RuntimeError('server failed to bind')
            m=re.search(r'name="csrf" value="([a-f0-9]{64})"',page)
            ck('login CSRF present',bool(m));csrf=m.group(1)
            uid=mysql(f"SELECT id FROM `{dbname}`.`sokna_qa_users` WHERE username='qa_admin' LIMIT 1").strip()
            ck('admin fixture found',uid.isdigit())
            def post(action,fields):
                values=[('csrf',csrf)]+list(fields)
                request=urllib.request.Request(base+'?page='+action,data=urllib.parse.urlencode(values).encode(),method='POST')
                with opener.open(request,timeout=20) as resp:return resp.status,resp.read().decode('utf-8','replace')
            code,html=post('login_submit',[('user_id',uid),('password','QA-Only-Disposable-Password-2026!'),('return','today')]);ck('authenticated login succeeds',code==200 and 'login-card auth-login-card' not in html)
            code,res_form=opener.open(base+'?page=reservation_new',timeout=20).status,opener.open(base+'?page=reservation_new',timeout=20).read().decode()
            ck('reservation form uses trusted CSRF',code==200 and ('name="csrf" value="'+csrf+'"') in res_form)
            res_token='b0'*16
            fields=[('client_token',res_token),('guest_name','QA Disposable Guest'),('phone',''),('room_id[]',room_id),('check_in[]','1405/10/20'),('check_out[]','1405/10/22'),('guests_count[]','2'),('pricing_mode[]','auto'),('deposit_amount','0')]
            status,html=post('reservation_create',fields)
            count=mysql(f"SELECT COUNT(*) FROM `{dbname}`.`sokna_qa_reservations` WHERE client_token='{res_token}'").strip()
            ck('real reservation POST creates one booking',status==200 and count=='1',f'HTTP={status}, booking={count}')
            id=mysql(f"SELECT id FROM `{dbname}`.`sokna_qa_reservations` WHERE client_token='{res_token}'").strip()
            reservation_status=lambda:mysql(f"SELECT status FROM `{dbname}`.`sokna_qa_reservations` WHERE id={id}").strip()
            ck('new reservation begins on hold',reservation_status()=='hold')
            ck('allocation stored',mysql(f"SELECT COUNT(*) FROM `{dbname}`.`sokna_qa_reservation_rooms` WHERE reservation_id={id}").strip()=='1')
            _,html=post('reservation_create',fields)
            ck('reservation duplicate client token rejected',mysql(f"SELECT COUNT(*) FROM `{dbname}`.`sokna_qa_reservations` WHERE client_token='{res_token}'").strip()=='1')
            pay_token='ac'*16
            payment=[('reservation_id',id),('amount','250000'),('method','card'),('reference','QA-CARD'),('note','QA only'),('client_token',pay_token)]
            status,html=post('payment_add',payment)
            paid=lambda:mysql(f"SELECT COUNT(*) FROM `{dbname}`.`sokna_qa_payments` WHERE reservation_id={id} AND kind='payment'").strip()
            ck('payment POST persists one payment',status==200 and paid()=='1')
            ck('hold auto-confirmed by payment',reservation_status()=='confirmed')
            post('payment_add',payment)
            ck('duplicate payment token does not double charge',paid()=='1')
            changed=[(k,'350000' if k=='amount' else v) for k,v in payment]
            post('payment_add',changed)
            ck('conflicting financial token rejected',paid()=='1')
            refund_token='fa'*16
            refund=[('reservation_id',id),('amount','45000'),('method','card'),('reference','QA-REF'),('note','QA test: manager approved reason'),('client_token',refund_token)]
            status,html=post('refund_add',refund)
            refunded=lambda:mysql(f"SELECT COUNT(*) FROM `{dbname}`.`sokna_qa_payments` WHERE reservation_id={id} AND kind='refund'").strip()
            ck('refund POST stores one refund',status==200 and refunded()=='1')
            post('refund_add',refund)
            ck('refund replay does not duplicate',refunded()=='1')
            key_rows=mysql(f"SELECT COUNT(*) FROM `{dbname}`.`sokna_qa_financial_commit_keys` WHERE reservation_id={id}").strip()
            ck('two durable financial idempotency keys',key_rows=='2',key_rows)
            payment_id=mysql(f"SELECT id FROM `{dbname}`.`sokna_qa_payments` WHERE reservation_id={id} AND kind='payment' LIMIT 1").strip()
            post('payment_void',[('reservation_id',id),('payment_id',payment_id)])
            ck('payment void rejected while dependent refund exists',mysql(f"SELECT COUNT(*) FROM `{dbname}`.`sokna_qa_payments` WHERE id={payment_id} AND voided_at IS NULL").strip()=='1')
            code,voucher=opener.open(base+f'?page=voucher&id={id}',timeout=20).status,opener.open(base+f'?page=voucher&id={id}',timeout=20).read().decode('utf-8','replace')
            ck('voucher route rendered with booking',code==200 and ('QA Disposable Guest' in voucher or 'QA3 Test Room' in voucher))
            post('reservation_cancel',[('reservation_id',id),('reason','guest_cancelled'),('penalty','0'),('note','QA only')])
            ck('cancellation preserves financial records',reservation_status()=='cancelled' and paid()=='1' and refunded()=='1')
        finally:
            server.terminate()
            with contextlib.suppress(subprocess.TimeoutExpired):server.wait(timeout=5)
            if server.poll() is None:server.kill();server.wait(timeout=5)
    backup=work/'qa3-backup.json'
    php_eval(app,'file_put_contents($argv[1],json_encode(BackupManagerV2::export(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));',backup)
    payload=json.loads(backup.read_text())
    ck('portable backup exports actual financial rows',len(payload['tables']['payments'])>=2 and len(payload['tables']['financial_commit_keys'])>=2)
    bad=work/'qa3-bad-foreign-key-backup.json';q=json.loads(backup.read_text());q['tables']['payments'][0]['reservation_id']='999999999';bad.write_text(json.dumps(q,ensure_ascii=False))
    original_count=mysql(f"SELECT COUNT(*) FROM `{dbname}`.`sokna_qa_payments`").strip()
    message=php_eval(app,'try{BackupManagerV2::restore(json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR));echo "RESTORED";}catch(Throwable $e){echo "REJECTED:".get_class($e);}',bad)
    ck('invalid foreign key rejected before destruction',message.startswith('REJECTED:'))
    ck('invalid backup leaves records untouched',mysql(f"SELECT COUNT(*) FROM `{dbname}`.`sokna_qa_payments`").strip()==original_count)
    mysql(f"INSERT INTO `{dbname}`.`sokna_qa_settings` (`key`,`value`) VALUES ('qa3_post_backup','MUTATION')")
    ck('post-snapshot mutation committed',mysql(f"SELECT COUNT(*) FROM `{dbname}`.`sokna_qa_settings` WHERE `key`='qa3_post_backup'").strip()=='1')
    message=php_eval(app,'BackupManagerV2::restore(json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR));echo "RESTORED";',backup)
    ck('actual native restore succeeds',message=='RESTORED')
    ck('valid restore rewinds disposable snapshot',mysql(f"SELECT COUNT(*) FROM `{dbname}`.`sokna_qa_settings` WHERE `key`='qa3_post_backup'").strip()=='0')
    ck('valid restore preserves booking and finances',mysql(f"SELECT COUNT(*) FROM `{dbname}`.`sokna_qa_reservations` WHERE id={id}").strip()=='1' and mysql(f"SELECT COUNT(*) FROM `{dbname}`.`sokna_qa_payments`").strip()==original_count)
    ck('valid restore keeps durable tokens',mysql(f"SELECT COUNT(*) FROM `{dbname}`.`sokna_qa_financial_commit_keys` WHERE reservation_id={id}").strip()=='2')

def main():
    ck('CI explicitly disposable',os.getenv('SOKNA_QA_DISPOSABLE')=='YES')
    with tempfile.TemporaryDirectory(prefix='house-qa3-e2e-') as tmp:
        work=Path(tmp);clean,update,manifest=create_packages(work)
        ck('candidate clean source exact',H(clean.read_bytes())==manifest['clean_sha256'])
        ck('candidate update exact',H(update.read_bytes())==manifest['update_sha256'])
        kit=work/'kit.zip'
        with kit.open('wb') as o:
            for part in ARCHIVE_PARTS:o.write(part.read_bytes())
        ck('source kit pinned',H(kit.read_bytes())==EXPECTED_KIT_SHA256)
        kitdir=work/'kit';unpack(kit,kitdir)
        old,new,bridge,qa3=derive_sources(kitdir,work)
        db='sokna_qa3_financial_e2e';app=work/'qa3-clean-app';unpack(clean,app)
        mysql(f'CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
        install(app,db,8891)
        ck('installed clean candidate exact',(app/'VERSION').read_text().strip()==TARGET)
        financial_e2e(app,work,db,8892)
        # Verify the ACTUAL produced update archive, not an in-memory substitute.
        db2='sokna_qa3_package_upgrade';legacy=work/'dev24-upgrade-app';unpack(kitdir/'Sokna-House-2.6.19-dev.24-clean-install.zip',legacy)
        mysql(f'CREATE DATABASE `{db2}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
        install(legacy,db2,8893)
        mysql(f"INSERT INTO `{db2}`.`sokna_qa_settings` (`key`,`value`) VALUES ('qa3_update_preserve','BEFORE')")
        mark=legacy/'storage/maintenance.lock';mark.write_text(json.dumps({'operation':'qa-simulated-offline-bridge','state':'offline_bridge'}))
        stage_bridge(legacy,old,bridge);mark.unlink()
        op=php(legacy,'RestoreGate::enterRequest(getcwd());echo UpdateManagerV2::prepare($argv[1],1);',update)
        ck('exact update seals snapshot',json.loads((legacy/'storage/updates'/op/'journal.json').read_text()).get('snapshot_isolated') is True)
        loop(legacy,op,'success')
        ck('exact update reaches clean version',(legacy/'VERSION').read_text().strip()==TARGET)
        with zipfile.ZipFile(clean) as z:
            for name,digest in json.loads(zipfile.ZipFile(update).read('update.json'))['files'].items():
                ck('updated source byte match '+name,H((legacy/name).read_bytes())==digest and (legacy/name).read_bytes()==z.read(name))
        ck('exact update preserves prior data',mysql(f"SELECT value FROM `{db2}`.`sokna_qa_settings` WHERE `key`='qa3_update_preserve'").strip()=='BEFORE')
        ck('exact update creates finance token table',mysql(f"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='{db2}' AND TABLE_NAME='sokna_qa_financial_commit_keys'").strip()=='1')

if __name__=='__main__':
    try:main();R['ok']=True
    except Exception as e:R['ok']=False;R['error']=type(e).__name__+': '+str(e)[:500];print('FAILED',R['error'],file=sys.stderr)
    finally:(ROOT/'staging-qa3-business-ci-report.json').write_text(json.dumps(R,ensure_ascii=False,indent=2)+'\n')
    sys.exit(0 if R['ok'] else 1)