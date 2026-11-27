"""英語版ルールブックの下地づくり。原本 PDF から「日本語の本文だけを抜いたページ」を画像にする。

使い方（通常は prepare.sh 経由）:
  python prepare.py --data <data/> --out <build/rulebook-en/> [--dpi 400]

出力（--out の下）:
  bg/pN.png     文字を抜いたページ
  orig/pN.png   原本そのまま（見比べ用）
  spans/pN.json 全 span（bbox・フォント・サイズ・色・文字列・removed）
  spans/pN.txt  抜いた文字の一覧（行ごと。上から下・左から右）
座標は PDF のポイント（左上原点。PyMuPDF と同じ）。
抜く規則: フォントが BODY_FONTS で、サイズが MIN_SIZE 以上で、数字だけでない span。
上書きは overrides.json（page・text・x・y で指定して keep / remove）。
検査: 原本と下地の差分が、抜いた文字の範囲（余白つき）の外に出ていたら非 0 で終わる。
"""
import argparse
import json
import os
import sys
from pathlib import Path

import numpy as np
import pymupdf

pymupdf.TOOLS.mupdf_display_errors(False)

BODY_FONTS = ("HiraMaruProN-W4", "HiraKakuStd-W8", "HiraKakuStdN-W8")
MIN_SIZE = 5.5
SHRINK = 0.25          # 墨消しの矩形の上下を縮める割合（隣の行に触れないため）
GAP_SPLIT = 8.0        # 文字の間にこの幅以上の空白があれば別の矩形に分ける（pt）
MASK_MARGIN = 2.0      # 検査で、抜いた文字の範囲に足す余白（pt）
DIFF_THRESHOLD = 8     # 差分とみなす画素差（0-255）
NOISE_LEVEL = 40       # 範囲外でこれ以下の差は描画の揺らぎ（アンチエイリアス）として許す
SPACES = " 　 \t"
TOL = 3.0              # 上書き表の位置の許容（pt）


def is_digits(text):
    t = text.strip().replace("　", "")
    return t.isdigit() or (t != "" and all(c in "0123456789０１２３４５６７８９" for c in t))


def load_overrides(path):
    return json.loads(Path(path).read_text(encoding="utf-8")) if Path(path).exists() else []


def decide(page_no, span, overrides):
    """(removed, 理由)"""
    text = span["text"]
    x0, y0 = span["bbox"][0], span["bbox"][1]
    for o in overrides:
        if o["page"] != page_no or o["text"] not in text:
            continue
        if "x" in o and abs(o["x"] - x0) > TOL:
            continue
        if "y" in o and abs(o["y"] - y0) > TOL:
            continue
        return o["action"] == "remove", "override"
    if not text.strip():
        return False, "blank"
    if span["font"] in BODY_FONTS and span["size"] >= MIN_SIZE and not is_digits(text):
        return True, "rule"
    return False, "rule"


def char_rects(span):
    """空白でない文字の範囲ごとの矩形（上下は縮めない）。行頭の字下げの空白は含めない。"""
    runs, cur, last = [], None, None
    for ch in span["chars"]:
        if ch["c"] in SPACES:
            continue
        b = ch["bbox"]
        if cur is not None and b[0] - last <= GAP_SPLIT:
            cur[2] = max(cur[2], b[2])
        else:
            if cur is not None:
                runs.append(cur)
            cur = [b[0], span["bbox"][1], b[2], span["bbox"][3]]
        last = b[2]
    if cur is not None:
        runs.append(cur)
    return runs


def span_record(page_no, span, removed, reason):
    return {
        "p": page_no,
        "bbox": [round(v, 2) for v in span["bbox"]],
        "font": span["font"],
        "size": round(span["size"], 2),
        "color": "%06x" % span["color"],
        "text": span["text"],
        "removed": removed,
        "reason": reason,
    }


def render(doc, i, dpi):
    pix = doc[i].get_pixmap(dpi=dpi, alpha=False)
    arr = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width, pix.n)
    return pix, arr


