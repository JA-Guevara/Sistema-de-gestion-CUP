# -*- coding: utf-8 -*-
"""Diagrama de secuencia UML estilo Enterprise Architect (lineas de vida, activaciones, mensajes)."""
import os
from PIL import Image, ImageDraw
import diagramas as D
S = D.S

# Colores por tipo de linea de vida
HEAD = {
    "actor":   (None, None, None),
    "boundary":((243,236,205),(225,210,158),(252,249,239)),
    "control": ((243,236,205),(225,210,158),(252,249,239)),
    "entity":  ((243,236,205),(225,210,158),(252,249,239)),
    "external":((206,226,245),(176,206,236),(228,238,250)),
    "database":((206,226,245),(176,206,236),(228,238,250)),
}
LINE = (90,90,90)
ACT_FILL = (236,230,205)
ACT_BORDER = (150,138,95)
FRAG = (90,110,150)

F_HEAD = D._font("arialbd.ttf", 12)
F_ST   = D._font("ariali.ttf", 10)
F_MSG  = D._font("arial.ttf", 11)
F_NUM  = D._font("arialbd.ttf", 11)
F_FRAG = D._font("arialbd.ttf", 11)

def _tw(draw,t,f):
    b=draw.textbbox((0,0),t,font=f); return b[2]-b[0]
def _th(f):
    a,d=f.getmetrics(); return a+d

def render(seq, outpath):
    lifelines = seq["lifelines"]
    mensajes = seq["mensajes"]
    fragmentos = seq.get("fragmentos", [])

    tmp=Image.new("RGB",(10,10)); md=ImageDraw.Draw(tmp)

    # ancho de cada cabecera
    def head_w(ll):
        w = _tw(md, ll["nombre"], F_HEAD)
        if ll["tipo"] not in ("actor",):
            w = max(w, _tw(md, "«%s»"%ll["tipo"], F_ST))
        return max(w + int(26*S), int(120*S))
    hw = [head_w(ll) for ll in lifelines]

    GAP = int(46*S)
    MARGIN = int(40*S)
    TOP = int(40*S)
    head_h = int(74*S)
    # x centro de cada linea de vida
    xs=[]; x=MARGIN
    for i,w in enumerate(hw):
        xs.append(x + w/2); x += w + GAP
    W = int(x - GAP + MARGIN)

    # asignar y a cada mensaje (con bandas de fragmento)
    row=int(40*S)
    frag_pad=int(34*S)
    y0 = TOP + head_h + int(30*S)
    msg_y=[]; frag_spans=[]
    y=y0
    fmap={}  # indice msg -> fragmento que empieza
    for f in fragmentos: fmap[f["from"]]=f
    open_frag=None
    for i,m in enumerate(mensajes):
        if i in fmap:
            y += frag_pad
            frag_spans.append({"label":fmap[i]["label"], "y0":y-int(10*S)})
        msg_y.append(y)
        y += row
    H = int(y + int(40*S))

    # cierre de bandas de fragmento (y1)
    for k,f in enumerate(fragmentos):
        fr=frag_spans[k]
        last_idx=f["to"] if f["to"]<len(msg_y) else len(msg_y)-1
        fr["y1"]=msg_y[last_idx]+int(14*S)
        fr["x0"]=MARGIN-int(8*S); fr["x1"]=W-MARGIN+int(8*S)

    img=Image.new("RGB",(W,H),(255,255,255)); draw=ImageDraw.Draw(img)
    idx={ll["id"]:i for i,ll in enumerate(lifelines)}

    # lineas de vida (dashed) primero
    for i,ll in enumerate(lifelines):
        x=xs[i]
        _dash_v(draw, x, TOP+head_h, H-int(30*S), (150,150,150))

    # bandas de fragmento
    for fr in frag_spans:
        draw.rounded_rectangle([fr["x0"],fr["y0"],fr["x1"],fr["y1"]], radius=int(4*S), outline=(170,185,210), width=max(1,int(1*S)))
        lbl=fr["label"]
        tw=_tw(draw,lbl,F_FRAG)
        draw.polygon([(fr["x0"],fr["y0"]),(fr["x0"]+tw+int(20*S),fr["y0"]),(fr["x0"]+tw+int(20*S),fr["y0"]+int(20*S)),(fr["x0"]+tw+int(10*S),fr["y0"]+int(28*S)),(fr["x0"],fr["y0"]+int(28*S))], fill=(225,232,243), outline=(170,185,210))
        draw.text((fr["x0"]+int(8*S),fr["y0"]+int(6*S)), lbl, font=F_FRAG, fill=(40,55,90), anchor="la")

    # barras de activacion (stack por linea de vida)
    stacks={ll["id"]:[] for ll in lifelines}
    acts=[]
    num=0
    for i,m in enumerate(mensajes):
        y=msg_y[i]; t=m.get("tipo","call")
        if t in ("call","self","async"):
            stacks[m["a"]].append(y)
        elif t=="return":
            if stacks.get(m["de"]):
                sy=stacks[m["de"]].pop()
                acts.append((m["de"], sy, y))
    # las activaciones sin retorno explicito se dibujan como "blip" corto (no hasta el fondo)
    for lid,st in stacks.items():
        for sy in st:
            acts.append((lid, sy, sy+int(0.55*row)))
    aw=int(9*S)
    for lid,sy,ey in acts:
        x=xs[idx[lid]]
        draw.rectangle([x-aw/2, sy-int(6*S), x+aw/2, ey], fill=ACT_FILL, outline=ACT_BORDER, width=max(1,int(1*S)))

    # mensajes
    for i,m in enumerate(mensajes):
        y=msg_y[i]; t=m.get("tipo","call")
        a=xs[idx[m["de"]]]; b=xs[idx[m["a"]]]
        txt=m["txt"]
        if t!="return":
            num+=1; txt=f"{num}: {txt}"
        if m["de"]==m["a"]:
            _self_msg(draw, a, y, txt)
            continue
        dashed = (t=="return")
        _msg(draw, a, b, y, txt, dashed=dashed, open_head=(t in ("return","async")))

    # cabeceras (encima)
    for i,ll in enumerate(lifelines):
        _header(img, draw, xs[i], TOP, hw[i], head_h, ll)

    D.draw_frame(img, draw, W, H, "sd "+seq.get("titulo","Diagrama de Secuencia"))
    img.save(outpath)
    return outpath

