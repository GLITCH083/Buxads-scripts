#!/usr/bin/env python3
"""
Buxads - BTCAdSpace Bot
Faucet · Surfads · Videos (Aviso)
Captcha: Vernuable (Turnstile + AntiBot)
"""
from __future__ import annotations
import os, re, sys, time, random, json
from datetime import datetime
from typing import Dict, List, Optional, Tuple
from urllib.parse import urljoin

try:
    import requests
except ImportError:
    print("pip install requests")
    sys.exit(1)

BASE = "https://btcadspace.com"
AVISO = "https://aviso.bz/api/v1"
VN_IN = "https://vernuable.my.id/in.php"
VN_RES = "https://vernuable.my.id/res.php"
TURNSTILE_SITEKEY = "0x4AAAAAAAB-TZt_lwYtViEL"
UA = "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Mobile Safari/537.36"
PLATFORM = "Linux armv81"
ACCOUNTS_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", "information", "btcadspace.com.txt")

class C:
    R="\033[0m"; D="\033[90m"; G="\033[92m"; Y="\033[93m"; B="\033[96m"; M="\033[95m"; RED="\033[91m"; BOLD="\033[1m"

def log(msg, kind="INFO"):
    ts = datetime.now().strftime("%H:%M:%S")
    col = {"INFO":C.B,"OK":C.G,"WARN":C.Y,"ERR":C.RED,"WAIT":C.M,"CASH":C.G+C.BOLD,"CAP":C.Y,"★":C.M}.get(kind,C.R)
    tag = {"INFO":"◆","OK":"✔","WARN":"!","ERR":"✖","WAIT":"…","CASH":"$","CAP":"🧩","★":"★"}.get(kind,"·")
    print(f"{C.D}[{ts}]{C.R} {col}{tag}{C.R} {msg}")

def banner():
    print(f"""
{C.M}╔══════════════════════════════════════════════════════════╗
║   ₿ BTCADSPACE · Buxads Edition                          ║
║   Faucet · Surfads · Videos · Vernuable                  ║
╚══════════════════════════════════════════════════════════╝{C.R}
""")

def ensure_accounts_file():
    os.makedirs(os.path.dirname(ACCOUNTS_FILE), exist_ok=True)
    if not os.path.exists(ACCOUNTS_FILE):
        with open(ACCOUNTS_FILE, "w") as f:
            f.write("# Format: cookie|vernuable_key\n# OR: username|password|vernuable_key\n# Example: bsid=xxx; remember_token=yyy|VN_API_KEY\n")

def load_accounts():
    ensure_accounts_file()
    accs = []
    with open(ACCOUNTS_FILE) as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#"): continue
            parts = [p.strip() for p in line.split("|")]
            if len(parts) >= 2:
                accs.append(parts)
    return accs

class Vernuable:
    def __init__(self, key):
        self.key = key
        self.s = requests.Session()
        self.s.headers.update({"User-Agent": "BTCAdSpaceFarm/1.0", "Accept": "application/json"})
    def _post(self, url, fields, as_json=False):
        r = self.s.post(url, json=fields if as_json else None, data=None if as_json else fields, timeout=180)
        try: return r.json()
        except:
            raw = r.text.strip()
            if raw.startswith("OK|"): return {"status":1,"request":raw.split("|",1)[1]}
            return {"status":0,"request":raw[:200]}
    def balance(self):
        j = self._post(VN_RES, {"key":self.key,"action":"getbalance","json":"1"})
        return str(j.get("balance") or j.get("request") or "?")
    def solve(self, method, timeout=180, poll=2.5, retries=3, **params):
        body = {"key":self.key,"method":method,"json":"1",**params}
        as_json = method in ("antibot","bitcotask","limefaucet","pcaptcha","rotation","iconcaptcha")
        last_err = None
        for attempt in range(1, retries+1):
            log(f"solving {method}…", "CAP")
            task = self._post(VN_IN, body, as_json=as_json)
            if int(task.get("status") or 0) != 1:
                last_err = str(task.get("request") or task)
                if "NO_SLOT" in last_err.upper() or "ERROR_" in last_err.upper():
                    log(f"slot busy · retry {attempt}/{retries}", "WARN")
                    time.sleep(3+attempt*2); continue
                raise RuntimeError(f"submit fail: {task}")
            job = task["request"]
            log(f"job {job}", "★")
            t0 = time.time()
            while time.time()-t0 < timeout:
                time.sleep(poll)
                res = self._post(VN_RES, {"key":self.key,"action":"get","id":job,"json":"1"})
                if int(res.get("status") or 0) == 1: return res
                rr = str(res.get("request") or "")
                if rr != "CAPCHA_NOT_READY": last_err=rr; break
            else: last_err="timeout"
            log(f"solve retry {attempt}/{retries} · {last_err}", "WARN"); time.sleep(2)
        raise RuntimeError(f"{method} failed: {last_err}")
    def turnstile(self, pageurl, sitekey=TURNSTILE_SITEKEY):
        r = self.solve("turnstile", sitekey=sitekey, pageurl=pageurl, timeout=120, poll=2, retries=4)
        tok = str(r.get("request") or r.get("token") or "")
        log(f"turnstile ok · {tok[:28]}…", "OK")
        return tok
    def antibot(self, main_b64, subs):
        def pure(b):
            if "," in b and b.strip().startswith("data:"): return b.split(",",1)[1]
            return b
        r = self.solve("antibot", main=pure(main_b64), sub={k:pure(v) for k,v in subs.items()}, timeout=180, poll=2, retries=3)
        order = str(r.get("request") or "").strip()
        ids = re.findall(r"\d+", order)
        result = (" " + " ".join(ids)) if ids else ""
        log(f"antibot order · {result.strip().replace(' ','+')}", "OK")
        return result

