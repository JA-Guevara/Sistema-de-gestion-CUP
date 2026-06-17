# -*- coding: utf-8 -*-
"""Diagramas de Casos de Uso y de Contexto (estilo Enterprise Architect)."""
import math
from PIL import Image, ImageDraw
import diagramas as D
import uml_core as U
S = D.S

def _actor_anchor(ax_cx, ay_waist, side):
    return (ax_cx + (int(16*S) if side=="L" else -int(16*S)), ay_waist)

def render_usecase(spec, outpath):
    """spec: {titulo, actores:[{nombre,side}], casos:[{id,nombre,col}], asociaciones:[(actorNombre,casoId)],
             includes:[(a,b)], extends:[(a,b)], generaliza:[(sub,super)]}"""
    actores=spec["actores"]; casos=spec["casos"]
    L=[a for a in actores if a["side"]=="L"]; R=[a for a in actores if a["side"]=="R"]
    ncol=max((c.get("col",0) for c in casos), default=0)+1

    tmp=Image.new("RGB",(10,10)); md=ImageDraw.Draw(tmp)
    # medir ovalos
    for c in casos:
        lines=U.wrap(md,c["nombre"],U.F_TX,int(165*S))
        c["_w"]=max(int(150*S), max(U.tw(md,l,U.F_TX) for l in lines)+int(44*S))
        c["_h"]=max(int(62*S), len(lines)*int(18*S)+int(36*S))

    col_w=[max((c["_w"] for c in casos if c.get("col",0)==k), default=int(160*S)) for k in range(ncol)]
    GAPX=int(46*S); GAPY=int(26*S)
    actor_w=int(150*S); MARGIN=int(30*S)
    bx0 = MARGIN + (actor_w if L else 0) + int(30*S)
    # x de cada columna
    colx=[]; x=bx0+int(40*S)
    for k in range(ncol):
        colx.append(x+col_w[k]/2); x+=col_w[k]+GAPX
    bx1 = x - GAPX + int(40*S)
    W = int(bx1 + int(30*S) + (actor_w if R else 0) + MARGIN)

    # posicionar casos por columna (centrado vertical)
    by_col={k:[c for c in casos if c.get("col",0)==k] for k in range(ncol)}
    col_h={k:sum(c["_h"] for c in by_col[k])+GAPY*(len(by_col[k])-1) for k in range(ncol)}
    content_h=max(list(col_h.values())+[int(300*S)])
    TOP=int(70*S)
    for k in range(ncol):
        y=TOP+int(40*S)+(content_h-col_h[k])/2
        for c in by_col[k]:
            c["cx"]=colx[k]; c["cy"]=y+c["_h"]/2
            c["l"]=c["cx"]-c["_w"]/2; c["r"]=c["cx"]+c["_w"]/2; c["t"]=c["cy"]-c["_h"]/2; c["b"]=c["cy"]+c["_h"]/2
            y+=c["_h"]+GAPY
    H=int(TOP+40*S+content_h+int(70*S))
    by1=TOP+int(20*S)+content_h+int(40*S)

    img,draw=U.canvas(W,H)
    # boundary
    U.boundary_system(draw, bx0, TOP, bx1, by1, spec.get("sistema","Sistema Web Integrado de Gestion del CUP - FICCT"))

    # actores
    amap={}
    def place_actors(group, side):
        if not group: return
        cx = MARGIN+actor_w/2 if side=="L" else W-MARGIN-actor_w/2
        bh=int(95*S); total=len(group)*bh
        y0=TOP+(by1-TOP-total)/2 + int(10*S)
        for a in group:
            _,waist=D.draw_actor(draw,cx,y0,a["nombre"])
            amap[a["nombre"]]={"cx":cx,"waist":waist-int(8*S),"side":side}
            y0+=bh
    place_actors(L,"L"); place_actors(R,"R")

    # asociaciones actor-caso
    cmap={c["id"]:c for c in casos}
    for actorN,casoId in spec.get("asociaciones",[]):
        if actorN not in amap or casoId not in cmap: continue
        a=amap[actorN]; c=cmap[casoId]
        p0=_actor_anchor(a["cx"],a["waist"],a["side"])
        # punto en el ovalo mas cercano (lado izq o der)
        px = c["l"] if a["side"]=="L" else c["r"]
        p1=(px,c["cy"])
        draw.line([p0,p1],fill=(110,110,110),width=max(1,int(1.1*S)))

    # include / extend
    def uc_edge(a,b):
        return U.clip_box(a,b) if hasattr(U,"clip_box") else (( a["cx"],a["cy"]),(b["cx"],b["cy"]))
    for kind,pairs,lbl in [("include",spec.get("includes",[]),"«include»"),("extend",spec.get("extends",[]),"«extend»")]:
        for a,b in pairs:
            if a not in cmap or b not in cmap: continue
            ca,cb=cmap[a],cmap[b]
            p0=(ca["cx"],ca["cy"]); p1=(cb["cx"],cb["cy"])
            # recortar a bordes elipticos (aprox con rect)
            p0=_ellipse_edge(ca,p1); p1b=_ellipse_edge(cb,p0)
            U.dashed(draw,[p0,p1b],color=(120,120,120),w=1.2)
            U._ah_open(draw,p0,p1b,color=(120,120,120))
            U.label_on(draw,((p0[0]+p1b[0])/2,(p0[1]+p1b[1])/2),lbl,font=U.F_ST)

    # generalizacion entre actores
    for sub,sup in spec.get("generaliza",[]):
        if sub in amap and sup in amap:
            a=amap[sub]; b=amap[sup]
            p0=(a["cx"],a["waist"]); p1=(b["cx"],b["waist"])
            draw.line([p0,p1],fill=(90,90,90),width=max(1,int(1.2*S)))
            U._ah_triangle(draw,p0,p1)

    # ovalos encima
    for c in casos:
        U.use_case(img,draw,c["cx"],c["cy"],c["nombre"])
        if c.get("id","").startswith("CU"):
            draw.text((c["cx"],c["t"]-int(11*S)),c["id"],font=U.F_SM,fill=(110,110,110),anchor="mm")

    U.frame(img,draw,W,H,spec["titulo"])
    img.save(outpath); return img.size

