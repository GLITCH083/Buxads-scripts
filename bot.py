# ============================================================
#  ALCaptcha Full Pipeline — COMPLETE (matches real AdsLab JS)
# ============================================================

import hashlib
import time
import random
import string
import base64
import json
import sys
import requests
from datetime import datetime

# -------------------- CONFIG --------------------
ADS_TILES_API = "http://37.60.224.60:7861"
API_KEY       = "2e55-4949-2691-2e22-7c41"
SITEKEY       = "apv_f47e28152653294bf67360fc5f2802a57e48"
DOMAIN        = "earnzilla.net"
SUBID         = "widget_user"
CAPTCHA_TYPE  = "motion"

INIT_URL   = "https://adslab.me/api/v1/alcaptcha/init"
VERIFY_URL = "https://adslab.me/api/v1/alcaptcha/verify-click"

GRID_POS = {
    (0, 0): 0, (1, 0): 1, (2, 0): 2,
    (0, 1): 3, (1, 1): 4, (2, 1): 5,
}

_START = time.time()


def log(sec, msg, data=None, level="INFO"):
    ts = datetime.now().strftime("%H:%M:%S.%f")[:-3]
    print(f"[{ts} +{time.time()-_START:7.3f}s] [{sec:<10}] [{level:<5}] {msg}")
    if data is not None:
        s = json.dumps(data, indent=2, ensure_ascii=False, default=str)
        if len(s) > 1800:
            s = s[:1800] + "\n... (truncated)"
        for line in s.splitlines():
            print(f"{'':>40}{line}")


def die(sec, msg, data=None):
    log(sec, msg, data, "FATAL")
    sys.exit(1)


def banner(t):
    print(f"\n{'='*70}\n  {t}\n{'='*70}")


# -------------------- 1) PoW --------------------
def do_pow():
    banner("STEP 1 — PROOF OF WORK")
    seed = (
        "".join(random.choices(string.ascii_lowercase + string.digits, k=13))
        + str(int(time.time() * 1000))
    )
    log("POW", f"seed={seed}")
    t0 = time.time()
    nonce = 0
    while True:
        d = hashlib.sha256(f"{seed}{nonce}".encode()).digest()
        if d[0] == 0 and (d[1] & 0xF0) == 0:
            log("POW", f"FOUND nonce={nonce} in {(time.time()-t0)*1000:.1f}ms")
            return {"seed": seed, "nonce": nonce}
        nonce += 1
        if nonce > 5_000_000:
            die("POW", "gave up after 5M nonces")


# -------------------- 2) Init --------------------
def do_init(pow_block):
    banner("STEP 2 — INIT")
    body = {
        "sitekey": SITEKEY,
        "domain": DOMAIN,
        "subid": SUBID,
        "pow": pow_block,
    }
    r = requests.post(INIT_URL, json=body, timeout=30)
    log("INIT", f"HTTP {r.status_code}")
    resp = r.json()
    log("INIT", "response:", resp)

    if not resp.get("success"):
        die("INIT", f"failed: {resp.get('message')}", resp)

    token = resp.get("token")
    captcha_url = resp.get("captchaUrl") or resp.get("image") or resp.get("gif")
    elements_map = resp.get("elementsMap") or []
    expected = int(resp.get("expectedLength") or 2)

    if not token or not captcha_url:
        die("INIT", "missing token or captchaUrl", list(resp.keys()))

    log("INIT", f"token={token}")
    log("INIT", f"captchaUrl={captcha_url}")
    log("INIT", f"expected={expected} elements={len(elements_map)}")

    return {
        "token": token,
        "captcha_url": captcha_url,
        "elements_map": elements_map,
        "expected_len": expected,
        "start_ms": int(time.time() * 1000),
    }


# -------------------- 2b) Download GIF --------------------
def download_gif_b64(url):
    banner("STEP 2b — DOWNLOAD GIF → BASE64")
    r = requests.get(url, timeout=30)
    log("GIF", f"HTTP {r.status_code} size={len(r.content)}")
    if r.status_code != 200 or len(r.content) < 500:
        die("GIF", "bad download", {"status": r.status_code, "len": len(r.content)})

    try:
        with open("captcha.gif", "wb") as f:
            f.write(r.content)
        log("GIF", "saved -> captcha.gif")
    except Exception:
        pass

    b64 = base64.b64encode(r.content).decode()
    log("GIF", f"base64 len={len(b64)}")
    return b64


