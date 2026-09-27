#!/usr/bin/env python3
"""
label_shake.py  —  Label shake GIFs with correct icon index 0..3

Layout reminder:
  0 = top-left     1 = top-right
  2 = bottom-left  3 = bottom-right

Usage:
  python3 label_shake.py                 # label all unlabeled shake_*.gif
  python3 label_shake.py --start 0       # from index
  python3 label_shake.py --review        # re-check already labeled
  python3 label_shake.py --export        # write labels.json + labels.csv only

Keys during labeling:
  0 1 2 3  = correct icon
  s        = skip for now
  q        = quit & save
  p        = print trajectory summary again
  r        = re-open gif path hint

Saves:
  shake_dataset/labels.json   {"shake_000.gif": 2, ...}
  shake_dataset/labels.csv    file,label,mode
"""
import argparse, json, os, sys
from pathlib import Path
import numpy as np
from PIL import Image
from scipy import ndimage

ROOT = Path(__file__).resolve().parent / "shake_dataset"
GIF_DIR = ROOT / "gifs"
PREV_DIR = ROOT / "previews"
LABELS_JSON = ROOT / "labels.json"
LABELS_CSV = ROOT / "labels.csv"
LOGO_BOX = (25, 70, 75, 120)
ICONS = {
    0: (115, 10, 185, 80),
    1: (210, 15, 280, 85),
    2: (120, 105, 195, 175),
    3: (215, 105, 290, 175),
}
NAMES = {0: "top-left", 1: "top-right", 2: "bottom-left", 3: "bottom-right"}

def load_labels():
    if LABELS_JSON.exists():
        return json.loads(LABELS_JSON.read_text())
    return {}

def save_labels(labels):
    ROOT.mkdir(parents=True, exist_ok=True)
    LABELS_JSON.write_text(json.dumps(labels, indent=2, sort_keys=True))
    with LABELS_CSV.open("w") as f:
        f.write("file,label,mode\n")
        for k in sorted(labels.keys()):
            mode = "shake" if k.startswith("shake") else "scale"
            f.write(f"{k},{labels[k]},{mode}\n")
    print(f"saved {len(labels)} labels → {LABELS_JSON}")

def frames_from_path(path):
    im = Image.open(path)
    frames = []
    try:
        while True:
            frames.append(np.array(im.convert("RGB"), dtype=np.float64))
            im.seek(im.tell() + 1)
    except EOFError:
        pass
    return frames

def track(frames, box, is_logo=False):
    cxs, cys, scs = [], [], []
    for fr in frames:
        x0, y0, x1, y1 = [int(v) for v in box]
        x0, y0 = max(0, x0), max(0, y0)
        x1, y1 = min(fr.shape[1], x1), min(fr.shape[0], y1)
        crop = fr[y0:y1, x0:x1]
        if is_logo:
            r, g, b = crop[..., 0], crop[..., 1], crop[..., 2]
            mask = (g > r + 12) & (g > b + 12) & (g > 60)
        else:
            gray = crop.mean(axis=2)
            bg = np.median(gray)
            mask = gray < (bg - 10)
            mask = ndimage.binary_opening(mask, np.ones((2, 2)))
            lab, n = ndimage.label(mask)
            if n > 0:
                sizes = ndimage.sum(mask, lab, range(1, n + 1))
                mask = lab == (int(np.argmax(sizes)) + 1)
        ys, xs = np.where(mask)
        if len(xs) < 5:
            cxs.append(np.nan); cys.append(np.nan); scs.append(0.0)
        else:
            cxs.append(float(xs.mean() + x0))
            cys.append(float(ys.mean() + y0))
            scs.append(float(np.sqrt(len(xs))))
    return np.array(cxs), np.array(cys), np.array(scs)

def fill(a):
    a = a.copy()
    n = np.isnan(a)
    if n.all():
        return np.zeros_like(a)
    if n.any():
        i = np.arange(len(a))
        a[n] = np.interp(i[n], i[~n], a[~n])
    return a