def _ellipse_edge(c, towards):
    ang=math.atan2(towards[1]-c["cy"],towards[0]-c["cx"])
    return (c["cx"]+math.cos(ang)*c["_w"]/2, c["cy"]+math.sin(ang)*c["_h"]/2)

# ---------------- Diagrama de Contexto ----------------
def render_contexto(spec, outpath):
    """spec: {titulo, sistema, actores:[nombre], externos:[nombre], rel_actores:[(actor,etiqueta)], rel_externos:[(ext,etiqueta)]}"""
    W=int(1500*S); H=int(1040*S)
    img,draw=U.canvas(W,H)
    cx,cy=W/2,H/2
    # sistema central (hub)
    rw,rh=int(320*S),int(150*S)
    draw.ellipse([cx-rw/2,cy-rh/2,cx+rw/2,cy+rh/2],fill=U.BLUE_BODY,outline=(70,100,150),width=max(2,int(2*S)))
    for i,l in enumerate(U.wrap(draw,spec["sistema"],U.F_T2,rw-int(40*S))):
        draw.text((cx,cy-int(20*S)+i*int(20*S)),l,font=U.F_T2,fill=(20,40,80),anchor="ma")
    actores=spec["actores"]; externos=spec.get("externos",[])
    # actores alrededor (mitad superior/lados), externos abajo
    import math as m
    n=len(actores)
    rad_x=int(560*S); rad_y=int(340*S)
    rel_a={a:lbl for a,lbl in spec.get("rel_actores",[])}
    for i,a in enumerate(actores):
        ang=m.pi*(1.15 - 1.30*i/(max(1,n-1)))  # arco superior
        ax=cx+m.cos(ang)*rad_x; ay=cy-int(60*S)+ -m.sin(ang)*rad_y*0.0 - m.sin(ang)*rad_y
        ax=cx+m.cos(ang)*rad_x; ay=cy - m.sin(ang)*rad_y
        _,waist=D.draw_actor(draw,ax,ay-int(40*S),a)
        # linea al hub
        p0=(ax,waist-int(8*S))
        ang2=m.atan2(cy-p0[1],cx-p0[0]); p1=(cx+m.cos(ang2)*rw/2*0.98, cy+m.sin(ang2)*rh/2*0.98)
        draw.line([p0,p1],fill=(110,110,110),width=max(1,int(1.2*S)))
        if a in rel_a:
            U.label_on(draw,((p0[0]+p1[0])/2,(p0[1]+p1[1])/2),rel_a[a],font=U.F_SM)
    # externos abajo
    ne=len(externos); rel_e={e:lbl for e,lbl in spec.get("rel_externos",[])}
    for j,e in enumerate(externos):
        ex=cx-(ne-1)*int(220*S)/2 + j*int(220*S); ey=cy+int(380*S)
        bw,bh=int(180*S),int(70*S)
        U.rounded(img,draw,ex-bw/2,ey-bh/2,bw,bh,e,fill=(238,232,222),border=U.BORDER,bold=True)
        p0=(ex,ey-bh/2); p1=(cx,cy+rh/2)
        U.dashed(draw,[p0,(p0[0],(p0[1]+p1[1])/2),(p1[0],(p0[1]+p1[1])/2),p1],color=(120,120,120))
        if e in rel_e:
            U.label_on(draw,((p0[0]+p1[0])/2,(p0[1]+p1[1])/2),rel_e[e],font=U.F_SM)
    U.frame(img,draw,W,H,spec["titulo"])
    img.save(outpath); return img.size