# -------------------- 3) Real tiles only --------------------
def get_real_tiles(elements_map):
    banner("STEP 3 — FILTER REAL GRID TILES")
    real = []
    for elem in elements_map:
        css = elem.get("css") or {}
        if css.get("opacity") == 0 or css.get("pointerEvents") == "none":
            continue
        try:
            left_f = float(str(css.get("left", "0")).replace("%", "").strip())
            top_f = float(str(css.get("top", "0")).replace("%", "").strip())
        except ValueError:
            continue
        col = 0 if left_f < 20 else (1 if left_f < 50 else 2)
        row = 0 if top_f < 30 else 1
        g = GRID_POS.get((col, row))
        if g is None:
            continue
        real.append({
            "id": elem["id"],
            "css": css,
            "grid_idx": g,
            "left": left_f,
            "top": top_f,
        })
    real.sort(key=lambda x: x["grid_idx"])
    log("MAP", f"real tiles: {len(real)}")
    names = [
        "top-left", "top-middle", "top-right",
        "bottom-left", "bottom-middle", "bottom-right",
    ]
    for t in real:
        name = names[t["grid_idx"]] if t["grid_idx"] < 6 else "?"
        log("MAP", f"  grid[{t['grid_idx']}] ({name}) {t['id'][:13]}... L={t['left']}% T={t['top']}%")
    return real


# -------------------- 4) Solve tiles --------------------
def solve_tiles(image_b64, expected):
    banner("STEP 4 — SOLVE TILES")
    r = requests.post(
        f"{ADS_TILES_API}/in.php",
        data={
            "key": API_KEY,
            "method": "adslab_tiles",
            "image": image_b64,
            "expected": expected,
            "type": CAPTCHA_TYPE,
        },
        timeout=60,
    )
    text = (r.text or "").strip()
    log("TILES", f"submit HTTP {r.status_code}: {text[:120]}")
    if not text.startswith("OK|"):
        die("TILES", f"submit failed: {text}")

    job_id = text.split("|", 1)[1].strip()
    log("TILES", f"job_id={job_id}")

    for i in range(40):
        time.sleep(1.5)
        try:
            res = requests.get(
                f"{ADS_TILES_API}/res.php",
                params={"key": API_KEY, "action": "get", "id": job_id, "json": 1},
                timeout=30,
            )
        except requests.RequestException as e:
            log("TILES", f"poll {i+1} network: {e}", level="WARN")
            continue

        raw = (res.text or "").strip()
        log("TILES", f"poll {i+1}: {raw[:220]}")

        data = None
        try:
            data = res.json()
        except Exception:
            data = None

        if data is not None:
            if data.get("status") == 1:
                tiles = data.get("tiles") or data.get("selected")
                if not tiles:
                    tok = data.get("token") or data.get("request") or ""
                    if isinstance(tok, str) and tok.startswith("["):
                        tiles = json.loads(tok)
                if tiles:
                    tiles = list(tiles)[:expected]
                    log("TILES", f"tiles={tiles}", level="OK")
                    return tiles
                die("TILES", "status=1 but no tiles", data)

            req = str(data.get("request", ""))
            if req in ("CAPCHA_NOT_READY", "CAPTCHA_NOT_READY"):
                continue
            die("TILES", f"error: {data}")

        if raw in ("CAPCHA_NOT_READY", "CAPTCHA_NOT_READY"):
            continue
        if raw.startswith("OK|"):
            payload = raw.split("|", 1)[1]
            tiles = json.loads(payload)[:expected]
            log("TILES", f"tiles={tiles}", level="OK")
            return tiles
        if raw.startswith("ERROR"):
            die("TILES", raw)

    die("TILES", "timeout")