def _header(img, draw, cx, y, w, h, ll):
    if ll["tipo"]=="actor":
        D.draw_actor(draw, cx, y, ll["nombre"])
        return
    t,b,body = HEAD.get(ll["tipo"], HEAD["control"])
    x0=cx-w/2; bh=int(50*S)
    draw.rounded_rectangle([x0,y,x0+w,y+bh], radius=int(5*S), fill=body, outline=D.C_BORDER, width=max(1,int(1.2*S)))
    tmp=Image.new("RGB",(int(w),int(bh)),body); D.gradient_rect(tmp,0,0,w,bh,t,b)
    mask=Image.new("L",(int(w),int(bh)),0); ImageDraw.Draw(mask).rounded_rectangle([0,0,w,bh],radius=int(5*S),fill=255)
    img.paste(tmp,(int(x0),int(y)),mask)
    draw.rounded_rectangle([x0,y,x0+w,y+bh], radius=int(5*S), outline=D.C_BORDER, width=max(1,int(1.2*S)))
    cy=y+int(8*S)
    draw.text((cx,cy),"«%s»"%ll["tipo"],font=F_ST,fill=(80,70,40),anchor="ma"); cy+=_th(F_ST)+int(1*S)
    draw.text((cx,cy),ll["nombre"],font=F_HEAD,fill=(15,15,15),anchor="ma")

def _msg(draw, a, b, y, txt, dashed=False, open_head=False):
    if dashed:
        _dash_h(draw, a, b, y, LINE)
    else:
        draw.line([(a,y),(b,y)], fill=LINE, width=max(1,int(1.3*S)))
    _arrow(draw, a, b, y, open_head)
    # etiqueta sobre la flecha
    midx=(a+b)/2
    draw.text((midx, y-int(7*S)), txt, font=F_MSG, fill=(30,30,30), anchor="ms")

def _self_msg(draw, x, y, txt):
    w=int(26*S); h=int(20*S)
    draw.line([(x,y),(x+w,y),(x+w,y+h),(x+2*S,y+h)], fill=LINE, width=max(1,int(1.3*S)))
    _arrow(draw, x+w, x, y+h, False)
    draw.text((x+w+int(8*S), y), txt, font=F_MSG, fill=(30,30,30), anchor="lm")

def _arrow(draw, a, b, y, open_head):
    d = int(11*S)
    sign = 1 if b>=a else -1
    tip=(b,y)
    p1=(b-sign*d, y-int(5*S)); p2=(b-sign*d, y+int(5*S))
    if open_head:
        draw.line([p1,tip], fill=LINE, width=max(1,int(1.3*S)))
        draw.line([p2,tip], fill=LINE, width=max(1,int(1.3*S)))
    else:
        draw.polygon([tip,p1,p2], fill=LINE)

def _dash_v(draw, x, y0, y1, color):
    yy=y0
    while yy<y1:
        draw.line([(x,yy),(x,min(yy+int(7*S),y1))], fill=color, width=max(1,int(1.1*S)))
        yy+=int(12*S)

def _dash_h(draw, x0, x1, y, color):
    step=int(12*S); a,b=(x0,x1) if x0<x1 else (x1,x0); xx=a
    while xx<b:
        draw.line([(xx,y),(min(xx+int(7*S),b),y)], fill=color, width=max(1,int(1.3*S)))
        xx+=step