def summarize(path):
    frames = frames_from_path(path)
    q_cx, q_cy, q_sc = track(frames, LOGO_BOX, True)
    q_pos = float(np.hypot(np.nanmax(q_cx) - np.nanmin(q_cx), np.nanmax(q_cy) - np.nanmin(q_cy)))
    q_scr = float(np.nanmax(q_sc) - np.nanmin(q_sc))
    print(f"  frames={len(frames)}  Q pos_range={q_pos:.2f}  Q scale_range={q_scr:.2f}")
    print(f"  Q cx: {np.round(fill(q_cx), 1).tolist()}")
    print(f"  Q cy: {np.round(fill(q_cy), 1).tolist()}")
    print("  icons (pos_range, scale_range):")
    for i, box in ICONS.items():
        cx, cy, sc = track(frames, box, False)
        pos = float(np.hypot(np.nanmax(cx) - np.nanmin(cx), np.nanmax(cy) - np.nanmin(cy)))
        scr = float(np.nanmax(sc) - np.nanmin(sc))
        print(f"    {i} {NAMES[i]:12s}  pos={pos:5.1f}  sc={scr:5.1f}")
    return frames

def ensure_preview(gif_path):
    name = gif_path.stem
    prev = PREV_DIR / f"{name}_strip.png"
    if prev.exists():
        return prev
    PREV_DIR.mkdir(parents=True, exist_ok=True)
    try:
        frames = frames_from_path(gif_path)
        idxs = np.linspace(0, len(frames) - 1, min(6, len(frames))).astype(int)
        imgs = [Image.fromarray(frames[i].astype(np.uint8)) for i in idxs]
        w, h = imgs[0].size
        strip = Image.new("RGB", (w * len(imgs), h))
        for i, im in enumerate(imgs):
            strip.paste(im, (i * w, 0))
        strip.save(prev)
    except Exception as e:
        print("preview err", e)
        return None
    return prev

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--start", type=int, default=0)
    ap.add_argument("--review", action="store_true")
    ap.add_argument("--export", action="store_true")
    args = ap.parse_args()

    labels = load_labels()
    if args.export:
        save_labels(labels)
        return

    gifs = sorted(GIF_DIR.glob("shake_*.gif"))
    if not gifs:
        print(f"No GIFs in {GIF_DIR}")
        print("Run: python3 collect_shake.py --target 150")
        return

    if args.review:
        todo = gifs
    else:
        todo = [g for g in gifs if g.name not in labels]

    # resume from --start index in filename
    todo = [g for g in todo if int(g.stem.split("_")[1]) >= args.start]

    print("=" * 60)
    print("LABEL SHAKE CAPTCHA")
    print("  0 = top-left     1 = top-right")
    print("  2 = bottom-left  3 = bottom-right")
    print("  keys: 0/1/2/3 = label | s=skip | q=quit | p=stats")
    print("=" * 60)
    print(f"total gifs={len(gifs)}  already labeled={len(labels)}  todo={len(todo)}")

    labeled_now = 0
    for n, gpath in enumerate(todo):
        print()
        print(f"[{n+1}/{len(todo)}] {gpath.name}")
        prev = ensure_preview(gpath)
        if prev:
            print(f"  preview strip: {prev}")
        print(f"  gif path:      {gpath}")
        try:
            summarize(gpath)
        except Exception as e:
            print("  summarize err", e)

        while True:
            ans = input("  correct icon [0-3 / s / q]: ").strip().lower()
            if ans in ("0", "1", "2", "3"):
                labels[gpath.name] = int(ans)
                labeled_now += 1
                if labeled_now % 5 == 0:
                    save_labels(labels)
                print(f"  → saved label={ans} ({NAMES[int(ans)]})")
                break
            if ans == "s":
                print("  skipped")
                break
            if ans == "q":
                save_labels(labels)
                print(f"quit. labeled this session={labeled_now} total={len(labels)}")
                return
            if ans == "p":
                try:
                    summarize(gpath)
                except Exception as e:
                    print(e)
                continue
            print("  invalid — use 0 1 2 3 s q")

    save_labels(labels)
    print()
    print(f"DONE session_labeled={labeled_now} total_labels={len(labels)}")
    # distribution
    from collections import Counter
    c = Counter(labels.values())
    print("label distribution:", dict(sorted(c.items())))

if __name__ == "__main__":
    main()