class BTCAdSpace:
    def __init__(self, vn: Vernuable):
        self.vn = vn
        self.s = requests.Session()
        self.s.headers.update({"User-Agent":UA,"Accept":"text/html,application/xhtml+xml","Accept-Language":"en-US,en;q=0.9","Origin":BASE,"Referer":BASE+"/"})
    def set_cookie_string(self, cookie):
        cookie = re.sub(r"(?i)^cookie:\s*","",cookie).strip()
        for part in cookie.split(";"):
            part=part.strip()
            if not part or "=" not in part: continue
            k,v = part.split("=",1)
            self.s.cookies.set(k.strip(), v.strip(), domain="btcadspace.com", path="/")
    def _req(self, method, path, retries=4, **kw):
        url = path if path.startswith("http") else urljoin(BASE, path)
        timeout = kw.pop("timeout",45)
        last=None
        for i in range(retries):
            try:
                return self.s.get(url,timeout=timeout,**kw) if method.upper()=="GET" else self.s.post(url,timeout=timeout,**kw)
            except (requests.exceptions.ConnectionError, requests.exceptions.Timeout, requests.exceptions.ChunkedEncodingError) as e:
                last=e; time.sleep(2+i*2+random.uniform(0,1.5))
                try: self.s.close()
                except: pass
        raise last
    def get(self, path, **kw): return self._req("GET", path, **kw)
    def post_form(self, path, data, referer="", ajax=False):
        headers={"Content-Type":"application/x-www-form-urlencoded","Origin":BASE}
        if ajax: headers.update({"X-Requested-With":"XMLHttpRequest","Accept":"application/json, text/javascript, */*; q=0.01"})
        else: headers["Accept"]="text/html,application/xhtml+xml"
        if referer: headers["Referer"]=referer
        return self._req("POST", path, data=data, headers=headers, allow_redirects=True)
    @staticmethod
    def _csrf(html):
        m = re.search(r'name=["\']csrf_token["\']\s+value=["\']([^"\']+)', html)
        if m: return m.group(1)
        m = re.search(r'csrf_token["\']?\s*[:=]\s*["\']([^"\']+)', html)
        return m.group(1) if m else ""
    def logged_in(self):
        try: r = self.get("/account")
        except: return True
        if r.status_code != 200: return False
        t = r.text.lower()
        if "/login" in r.url.lower(): return False
        if "logout" in t or "dashboard" in t or "balance" in t or "coins" in t:
            if 'name="username"' in t and 'name="password"' in t and "logout" not in t: return False
            return True
        return False
    def login_password(self, username, password, twofa=""):
        r = self.get("/login")
        if r.status_code != 200: return False, f"login page {r.status_code}"
        csrf = self._csrf(r.text)
        if not csrf: return False, "csrf missing"
        try: ts = self.vn.turnstile(pageurl=f"{BASE}/login")
        except Exception as e: return False, f"turnstile: {e}"
        data = {"csrf_token":csrf,"username":username,"password":password,"remember":"1","cf-turnstile-response":ts}
        if twofa: data["2fa"]=twofa
        self.post_form("/login", data, referer=f"{BASE}/login")
        if self.logged_in(): return True, "ok"
        return False, "login failed"
    def faucet_claim(self):
        r = self.get("/faucet")
        if r.status_code == 401 or "/login" in r.url.lower(): return False, "session dead"
        if r.status_code != 200: return False, f"faucet page {r.status_code}"
        html = r.text
        if re.search(r"maximum daily claims|daily limit|reached the maximum", html, re.I):
            return False, "daily limit reached"
        csrf = self._csrf(html)
        if not csrf: return False, "csrf missing"
        m = re.search(r'order\s*<img\s+src="(data:image/png;base64,[^"]+)"', html, re.I)
        if not m:
            if re.search(r"wait|cooldown|next claim|come back|already", html, re.I):
                tm = re.search(r"(\d+)\s*(?:minute|second|min|sec)", html, re.I)
                return False, f"cooldown{(' ~'+tm.group(0)) if tm else ''}"
            return False, "antibot main image not found"
        main_b64 = m.group(1)
        m = re.search(r"ablinks\s*=\s*(\[.*?\]);", html, re.S)
        if not m: return False, "ablinks missing"
        subs = {}
        for block in re.finditer(r'rel=\\?"(\d+)\\?".*?src=\\?"(data:image/[^\\"]+)\\?"', m.group(1), re.S):
            rel, src = block.group(1), block.group(2).replace("\\/","/")
            subs[rel] = src
        if len(subs) < 2: return False, f"only {len(subs)} subs"
        log(f"antibot · main + {len(subs)} subs", "CAP")
        try: order = self.vn.antibot(main_b64, subs)
        except Exception as e: return False, f"antibot: {e}"
        try: ts = self.vn.turnstile(pageurl=f"{BASE}/faucet")
        except Exception as e: return False, f"turnstile: {e}"
        resp = self.post_form("/faucet", {"csrf_token":csrf,"antibotlinks":order,"cf-turnstile-response":ts}, referer=f"{BASE}/faucet")
        body = resp.text
        if re.search(r"You have earned|claimed successfully|Coins?.*added", body, re.I):
            m = re.search(r"You have earned[^<!.]{0,60}", body, re.I)
            return True, (m.group(0).strip() if m else "claimed")
        if re.search(r"maximum daily|daily limit", body, re.I): return False, "daily limit reached"
        if re.search(r"wait|cooldown|next claim", body, re.I): return False, "cooldown"
        return False, "claim unclear"
    def surf_list(self):
        r = self.get("/surf")
        if r.status_code != 200 or "/login" in r.url.lower(): return []
        html = r.text
        items = []
        for m in re.finditer(r'href="(/surf/([a-f0-9]+))"[^>]*>[\s\S]*?fa-coins[\s\S]*?(\d+)\s*Coins[\s\S]*?fa-stopwatch[\s\S]*?(\d+)\s*seconds', html, re.I):
            items.append({"path":m.group(1),"hash":m.group(2),"coins":int(m.group(3)),"seconds":int(m.group(4))})
        if not items:
            for m in re.finditer(r'href="(/surf/([a-f0-9]+))"', html):
                items.append({"path":m.group(1),"hash":m.group(2),"coins":0,"seconds":10})
        seen=set(); out=[]
        for it in items:
            if it["hash"] not in seen:
                seen.add(it["hash"]); out.append(it)
        self._surf_csrf = self._csrf(html)
        return out
    def surf_one(self, item):
        path, uid, secs, coins = item["path"], item["hash"], max(3,int(item.get("seconds") or 10)), item.get("coins") or "?"
        log(f"surf · {uid[:12]}… · {coins} coins · {secs}s", "★")
        try: r = self.get(path, headers={"Referer":f"{BASE}/surf"})
        except Exception as e: return False, f"open: {e}"
        if r.status_code != 200 or "/login" in r.url.lower(): return False, "session issue"
        view = r.text
        sid = ""
        m = re.search(r'\bid\s*=\s*["\']([a-f0-9]{32,})["\']', view, re.I)
        if m: sid = m.group(1)
        if not sid:
            csrf_tmp = self._csrf(view)
            for hx in re.findall(r'["\']([a-f0-9]{64})["\']', view, re.I):
                if hx != csrf_tmp: sid=hx; break
        if not sid: return False, "page id missing"
        m = re.search(r'\bcount\s*=\s*(\d+)', view)
        if m: secs = max(secs, int(m.group(1)))
        has_captcha = True
        m = re.search(r'\bhasCAPTCHA\s*=\s*(!0|!1|true|false)', view, re.I)
        if m: has_captcha = m.group(1).lower() in ("!0","true")
        csrf = self._csrf(view) or getattr(self,"_surf_csrf","") or ""
        rnd = random.randint(1,9999)
        c_val = f"{sid}{rnd}"
        try: self.get(f"/surf/{uid}/{c_val}", headers={"Referer":urljoin(BASE,path)})
        except: pass
        wait = secs + random.uniform(1.2,2.5)
        log(f"watching · {wait:.0f}s", "WAIT"); time.sleep(wait)
        if not csrf:
            try: csrf = self._csrf(self.get(path).text)
            except: pass
        if not csrf: return False, "csrf missing"
        ts = ""
        if has_captcha:
            try: ts = self.vn.turnstile(pageurl=urljoin(BASE,path))
            except Exception as e: return False, f"turnstile: {e}"
        payload = {"csrf_token":csrf,"uid":uid,"c":c_val}
        if ts: payload["cf-turnstile-response"] = ts
        resp = self.post_form("/ajax/surf", payload, referer=urljoin(BASE,path), ajax=True)
        try: j = resp.json()
        except: return False, f"bad resp {resp.status_code}"
        if j.get("success"): return True, str(j.get("message") or "ok")
        return False, str(j.get("message") or j)