def write_lines_txt(page_no, lines, path):
    """抜いた行の一覧。近い行（縦位置が 2.5 pt 以内）を 1 組にして、左から右に並べる。"""
    items = sorted(lines, key=lambda l: (l["yc"], l["x0"]))
    rows = []
    for it in items:
        if rows and abs(it["yc"] - rows[-1]["yc"]) <= 2.5:
            rows[-1]["items"].append(it)
        else:
            rows.append({"yc": it["yc"], "items": [it]})
    out = [f"# p{page_no}: 抜いた文字（pt、左上原点）。x0 y0 x1 y1 | サイズ フォント | 文字列"]
    for r in rows:
        for it in sorted(r["items"], key=lambda l: l["x0"]):
            out.append(
                f"{it['x0']:7.1f} {it['y0']:7.1f} {it['x1']:7.1f} {it['y1']:7.1f} | "
                f"{it['size']:4.1f} {it['font'].replace('HiraKakuStdN', 'HKN').replace('HiraKakuStd', 'HK').replace('HiraMaruProN', 'HM')} | {it['text']}"
            )
    Path(path).write_text("\n".join(out) + "\n", encoding="utf-8")


def fat(mask, k):
    """k x k 以上の塊に含まれる True の画素だけを返す"""
    h, w = mask.shape
    core = np.ones((h - k + 1, w - k + 1), dtype=bool)
    for dy in range(k):
        for dx in range(k):
            core &= mask[dy:h - k + 1 + dy, dx:w - k + 1 + dx]
    res = np.zeros_like(mask)
    for dy in range(k):
        for dx in range(k):
            res[dy:h - k + 1 + dy, dx:w - k + 1 + dx] |= core
    return res & mask


