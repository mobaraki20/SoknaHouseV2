#!/usr/bin/env python3
"""Genuine headless Chromium UI/screenshot smoke on disposable SoknaHouse QA3 clean install."""
import contextlib,json,os,shutil,subprocess,sys,tempfile,time
from pathlib import Path
from staging_runner import mysql,install,unpack
from build_qa3_candidate import create_packages,TARGET

ROOT=Path(__file__).resolve().parents[2]
REPORT={'type':'real-browser-Chromium','production_approved':False,'checks':[],'observations':[]}
def ck(name,ok,detail=''):
    REPORT['checks'].append({'name':name,'pass':bool(ok),'detail':str(detail)[:300]})
    print(('PASS' if ok else 'FAIL'),name,str(detail)[:100],flush=True)
    if not ok:raise AssertionError(name+': '+str(detail))

def do_browser(app,db,work):
    from playwright.sync_api import sync_playwright
    # Chrome is supplied by the public GitHub-hosted Ubuntu runner, not by a production machine.
    chrome=next((p for p in ['google-chrome','google-chrome-stable','chromium','chromium-browser'] if shutil.which(p)),None)
    ck('CI Chromium/Chrome executable exists',chrome is not None)
    browser_path=shutil.which(chrome)
    port=8899
    log=work/'browser-server.log'
    with log.open('wb') as out:
        server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(app)],cwd=app,stdout=out,stderr=subprocess.STDOUT)
        try:
            with sync_playwright() as p:
                browser=p.chromium.launch(executable_path=browser_path,headless=True,args=['--no-sandbox'])
                context=browser.new_context(viewport={'width':1440,'height':900},locale='fa-IR',timezone_id='Asia/Tehran')
                page=context.new_page();errors=[];page.on('pageerror',lambda exc:errors.append(str(exc)))
                url=f'http://127.0.0.1:{port}/index.php'
                for i in range(30):
                    try:
                        page.goto(url+'?page=login',wait_until='domcontentloaded',timeout=5000)
                        if page.locator('form').count()>0:break
                    except Exception:time.sleep(.2)
                ck('real browser login form visible',page.locator('form').count()>0)
                ck('real browser form has CSRF',page.locator('input[name=csrf]').count()>0)
                page.locator('input[name=user_id]').first.check(force=True)
                page.locator('input[name=password]').fill('QA-Only-Disposable-Password-2026!')
                page.locator('button[type=submit]').first.click()
                page.wait_for_url('**page=today',timeout=12000)
                ck('real browser admin authenticated',page.locator('body').inner_text().find('امروز')!=-1)
                for name in ['today','calendar','rooms','reservation_new','guests','reports','settings','updates','backup','health']:
                    response=page.goto(url+'?page='+name,wait_until='networkidle',timeout=15000)
                    ck('desktop browser '+name+' DOM',response is not None and response.status==200 and page.locator('body').count()==1, response.status if response else None)
                    if name=='calendar':page.screenshot(path=str(ROOT/'qa3-desktop-calendar.png'),full_page=True)
                    if name=='reservation_new':ck('reservation form browser mounted',page.locator('form[data-reservation-form]').count()==1)
                # Public booking remains on display and must not crash on mobile.
                mob=browser.new_context(viewport={'width':390,'height':844},device_scale_factor=1,is_mobile=True,has_touch=True,locale='fa-IR')
                mobile=mob.new_page();mobile_errors=[];mobile.on('pageerror',lambda exc:mobile_errors.append(str(exc)))
                mobile.goto(url+'?page=login',wait_until='networkidle')
                mobile.locator('input[name=user_id]').first.check(force=True);mobile.locator('input[name=password]').fill('QA-Only-Disposable-Password-2026!')
                mobile.locator('button[type=submit]').first.click();mobile.wait_for_url('**page=today',timeout=12000)
                for name in ['today','calendar','reservation_new','rooms']:
                    response=mobile.goto(url+'?page='+name,wait_until='networkidle',timeout=15000)
                    ck('mobile browser '+name+' DOM',response is not None and response.status==200, response.status if response else None)
                    if name=='calendar':mobile.screenshot(path=str(ROOT/'qa3-mobile-calendar.png'),full_page=True)
                    if name=='reservation_new':ck('mobile reservation form mounted',mobile.locator('form[data-reservation-form]').count()==1)
                # Image/stylesheet fetches must be resolvable, not just HTTP 200 for controller HTML.
                ck('browser stylesheet loaded',page.evaluate('document.styleSheets.length')>=1)
                ck('browser no uncaught JavaScript exceptions',not errors and not mobile_errors,'Desktop='+str(errors[:2])+' mobile='+str(mobile_errors[:2]))
                browser.close()
        finally:
            server.terminate()
            with contextlib.suppress(subprocess.TimeoutExpired):server.wait(timeout=8)
            if server.poll() is None:server.kill();server.wait(timeout=5)

def main():
    ck('CI isolated runtime declared',os.getenv('SOKNA_QA_DISPOSABLE')=='YES')
    with tempfile.TemporaryDirectory(prefix='soknahouse-qa3-browser-') as t:
        work=Path(t);clean,_,manifest=create_packages(work)
        db='sokna_qa3_browser';app=work/'app';unpack(clean,app)
        mysql(f'CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
        install(app,db,8898)
        ck('browser tests use exact candidate version',(app/'VERSION').read_text().strip()==TARGET)
        do_browser(app,db,work)

if __name__=='__main__':
    try:main();REPORT['ok']=True
    except Exception as ex:REPORT['ok']=False;REPORT['error']=type(ex).__name__+': '+str(ex)[:600];print('FAILED',REPORT['error'],file=sys.stderr)
    finally:(ROOT/'staging-qa3-browser-ci-report.json').write_text(json.dumps(REPORT,ensure_ascii=False,indent=2)+'\n')
    sys.exit(0 if REPORT['ok'] else 1)