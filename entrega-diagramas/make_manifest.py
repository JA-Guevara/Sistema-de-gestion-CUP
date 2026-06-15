# -*- coding: utf-8 -*-
"""Genera manifest.json con dimensiones ajustadas a pagina (Letter vertical) y leyendas de trazabilidad."""
import json, os
from PIL import Image

CUR = json.load(open("data/diagramas_curado.json", encoding="utf-8"))

# Area de contenido utilizable (Letter vertical, margenes 0.6"), reservando espacio de encabezado
MAXW_IN = 7.3
MAXH_IN = 8.7
DPI = 96

def fit(path):
    im = Image.open(path); w, h = im.size
    aspect = w / h
    if aspect >= (MAXW_IN / MAXH_IN):
        tw = MAXW_IN; th = MAXW_IN / aspect
    else:
        th = MAXH_IN; tw = MAXH_IN * aspect
    return {"w_px": w, "h_px": h, "target_w": round(tw * DPI), "target_h": round(th * DPI)}

def short_src(fuentes):
    out = []
    for f in fuentes or []:
        f2 = f.replace("\\", "/")
        i = f2.find("src/")
        out.append(f2[i:] if i >= 0 else f2)
    return out

actor_brief = {}
items = []
for cu in CUR["analisis"]:
    info = fit(f"png/{cu['cu']}.png")
    info.update({
        "tipo": "analisis",
        "cu": cu["cu"],
        "titulo": cu["titulo"],
        "img": f"png/{cu['cu']}.png",
        "actores": cu["actores"],
        "fronteras": [f["nombre"] for f in cu["fronteras"]],
        "controles": [c["nombre"] for c in cu["controles"]],
        "entidades": [e["nombre"] for e in cu["entidades"]],
        "fuentes": short_src(cu.get("fuentes"))[:8],
    })
    items.append(info)

arq = fit("png/ARQUITECTURA.png")
arq.update({"tipo": "arquitectura", "titulo": CUR["arquitectura"]["titulo"],
            "img": "png/ARQUITECTURA.png", "modulos": CUR["arquitectura"]["modulos"],
            "capas": [c["nombre"] for c in CUR["arquitectura"]["capas"]]})

dep = fit("png/DESPLIEGUE.png")
dep.update({"tipo": "despliegue", "titulo": CUR["despliegue"]["titulo"],
            "img": "png/DESPLIEGUE.png",
            "nodos": [f"«{n['estereotipo']}» {n['nombre']}" for n in CUR["despliegue"]["nodos"]]})

manifest = {"analisis": items, "arquitectura": arq, "despliegue": dep}
json.dump(manifest, open("data/manifest.json", "w", encoding="utf-8"), ensure_ascii=False, indent=2)
print("manifest.json OK -", len(items), "CU +", "arquitectura + despliegue")
for it in items:
    print(" ", it["cu"], it["target_w"], "x", it["target_h"], "px @96dpi")
