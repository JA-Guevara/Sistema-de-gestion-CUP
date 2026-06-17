# -*- coding: utf-8 -*-
"""Sanea y renderiza los 16 diagramas de secuencia (uno por CU) + manifest para el Word."""
import json, os, sys
from PIL import Image
import render_sequence as RS

VALID_TIPOS = {"actor", "boundary", "control", "entity", "external", "database"}

def sanitize(seq):
    lls = seq.get("lifelines", [])
    # ids unicos y tipos validos
    seen = set(); clean_ll = []
    for ll in lls:
        lid = str(ll.get("id", "")).strip()
        if not lid or lid in seen:
            continue
        seen.add(lid)
        tipo = ll.get("tipo", "control")
        if tipo not in VALID_TIPOS:
            tipo = "control"
        clean_ll.append({"id": lid, "nombre": ll.get("nombre", lid), "tipo": tipo})
    ids = {ll["id"] for ll in clean_ll}
    seq["lifelines"] = clean_ll
    # mensajes con de/a validos
    msgs = []
    for m in seq.get("mensajes", []):
        de, a = str(m.get("de", "")), str(m.get("a", ""))
        if de in ids and a in ids:
            t = m.get("tipo", "call")
            if t not in ("call", "return", "self", "async"):
                t = "call"
            msgs.append({"de": de, "a": a, "txt": m.get("txt", ""), "tipo": t})
    seq["mensajes"] = msgs
    # fragmentos clamp
    n = len(msgs)
    frs = []
    for f in seq.get("fragmentos", []):
        try:
            fr = int(f.get("from", 0)); to = int(f.get("to", 0))
        except Exception:
            continue
        fr = max(0, min(fr, n - 1)); to = max(fr, min(to, n - 1))
        if n > 0:
            frs.append({"label": f.get("label", ""), "from": fr, "to": to})
    seq["fragmentos"] = frs
    return seq

def fit(path, maxw=7.3, maxh=8.7, dpi=96):
    im = Image.open(path); w, h = im.size
    a = w / h
    if a >= maxw / maxh:
        tw = maxw; th = maxw / a
    else:
        th = maxh; tw = maxh * a
    return {"w_px": w, "h_px": h, "target_w": round(tw * dpi), "target_h": round(th * dpi)}

def main():
    raw = json.load(open("data/secuencias_raw.json", encoding="utf-8"))
    seqs = raw["result"]["secuencias"] if "result" in raw else raw["secuencias"]
    # ordenar por CU
    seqs = sorted(seqs, key=lambda s: s.get("cu", ""))
    manifest = []
    os.makedirs("png", exist_ok=True)
    for s in seqs:
        s = sanitize(s)
        cu = s["cu"]
        out = f"png/SEC_{cu}.png"
        RS.render(s, out)
        info = fit(out)
        info.update({"cu": cu, "titulo": s["titulo"], "img": out,
                     "lifelines": [l["nombre"] for l in s["lifelines"]],
                     "n_msgs": len(s["mensajes"])})
        manifest.append(info)
        print("OK", cu, len(s["lifelines"]), "lifelines", len(s["mensajes"]), "msgs ->", Image.open(out).size)
    json.dump(seqs, open("data/secuencias_curado.json", "w", encoding="utf-8"), ensure_ascii=False, indent=2)
    json.dump(manifest, open("data/manifest_secuencias.json", "w", encoding="utf-8"), ensure_ascii=False, indent=2)
    print("TOTAL:", len(manifest))

if __name__ == "__main__":
    main()
