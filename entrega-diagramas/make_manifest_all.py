# -*- coding: utf-8 -*-
import json, os
from PIL import Image
MAXW, MAXH, DPI = 7.3, 8.7, 96
def fit(img):
    im=Image.open(f"png/{img}"); w,h=im.size; a=w/h
    if a>=MAXW/MAXH: tw=MAXW; th=MAXW/a
    else: th=MAXH; tw=MAXH*a
    return {"img":f"png/{img}","target_w":round(tw*DPI),"target_h":round(th*DPI)}
def E(img,cu,titulo,extra=None):
    d=fit(img); d.update({"titulo":titulo}); 
    if cu: d["cu"]=cu
    if extra: d.update(extra)
    return d

ana = json.load(open("data/manifest.json",encoding="utf-8"))
seqs = json.load(open("data/manifest_secuencias.json",encoding="utf-8"))
uc = json.load(open("data/manifest_uc.json",encoding="utf-8"))
flow = json.load(open("data/manifest_flow.json",encoding="utf-8"))

out = {}
out["contexto"] = E("UC_CONTEXTO.png", None, "Diagrama de Contexto")
out["uc_general"] = E("UC_GENERAL.png", None, "Casos de Uso General")
out["uc_modulos"] = [E(f, None, t) for f,t in uc if f.startswith("UC_M_")]
out["bce"] = [E(f"{a['cu']}.png", a["cu"], a["titulo"],
               {"actores":a["actores"],"fronteras":a["fronteras"],"controles":a["controles"],"entidades":a["entidades"]})
              for a in ana["analisis"]]
out["secuencias"] = [E(f"SEC_{s['cu']}.png", s["cu"], s["titulo"], {"lifelines":s["lifelines"]}) for s in seqs]
out["secuencia_integral"] = E("SECUENCIA_INSCRIPCION.png", None, "Secuencia integral: Inscripcion + Pago + Confirmacion")
out["actividades"] = [E(f, None, t) for f,t in flow["actividades"]]
out["estados"] = [E(f, None, t) for f,t in flow["estados"]]
out["componentes"] = E("COMPONENTES.png", None, "Diagrama de Componentes")
out["despliegue"] = E("DESPLIEGUE.png", None, "Diagrama de Despliegue")
out["paquetes"] = E("PAQUETES.png", None, "Diagrama de Paquetes")
out["clases"] = E("CLASES.png", None, "Diagrama de Clases Completo")
out["conceptual"] = E("MODELO_CONCEPTUAL.png", None, "Modelo Conceptual de Datos")
out["logico"] = E("MODELO_LOGICO.png", None, "Modelo Logico de Datos")
out["fisico"] = E("MODELO_FISICO.png", None, "Modelo Fisico de Datos (PostgreSQL)")

json.dump(out, open("data/master_manifest.json","w",encoding="utf-8"), ensure_ascii=False, indent=2)
n = 2+len(out["uc_modulos"])+len(out["bce"])+len(out["secuencias"])+1+len(out["actividades"])+len(out["estados"])+6
print("master_manifest OK | total diagramas:", n)
