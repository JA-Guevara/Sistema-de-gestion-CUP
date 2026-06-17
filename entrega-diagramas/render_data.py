# -*- coding: utf-8 -*-
"""Diagrama de Clases completo + Modelo Logico + Modelo Fisico (PostgreSQL), fieles a modelo.json."""
import json, math
from PIL import Image, ImageDraw
import diagramas as D
import uml_core as U
S = D.S

# Agrupacion por contexto (para clusterizar y reducir cruces)
CLUSTERS = [
    ("Seguridad",   ["User","Role","Permission","PasswordResetToken"]),
    ("Gestion CUP", ["Gestion","PeriodoGestion","ConfiguracionGestion","CarreraGestion","CupoGestion","HistorialGestion"]),
    ("Academico",   ["Carrera","Materia","Aula","Turno","Grupo","Horario"]),
    ("Inscripcion / Pago", ["Inscripcion","Documento","VerificacionDocumento","CalendarioRevision","Pago"]),
    ("Asignacion / Notas", ["AsignacionGrupo","AsignacionDocente","Nota"]),
    ("Bitacora",    ["LogEntry"]),
]

def load():
    ents = json.load(open("data/modelo.json", encoding="utf-8"))
    return {e["name"]: e for e in ents}

def clip(boxA, boxB):
    """Segmento centro-a-centro recortado a los bordes de cada caja rectangular."""
    ax,ay=boxA["cx"],boxA["cy"]; bx,by=boxB["cx"],boxB["cy"]
    def edge(box,fromx,fromy,tox,toy):
        dx,dy=tox-fromx,toy-fromy
        if dx==0 and dy==0: return (fromx,fromy)
        cand=[]
        for X in (box["l"],box["r"]):
            if dx!=0:
                t=(X-fromx)/dx; y=fromy+t*dy
                if 0<=t<=1 and box["t"]-1<=y<=box["b"]+1: cand.append((t,(X,y)))
        for Y in (box["t"],box["b"]):
            if dy!=0:
                t=(Y-fromy)/dy; x=fromx+t*dx
                if 0<=t<=1 and box["l"]-1<=x<=box["r"]+1: cand.append((t,(x,Y)))
        if not cand: return (fromx,fromy)
        cand.sort(); return cand[0][1]
    p1=edge(boxA,ax,ay,bx,by); p2=edge(boxB,bx,by,ax,ay)
    return p1,p2