def components(mask):
    """True の格子の連結成分の外接矩形 [(r0, c0, r1, c1)]"""
    seen = np.zeros_like(mask, dtype=bool)
    res = []
    for r, c in zip(*np.nonzero(mask)):
        if seen[r, c]:
            continue
        stack = [(r, c)]
        seen[r, c] = True
        r0 = r1 = r
        c0 = c1 = c
        while stack:
            a, b = stack.pop()
            r0, r1, c0, c1 = min(r0, a), max(r1, a), min(c0, b), max(c1, b)
            for da in (-1, 0, 1):
                for db in (-1, 0, 1):
                    na, nb = a + da, b + db
                    if 0 <= na < mask.shape[0] and 0 <= nb < mask.shape[1] and mask[na, nb] and not seen[na, nb]:
                        seen[na, nb] = True
                        stack.append((na, nb))
        res.append((r0, c0, r1, c1))
    return res


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--data", required=True)
    ap.add_argument("--out", required=True)
    ap.add_argument("--dpi", type=int, default=400)
    args = ap.parse_args()
    here = Path(__file__).resolve().parent
    src = Path(args.data) / "rulebook" / "rulebook_web.pdf"
    if not src.exists():
        sys.exit(f"原本が見つかりません: {src}（IR_DATA_DIR で指定できます）")
    out = Path(args.out)
    for d in ("bg", "orig", "spans"):
        (out / d).mkdir(parents=True, exist_ok=True)
    overrides = load_overrides(here / "overrides.json")

    orig = pymupdf.open(str(src))
    work = pymupdf.open(str(src))
    scale = args.dpi / 72.0
    failures = []
    kept_big = []
    removed_count = 0

    for i in range(len(work)):
        n = i + 1
        page = work[i]
        raw = page.get_text("rawdict")
        records, lines_out, mask_rects = [], [], []
        for b in raw["blocks"]:
            if b["type"] != 0:
                continue
            for line in b["lines"]:
                rem_spans = []
                for span in line["spans"]:
                    text = "".join(c["c"] for c in span["chars"])
                    span["text"] = text
                    removed, reason = decide(n, span, overrides)
                    records.append(span_record(n, span, removed, reason))
                    if removed:
                        rem_spans.append(span)
                        for r in char_rects(span):
                            mask_rects.append(pymupdf.Rect(r))
                            h = r[3] - r[1]
                            page.add_redact_annot(pymupdf.Rect(r[0], r[1] + h * SHRINK, r[2], r[3] - h * SHRINK), fill=False)
                    elif span["size"] >= MIN_SIZE and text.strip() and not is_digits(text):
                        kept_big.append((n, span, reason))
                if rem_spans:
                    bb = [s["bbox"] for s in rem_spans]
                    lines_out.append({
                        "x0": min(v[0] for v in bb), "y0": min(v[1] for v in bb),
                        "x1": max(v[2] for v in bb), "y1": max(v[3] for v in bb),
                        "yc": (min(v[1] for v in bb) + max(v[3] for v in bb)) / 2,
                        "size": rem_spans[0]["size"], "font": rem_spans[0]["font"],
                        "text": "".join(s["text"] for s in rem_spans).strip(),
                    })
        removed_count += sum(1 for r in records if r["removed"])
        page.apply_redactions(images=pymupdf.PDF_REDACT_IMAGE_NONE, graphics=pymupdf.PDF_REDACT_LINE_ART_NONE,
                              text=pymupdf.PDF_REDACT_TEXT_REMOVE)
        (out / "spans" / f"p{n}.json").write_text(json.dumps(records, ensure_ascii=False, indent=1), encoding="utf-8")
        write_lines_txt(n, lines_out, out / "spans" / f"p{n}.txt")

        pix_o, a_o = render(orig, i, args.dpi)
        pix_b, a_b = render(work, i, args.dpi)
        pix_o.save(str(out / "orig" / f"p{n}.png"))
        pix_b.save(str(out / "bg" / f"p{n}.png"))

        # 検査: 差分が抜いた文字の範囲の外に出ていないか
        delta = np.abs(a_o.astype(np.int16) - a_b.astype(np.int16)).max(axis=2)
        diff = delta > DIFF_THRESHOLD
        total = int(diff.sum())
        allowed = np.zeros_like(diff)
        for r in mask_rects:
            x0 = max(int((r.x0 - MASK_MARGIN) * scale), 0)
            y0 = max(int((r.y0 - MASK_MARGIN) * scale), 0)
            x1 = min(int((r.x1 + MASK_MARGIN) * scale) + 1, diff.shape[1])
            y1 = min(int((r.y1 + MASK_MARGIN) * scale) + 1, diff.shape[0])
            allowed[y0:y1, x0:x1] = True
        strong = (delta > NOISE_LEVEL) & ~allowed
        # 検査 1: 範囲外の差分のうち 4x4 以上の塊になるもの。1〜3 画素幅の線状の差分は、箱の枠線の描画の揺らぎ
        # （墨消しで内容が書き直されると、細い枠線の太さが 0.1〜0.2 pt 変わって描かれる）なので許す
        outside = fat(strong, 4)
        # 検査 2: 残した文字（番号札の数字・カード内の文字など）の範囲では、2x2 の塊も許さない
        keep_zone = np.zeros_like(strong)
        for r in records:
            if not r["removed"] and r["text"].strip():
                bx0, by0, bx1, by1 = r["bbox"]
                keep_zone[max(int(by0 * scale), 0):int(by1 * scale) + 1, max(int(bx0 * scale), 0):int(bx1 * scale) + 1] = True
        outside |= fat(strong & keep_zone, 2)
        weak = int((diff & ~allowed & ~outside).sum())
        cell = 8
        h, w = outside.shape
        grid = outside[: h // cell * cell, : w // cell * cell].reshape(h // cell, cell, w // cell, cell).any(axis=(1, 3))
        comps = components(grid)
        if os.environ.get('IR_DEBUG_DIFF'):
            pymupdf.Pixmap(pymupdf.csGRAY, w, h, bytes((outside * 255).astype(np.uint8).tobytes()), False).save(str(out / f'diff-p{n}.png'))
        print(f"p{n}: span {len(records)}、抜いた {sum(1 for r in records if r['removed'])}、差分の画素 {total}、範囲外の差分 {int(outside.sum())}（うち図形の縁の揺らぎ {weak}）")
        for r0, c0, r1, c1 in comps:
            msg = f"p{n}: 範囲外の差分 x={c0 * cell / scale:.1f}-{(c1 + 1) * cell / scale:.1f} y={r0 * cell / scale:.1f}-{(r1 + 1) * cell / scale:.1f} pt"
            failures.append(msg)
            print("  NG " + msg)

    print(f"\n抜いた span の合計: {removed_count}")
    print("\n5.5 pt 以上なのに残した span（ページ・x0 y0・フォント・サイズ・理由・文字列）:")
    for n, s, reason in kept_big:
        print(f"  p{n} ({s['bbox'][0]:.0f}, {s['bbox'][1]:.0f}) {s['font']} {s['size']:.1f} [{reason}] {s['text'].strip()[:60]}")
    if failures:
        print(f"\n検査に失敗: 範囲外の差分が {len(failures)} か所", file=sys.stderr)
        sys.exit(1)
    print("\n検査に成功: 差分はすべて抜いた文字の範囲の中")


if __name__ == "__main__":
    main()
