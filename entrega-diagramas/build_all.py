# -*- coding: utf-8 -*-
"""Cura los datos extraidos y renderiza los 18 diagramas (16 analisis + arquitectura + despliegue)."""
import json, re, copy, os
import diagramas as D

DATA = json.load(open("data/diagramas.json", encoding="utf-8"))
os.makedirs("png", exist_ok=True)

# Modulo por CU (para nombrar la caja al consolidar varios controladores)
def modulo_de(fuentes):
    for f in fuentes or []:
        m = re.search(r"src[\\/]+([A-Za-z]+)", f)
        if m:
            return m.group(1)
    return ""

def norm_vis(items, force=None, default="+"):
    """Normaliza prefijos de visibilidad."""
    out = []
    for it in items or []:
        t = it.strip()
        body = t
        if t[:1] in "+-#~":
            body = t[1:].strip()
        vis = force if force else (t[0] if t[:1] in "+-#~" else default)
        out.append(f"{vis} {body}")
    return out

def curate(cu):
    cu = copy.deepcopy(cu)
    # ---- Fronteras: max 4; atributos '-', metodos '+'
    fr = cu["fronteras"][:4]
    for f in fr:
        f["atributos"] = norm_vis(f.get("atributos", []), force="-")[:8]
        f["metodos"]   = norm_vis(f.get("metodos", []), force="+")[:8]
    cu["fronteras"] = fr
    # ---- Controles: separar Casos de Uso; consolidar controladores si > 2
    casos = [c for c in cu["controles"] if "caso" in c["nombre"].lower()]
    ctrls = [c for c in cu["controles"] if "caso" not in c["nombre"].lower()]
    if len(ctrls) > 2:
        metodos = []
        for c in ctrls:
            for m in c.get("metodos", []):
                mm = m.strip()
                if mm not in metodos:
                    metodos.append(mm)
        mod = modulo_de(cu.get("fuentes"))
        ctrls = [{"nombre": f"ctr{mod}" if mod else "Controladores", "metodos": metodos}]
    new = []
    for c in ctrls:
        c = dict(c)
        c["metodos"] = norm_vis(c.get("metodos", []), force="+")[:10]
        new.append(c)
    for c in casos[:1]:
        c = dict(c)
        c["nombre"] = "Casos de Uso"
        c["metodos"] = norm_vis(c.get("metodos", []), force="+")[:12]
        new.append(c)
    cu["controles"] = new
    # ---- Entidades: max 4; atributos '-', metodos '+'
    ents = cu["entidades"][:4]
    for e in ents:
        e["atributos"] = norm_vis(e.get("atributos", []), force="-")[:12]
        e["metodos"]   = norm_vis(e.get("metodos", []), force="+")[:8]
    cu["entidades"] = ents
    return cu

# ---------- Render analisis ----------
manifest = []
for cu in DATA["analisis"]:
    c = curate(cu)
    out = f"png/{c['cu']}.png"
    D.render_analisis(c, out)
    manifest.append((c["cu"], c["titulo"], out, len(c["fronteras"]), len(c["controles"]), len(c["entidades"])))
    print("OK", c["cu"], out)

# ---------- Render arquitectura ----------
D.render_arquitectura(DATA["arquitectura"], "png/ARQUITECTURA.png")
print("OK ARQUITECTURA")

# ---------- Render despliegue ----------
import render_deploy
render_deploy.render(DATA["despliegue"], "png/DESPLIEGUE.png")
print("OK DESPLIEGUE")

json.dump(DATA, open("data/diagramas_curado.json", "w", encoding="utf-8"), ensure_ascii=False, indent=2)
print("\nTotal analisis:", len(manifest))