# ---------------- Diagrama de Clases ----------------
def render_class(outpath):
    M = load()
    tmp=Image.new("RGB",(10,10)); md=ImageDraw.Draw(tmp)
    def box_attrs(e):
        return [f"- {f['name']}: {f['php'].lstrip('?')}" + ("" if not f['nullable'] else " [0..1]") for f in e["fields"]]
    def box_methods(e):
        return e["methods"][:7]
    # medir cajas
    sizes={}
    for name,e in M.items():
        w,h,*_=D.measure_class(md,"«entity»",name,box_attrs(e),box_methods(e))
        sizes[name]=(w,h)
    # layout en columnas por cluster
    GAPX=int(70*S); GAPY=int(34*S); MARGIN=int(45*S); TOP=int(60*S)
    boxes={}; x=MARGIN
    colmeta=[]
    for title,names in CLUSTERS:
        names=[n for n in names if n in M]
        cw=max((sizes[n][0] for n in names), default=int(160*S))
        y=TOP+int(30*S)
        for n in names:
            w,h=sizes[n]
            boxes[n]={"x":x,"y":y,"w":w,"h":h}
            y+=h+GAPY
        colmeta.append((title,x,cw,y))
        x+=cw+GAPX
    W=int(x-GAPX+MARGIN)
    H=int(max(b["y"]+b["h"] for b in boxes.values())+int(60*S))
    img,draw=U.canvas(W,H)
    # cabecera de cluster
    for title,cx,cw,_ in colmeta:
        draw.text((cx+cw/2, TOP-int(6*S)), title, font=U.F_T2, fill=(40,60,95), anchor="ma")
    # centros
    for n,b in boxes.items():
        b["cx"]=b["x"]+b["w"]/2; b["cy"]=b["y"]+b["h"]/2; b["l"]=b["x"]; b["r"]=b["x"]+b["w"]; b["t"]=b["y"]; b["b"]=b["y"]+b["h"]
    # relaciones (lineas) primero
    drawn=set()
    for name,e in M.items():
        for r in e["rels"]:
            tgt=r["target"]
            if tgt not in boxes: continue
            key=tuple(sorted([name,tgt]))+(r["prop"],)
            if key in drawn: continue
            drawn.add(key)
            a=boxes[name]; b=boxes[tgt]
            p1,p2=clip(a,b)
            draw.line([p1,p2],fill=U.LINE,width=max(1,int(1.2*S)))
            # multiplicidades + adornos
            if r["kind"]=="ManyToOne":
                U.label_on(draw,(p1[0],p1[1]),"*"); U.label_on(draw,(p2[0],p2[1]),"1")
                U._ah_open(draw,p1,p2)  # navegabilidad hacia el "1"
            elif r["kind"]=="OneToMany":
                # composicion: rombo lleno en el lado contenedor (name)
                U._diamond(draw,(p1[0],p1[1]),(p2[0],p2[1]),fill=U.LINE)
                U.label_on(draw,(p2[0]+int(14*S),p2[1]),"*")
            elif r["kind"]=="ManyToMany":
                U.label_on(draw,(p1[0],p1[1]),"*"); U.label_on(draw,(p2[0],p2[1]),"*")
            elif r["kind"]=="OneToOne":
                U.label_on(draw,(p1[0],p1[1]),"1"); U.label_on(draw,(p2[0],p2[1]),"1")
    # cajas encima
    for name,e in M.items():
        b=boxes[name]
        D.draw_class(img,draw,b["x"],b["y"],"«entity»",name,box_attrs(e),box_methods(e))
    U.frame(img,draw,W,H,"class Modelo de Dominio - SIGECUP-FICCT (Diagrama de Clases)")
    img.save(outpath); return img.size

# ---------------- Tabla ER (logico/fisico) ----------------
def er_table_box(img, draw, x, y, rows, title, mode):
    """rows: list of (marker, text). mode logico/fisico."""
    pad=int(10*S); lh=int(19*S); hh=int(28*S)
    wln=max((U.tw(draw,t,U.F_SM) for _,t in rows), default=int(120*S))
    w=max(int(170*S), wln+int(40*S)); h=hh+len(rows)*lh+int(8*S)
    draw.rectangle([x,y,x+w,y+h], fill=(252,249,239), outline=U.BORDER, width=max(1,int(1.3*S)))
    tmp=Image.new("RGB",(int(w),int(hh)),(252,249,239)); D.gradient_rect(tmp,0,0,w,hh,U.CREAM_T,U.CREAM_B)
    img.paste(tmp,(int(x),int(y)))
    draw.rectangle([x,y,x+w,y+h], outline=U.BORDER, width=max(1,int(1.3*S)))
    draw.line([(x,y+hh),(x+w,y+hh)],fill=U.BORDER,width=max(1,int(1.2*S)))
    draw.text((x+w/2,y+int(7*S)),title,font=U.F_TXB,fill=U.TEXT,anchor="ma")
    ty=y+hh+int(4*S)
    for marker,text in rows:
        col=(150,40,40) if marker=="PK" else ((40,90,150) if marker=="FK" else (70,70,70))
        if marker:
            draw.text((x+int(6*S),ty),marker,font=U.F_SM,fill=col,anchor="la")
        draw.text((x+int(34*S),ty),text,font=U.F_SM,fill=(45,45,45),anchor="la")
        ty+=lh
    return {"cx":x+w/2,"cy":y+h/2,"l":x,"r":x+w,"t":y,"b":y+h,"w":w,"h":h,"x":x,"y":y}