# -------------------- 5) p_payload (real frontend format) --------------------
def build_ppayload(clicked_ids, real_tiles, expected, start_ms):
    """
    Match official AdsLab JS:
      vt = btoa(x|y|w|h)  where x,y are click INSIDE the tile
      p_payload = reverse(btoa(json))   NO leading '='
    """
    banner("STEP 5 — BUILD p_payload")

    TILE_W, TILE_H = 100.0, 50.0

    vt = []
    for eid in clicked_ids:
        x = random.uniform(TILE_W * 0.25, TILE_W * 0.75)
        y = random.uniform(TILE_H * 0.25, TILE_H * 0.75)
        w, h = TILE_W, TILE_H
        raw = f"{x}|{y}|{w}|{h}"
        vt.append(base64.b64encode(raw.encode()).decode())
        log("P5", f"{eid[:12]}... vt={raw}")

    now = int(time.time() * 1000)
    ct = []
    t = start_ms + random.randint(800, 1500)
    for _ in clicked_ids:
        ct.append(t)
        t += random.randint(350, 900)

    ht = max(500, now - start_ms)

    inner = {
        "a": clicked_ids[:expected],
        "vt": vt,
        "hf": "",
        "ht": ht,
        "dz": "Asia/Karachi",
        "mm": random.randint(0, 15),
        "ct": ct,
    }
    log("P5", "inner:", inner)

    b64 = base64.b64encode(
        json.dumps(inner, separators=(",", ":")).encode()
    ).decode()
    # REAL frontend: reverse only — NO leading "="
    p_payload = b64[::-1]
    log("P5", f"p_payload len={len(p_payload)} head={p_payload[:70]}")
    return p_payload


# -------------------- 6) Verify --------------------
def do_verify(token, p_payload):
    banner("STEP 6 — VERIFY")
    body = {"token": token, "p_payload": p_payload}
    log("VERIFY", f"POST {VERIFY_URL}")
    r = requests.post(VERIFY_URL, json=body, timeout=30)
    log("VERIFY", f"HTTP {r.status_code}")
    resp = r.json()
    log("VERIFY", "response:", resp)

    if not resp.get("success"):
        die("VERIFY", f"failed: {resp.get('message', resp)}")

    final = resp.get("token") or resp.get("request")
    log("VERIFY", f"FINAL TOKEN = {final}", level="OK")
    return final


# -------------------- MAIN --------------------
def main():
    banner("ALCAPTCHA FULL PIPELINE — START")
    log("MAIN", f"sitekey = {SITEKEY}")
    log("MAIN", f"domain  = {DOMAIN}")
    log("MAIN", f"solver  = {ADS_TILES_API}")
    t0 = time.time()

    # 1. PoW
    pow_block = do_pow()

    # 2. Init
    session = do_init(pow_block)
    expected = session["expected_len"]
    start_ms = session["start_ms"]

    # 2b. Download GIF -> base64
    gif_b64 = download_gif_b64(session["captcha_url"])

    # 3. Real grid tiles (drop traps)
    real_tiles = get_real_tiles(session["elements_map"])
    if len(real_tiles) < 6:
        log("MAP", f"warning: only {len(real_tiles)} real tiles", level="WARN")

    # 4. Solve tiles
    tile_indices = solve_tiles(gif_b64, expected)
    tile_indices = tile_indices[:expected]
    log("MAIN", f"tile indices: {tile_indices}")

    # 5. Map grid index -> element id
    id_by_grid = {t["grid_idx"]: t["id"] for t in real_tiles}
    clicked_ids = []
    for idx in tile_indices:
        eid = id_by_grid.get(int(idx))
        if not eid:
            die("MAP", f"no id for grid {idx}", id_by_grid)
        clicked_ids.append(eid)
        log("MAP", f"index {idx} -> {eid}")

    # 6. Build payload + verify
    p_payload = build_ppayload(clicked_ids, real_tiles, expected, start_ms)
    final_token = do_verify(session["token"], p_payload)

    banner("RESULT — SUCCESS")
    out = {
        "status": 1,
        "token": final_token,
        "tiles": tile_indices,
        "ids": clicked_ids,
    }
    print(json.dumps(out, indent=2))
    log("MAIN", f"total {time.time()-t0:.2f}s", level="OK")


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\nInterrupted.")
        sys.exit(130)