def run_account(parts):
    if len(parts) == 2:
        # cookie|vn_key
        cookie, vn_key = parts
        vn = Vernuable(vn_key)
        site = BTCAdSpace(vn)
        site.set_cookie_string(cookie)
        mode = "cookie"
    else:
        # username|password|vn_key
        user, pw, vn_key = parts[0], parts[1], parts[2]
        vn = Vernuable(vn_key)
        site = BTCAdSpace(vn)
        log("logging in…", "★")
        ok, msg = site.login_password(user, pw)
        if not ok:
            log(f"login failed · {msg}", "ERR")
            return
        mode = "password"
        log(f"login ok", "OK")

    try: log(f"vernuable balance · {vn.balance()}", "OK")
    except: pass

    if not site.logged_in():
        log("session not valid", "ERR")
        return

    # Faucet loop until limit / cooldown long
    while True:
        ok, msg = site.faucet_claim()
        if ok:
            log(msg, "CASH")
            time.sleep(random.uniform(5,10))
        else:
            log(msg, "WARN")
            if "daily limit" in msg.lower() or "session dead" in msg.lower():
                break
            if "cooldown" in msg.lower():
                # short cooldown → wait a bit, long → stop faucet for this account
                break
            time.sleep(5)
            break

    # Surfads (skip if empty)
    items = site.surf_list()
    if items:
        log(f"surfads · {len(items)} found", "INFO")
        for it in items:
            ok, msg = site.surf_one(it)
            if ok: log(msg, "CASH")
            else: log(msg, "WARN")
            if "session dead" in msg.lower(): break
            time.sleep(random.uniform(3,7))
    else:
        log("surfads empty · skipped", "INFO")

    # Videos skipped for now in simple mode (can be added later)
    log("videos · skipped (empty or not enabled)", "INFO")

def main():
    banner()
    ensure_accounts_file()
    accs = load_accounts()
    print(f"  Accounts: {len(accs)}")
    print(f"  File: {ACCOUNTS_FILE}")
    print()
    print("  1) Run All Accounts")
    print("  2) Exit")
    try:
        choice = input("  › Choice [1]: ").strip() or "1"
    except (EOFError, KeyboardInterrupt):
        print(); sys.exit(0)

    if choice == "2":
        sys.exit(0)

    if not accs:
        print("  No accounts. Add to information/btcadspace.com.txt")
        print("  Format: cookie|vernuable_key")
        print("     or: username|password|vernuable_key")
        sys.exit(1)

    for i, parts in enumerate(accs, 1):
        print(f"\n{C.M}════════ Account {i}/{len(accs)} ════════{C.R}")
        try:
            run_account(parts)
        except KeyboardInterrupt:
            print(f"\n  {C.Y}stopped by user{C.R}")
            break
        except Exception as e:
            log(f"error · {e}", "ERR")
        time.sleep(2)

    print(f"\n{C.G}══ DONE ══{C.R}\n")

if __name__ == "__main__":
    main()
