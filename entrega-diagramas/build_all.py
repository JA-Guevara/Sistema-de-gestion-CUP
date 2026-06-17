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

# Ruido de framework que no aporta al analisis (se omite de las fronteras)
_NOISE = {"_csrf_token", "csrftoken", "csrf_token", "_token"}

def _plain_name(attr):
    """Extrae el nombre limpio de un atributo: quita visibilidad, tipo y sufijos."""
    t = (attr or "").strip()
    if t[:1] in "+-#~":
        t = t[1:].strip()
    # cortar en el primer ':' (tipo), '[' (coleccion/select) o espacio
    t = re.split(r"[:\[\s]", t, maxsplit=1)[0].strip()
    return t

def number_attrs(items, cap):
    """Atributos numerados estilo EA del usuario: '- 1. nombre' (sin tipos)."""
    out = []
    i = 1
    for it in items or []:
        nm = _plain_name(it)
        if not nm or nm.lower() in _NOISE:
            continue
        out.append(f"- {i}. {nm}")
        i += 1
        if i > cap:
            break
    return out

def simple_methods(items, cap):
    """Metodos simples: '+ nombre()' (sin parametros ni tipos de retorno)."""
    out = []
    seen = set()
    for it in items or []:
        t = (it or "").strip()
        if t[:1] in "+-#~":
            t = t[1:].strip()
        name = t.split("(")[0].strip()
        if not name:
            continue
        sig = f"+ {name}()"
        if sig in seen:
            continue
        seen.add(sig)
        out.append(sig)
        if len(out) >= cap:
            break
    return out

def curate(cu):
    cu = copy.deepcopy(cu)
    # ---- Fronteras: max 4; atributos numerados sin tipos, metodos simples
    fr = cu["fronteras"][:4]
    for f in fr:
        f["atributos"] = number_attrs(f.get("atributos", []), 8)
        f["metodos"]   = simple_methods(f.get("metodos", []), 8)
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
        ctrls = [{"nombre": f"Ctr{mod}" if mod else "Controlador", "metodos": metodos}]
    new = []
    for c in ctrls:
        c = dict(c)
        c["metodos"] = simple_methods(c.get("metodos", []), 10)
        new.append(c)
    for c in casos[:1]:
        c = dict(c)
        c["nombre"] = "Casos de Uso"
        c["metodos"] = simple_methods(c.get("metodos", []), 12)
        new.append(c)
    cu["controles"] = new
    # ---- Entidades: max 4; atributos numerados sin tipos, metodos simples
    ents = cu["entidades"][:4]
    for e in ents:
        e["atributos"] = number_attrs(e.get("atributos", []), 10)
        e["metodos"]   = simple_methods(e.get("metodos", []), 8)
    cu["entidades"] = ents
    return cu

# ---------- Render analisis ----------
manifest = []
for cu in DATA["analisis"]:
    c = curate(cu)
    out = f"png/{c['cu']}.png"
    D.render_analisis(c, out, show_stereo=False)
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