def render_er(outpath, mode):
    """mode: 'logico' o 'fisico'."""
    M=load()
    tmp=Image.new("RGB",(10,10)); md=ImageDraw.Draw(tmp)
    # FK targets -> tabla destino
    def rows_for(e):
        rows=[]
        for f in e["fields"]:
            if mode=="fisico":
                t=f"{f['name']}  {f['sql']}" + ("" if f['nullable'] else " NOT NULL") + (" UNIQUE" if f['unique'] else "")
            else:
                t=f"{f['name']}: {f['php'].lstrip('?')}"
            rows.append(("PK" if f["pk"] else "", t))
        # FKs por ManyToOne
        for r in e["rels"]:
            if r["kind"] in ("ManyToOne","OneToOne") and r["owner"]:
                col=f"{r['prop']}_id" + (" INTEGER" if mode=="fisico" else ": int")
                if mode=="fisico" and not r["nullable"]:
                    col+=" NOT NULL"
                rows.append(("FK", col + f"  -> {M[r['target']]['table'] if r['target'] in M else r['target']}"))
        return rows
    sizes={}
    for name,e in M.items():
        rows=rows_for(e);
        wln=max((U.tw(md,t,U.F_SM) for _,t in rows), default=int(120*S))
        w=max(int(180*S), wln+int(40*S)); h=int(28*S)+len(rows)*int(19*S)+int(8*S)
        sizes[name]=(w,h,rows)
    GAPX=int(60*S); GAPY=int(30*S); MARGIN=int(45*S); TOP=int(60*S)
    boxes={}; x=MARGIN; colmeta=[]
    for title,names in CLUSTERS:
        names=[n for n in names if n in M]
        cw=max((sizes[n][0] for n in names), default=int(180*S))
        y=TOP+int(26*S)
        for n in names:
            w,h,_=sizes[n]; boxes[n]={"x":x,"y":y,"w":w,"h":h}; y+=h+GAPY
        colmeta.append((title,x,cw)); x+=cw+GAPX
    W=int(x-GAPX+MARGIN); H=int(max(b["y"]+b["h"] for b in boxes.values())+int(60*S))
    img,draw=U.canvas(W,H)
    for title,cx,cw in colmeta:
        draw.text((cx+cw/2,TOP-int(8*S)),title,font=U.F_T2,fill=(40,60,95),anchor="ma")
    for n,b in boxes.items():
        b["cx"]=b["x"]+b["w"]/2; b["cy"]=b["y"]+b["h"]/2; b["l"]=b["x"]; b["r"]=b["x"]+b["w"]; b["t"]=b["y"]; b["b"]=b["y"]+b["h"]
    # relaciones (crow's foot simplificado con 1 y *)
    for name,e in M.items():
        for r in e["rels"]:
            if r["kind"] in ("ManyToOne","OneToOne") and r["owner"] and r["target"] in boxes:
                a=boxes[name]; b=boxes[r["target"]]
                p1,p2=clip(a,b)
                draw.line([p1,p2],fill=(110,110,150),width=max(1,int(1.1*S)))
                U.label_on(draw,p1,"*" if r["kind"]=="ManyToOne" else "1")
                U.label_on(draw,p2,"1")
    for name,e in M.items():
        b=boxes[name]; w,h,rows=sizes[name]
        er_table_box(img,draw,b["x"],b["y"],rows,f"{M[name]['table']}" if mode=="fisico" else name, mode)
    ttl = "data Modelo Fisico (PostgreSQL) - SIGECUP-FICCT" if mode=="fisico" else "data Modelo Logico de Datos - SIGECUP-FICCT"
    U.frame(img,draw,W,H,ttl)
    img.save(outpath); return img.size

if __name__=="__main__":
    print("clases:", render_class("png/CLASES.png"))
    print("logico:", render_er("png/MODELO_LOGICO.png","logico"))
    print("fisico:", render_er("png/MODELO_FISICO.png","fisico"))
