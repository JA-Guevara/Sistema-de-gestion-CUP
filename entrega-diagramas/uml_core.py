# -*- coding: utf-8 -*-
"""Primitivas UML compartidas (estilo Enterprise Architect) para todos los renderizadores."""
import math
from PIL import Image, ImageDraw
import diagramas as D
S = D.S

# Paleta
CREAM_T=(243,236,205); CREAM_B=(225,210,158); CREAM_BODY=(252,249,239)
BLUE_T=(206,226,245);  BLUE_B=(176,206,236);  BLUE_BODY=(228,238,250)
GREEN_BODY=(225,240,222); GREEN_B=(150,185,150)
UC_BODY=(253,247,224); UC_BORDER=(176,156,92)
BORDER=(150,138,95); BORDERB=(120,140,170)
LINE=(90,90,90); TEXT=(25,25,25)

F_T   = D._font("arialbd.ttf", 12)
F_T2  = D._font("arialbd.ttf", 13)
F_ST  = D._font("ariali.ttf", 10)
F_TX  = D._font("arial.ttf", 11)
F_TXB = D._font("arialbd.ttf", 11)
F_SM  = D._font("arial.ttf", 10)
F_MUL = D._font("arial.ttf", 10)

def tw(draw,t,f):
    b=draw.textbbox((0,0),t,font=f); return b[2]-b[0]
def th(f):
    a,d=f.getmetrics(); return a+d

def wrap(draw, text, font, maxw):
    words=text.split(); lines=[]; cur=""
    for w in words:
        t=(cur+" "+w).strip()
        if tw(draw,t,font)>maxw and cur:
            lines.append(cur); cur=w
        else: cur=t
    if cur: lines.append(cur)
    return lines or [""]

# ---------- arrowheads ----------
def _ah_filled(draw, frm, to, size=11, color=LINE):
    dx,dy=to[0]-frm[0],to[1]-frm[1]; L=math.hypot(dx,dy) or 1
    ux,uy=dx/L,dy/L; px,py=-uy,ux; s=size*S
    p1=(to[0]-ux*s+px*s*0.5, to[1]-uy*s+py*s*0.5)
    p2=(to[0]-ux*s-px*s*0.5, to[1]-uy*s-py*s*0.5)
    draw.polygon([to,p1,p2], fill=color)

def _ah_open(draw, frm, to, size=12, color=LINE, w=1.3):
    dx,dy=to[0]-frm[0],to[1]-frm[1]; L=math.hypot(dx,dy) or 1
    ux,uy=dx/L,dy/L; px,py=-uy,ux; s=size*S
    p1=(to[0]-ux*s+px*s*0.45, to[1]-uy*s+py*s*0.45)
    p2=(to[0]-ux*s-px*s*0.45, to[1]-uy*s-py*s*0.45)
    draw.line([p1,to],fill=color,width=max(1,int(w*S))); draw.line([p2,to],fill=color,width=max(1,int(w*S)))

def _ah_triangle(draw, frm, to, size=16, color=LINE, fill=(255,255,255)):
    """Generalizacion: triangulo hueco en 'to'."""
    dx,dy=to[0]-frm[0],to[1]-frm[1]; L=math.hypot(dx,dy) or 1
    ux,uy=dx/L,dy/L; px,py=-uy,ux; s=size*S
    p1=(to[0]-ux*s+px*s*0.6, to[1]-uy*s+py*s*0.6)
    p2=(to[0]-ux*s-px*s*0.6, to[1]-uy*s-py*s*0.6)
    draw.polygon([to,p1,p2], fill=fill, outline=color)
    return (to[0]-ux*s, to[1]-uy*s)  # base (donde conecta la linea)

def _diamond(draw, at, towards, size=14, color=LINE, fill=(255,255,255)):
    """Rombo (agregacion hueco / composicion lleno) en 'at', apuntando hacia 'towards'."""
    dx,dy=towards[0]-at[0],towards[1]-at[1]; L=math.hypot(dx,dy) or 1
    ux,uy=dx/L,dy/L; px,py=-uy,ux; s=size*S
    tip=(at[0]+ux*s*2, at[1]+uy*s*2)
    l=(at[0]+ux*s+px*s, at[1]+uy*s+py*s)
    r=(at[0]+ux*s-px*s, at[1]+uy*s-py*s)
    draw.polygon([at,l,tip,r], fill=fill, outline=color)
    return tip

def dashed(draw, pts, color=LINE, w=1.2, dash=8):
    for i in range(len(pts)-1):
        x0,y0=pts[i]; x1,y1=pts[i+1]; d=math.hypot(x1-x0,y1-y0)
        if d==0: continue
        n=max(1,int(d/(dash*S)))
        for k in range(n):
            if k%2==0:
                t0=k/n; t1=min((k+0.6)/n,1)
                draw.line([(x0+(x1-x0)*t0,y0+(y1-y0)*t0),(x0+(x1-x0)*t1,y0+(y1-y0)*t1)],fill=color,width=max(1,int(w*S)))

def ortho(p0,p1):
    midx=(p0[0]+p1[0])/2
    return [p0,(midx,p0[1]),(midx,p1[1]),p1]

def clip_box(boxA, boxB):
    """Segmento centro-a-centro recortado a los bordes de dos cajas rectangulares (l,r,t,b,cx,cy)."""
    def edge(box,fx,fy,tx,ty):
        dx,dy=tx-fx,ty-fy
        if dx==0 and dy==0: return (fx,fy)
        cand=[]
        for X in (box["l"],box["r"]):
            if dx!=0:
                t=(X-fx)/dx; y=fy+t*dy
                if 0<=t<=1 and box["t"]-1<=y<=box["b"]+1: cand.append((t,(X,y)))
        for Y in (box["t"],box["b"]):
            if dy!=0:
                t=(Y-fy)/dy; x=fx+t*dx
                if 0<=t<=1 and box["l"]-1<=x<=box["r"]+1: cand.append((t,(x,Y)))
        return (sorted(cand)[0][1] if cand else (fx,fy))
    p1=edge(boxA,boxA["cx"],boxA["cy"],boxB["cx"],boxB["cy"])
    p2=edge(boxB,boxB["cx"],boxB["cy"],boxA["cx"],boxA["cy"])
    return p1,p2

# ---------- shapes ----------
def actor(draw, cx, top, label, scale=1.0):
    return D.draw_actor(draw, cx, top, label)

def use_case(img, draw, cx, cy, text, maxw=170):
    lines=wrap(draw,text,F_TX,int(maxw*S))
    wln=max(tw(draw,l,F_TX) for l in lines)
    w=max(int(120*S), wln+int(46*S)); h=max(int(64*S), len(lines)*int(18*S)+int(38*S))
    box=[cx-w/2,cy-h/2,cx+w/2,cy+h/2]
    draw.ellipse(box, fill=UC_BODY, outline=UC_BORDER, width=max(1,int(1.4*S)))
    ty=cy-(len(lines)*int(18*S))/2
    for l in lines:
        draw.text((cx,ty),l,font=F_TX,fill=TEXT,anchor="ma"); ty+=int(18*S)
    return {"cx":cx,"cy":cy,"w":w,"h":h,"l":cx-w/2,"r":cx+w/2,"t":cy-h/2,"b":cy+h/2}

def boundary_system(draw, x0, y0, x1, y1, title):
    draw.rectangle([x0,y0,x1,y1], outline=(110,110,110), width=max(1,int(1.6*S)))
    draw.text(((x0+x1)/2, y0+int(12*S)), title, font=F_T2, fill=(40,40,40), anchor="ma")

def rounded(img, draw, x, y, w, h, text, fill=BLUE_BODY, border=BORDERB, bold=False, font=None):
    font = font or F_TX
    draw.rounded_rectangle([x,y,x+w,y+h], radius=int(10*S), fill=fill, outline=border, width=max(1,int(1.3*S)))
    lines=wrap(draw,text,font,w-int(16*S)); ty=y+(h-len(lines)*int(16*S))/2
    for l in lines:
        draw.text((x+w/2,ty),l,font=font,fill=TEXT,anchor="ma"); ty+=int(16*S)
    return {"cx":x+w/2,"cy":y+h/2,"l":x,"r":x+w,"t":y,"b":y+h,"w":w,"h":h}

def diamond_dec(draw, cx, cy, w, h, text=""):
    pts=[(cx,cy-h/2),(cx+w/2,cy),(cx,cy+h/2),(cx-w/2,cy)]
    draw.polygon(pts, fill=(255,248,225), outline=(176,156,92), width=max(1,int(1.3*S)))
    if text:
        draw.text((cx,cy),text,font=F_SM,fill=TEXT,anchor="mm")
    return {"cx":cx,"cy":cy,"t":cy-h/2,"b":cy+h/2,"l":cx-w/2,"r":cx+w/2}

def node_initial(draw, cx, cy, r=10):
    draw.ellipse([cx-r*S,cy-r*S,cx+r*S,cy+r*S], fill=(40,40,40))
    return {"cx":cx,"cy":cy,"b":cy+r*S,"t":cy-r*S}

def node_final(draw, cx, cy, r=12):
    draw.ellipse([cx-r*S,cy-r*S,cx+r*S,cy+r*S], outline=(40,40,40), width=max(1,int(1.6*S)))
    draw.ellipse([cx-(r-4)*S,cy-(r-4)*S,cx+(r-4)*S,cy+(r-4)*S], fill=(40,40,40))
    return {"cx":cx,"cy":cy,"t":cy-r*S,"b":cy+r*S}

def fork_bar(draw, cx, cy, w):
    draw.rectangle([cx-w/2,cy-int(4*S),cx+w/2,cy+int(4*S)], fill=(50,50,50))
    return {"cx":cx,"cy":cy,"l":cx-w/2,"r":cx+w/2,"t":cy-int(4*S),"b":cy+int(4*S)}

def state_box(img, draw, x, y, w, h, name, body=None):
    draw.rounded_rectangle([x,y,x+w,y+h], radius=int(12*S), fill=CREAM_BODY, outline=BORDER, width=max(1,int(1.4*S)))
    if body:
        hh=int(26*S)
        draw.text((x+w/2,y+int(7*S)),name,font=F_TXB,fill=TEXT,anchor="ma")
        draw.line([(x,y+hh),(x+w,y+hh)],fill=BORDER,width=max(1,int(1*S)))
        ty=y+hh+int(5*S)
        for ln in body:
            draw.text((x+int(8*S),ty),ln,font=F_SM,fill=(70,70,70),anchor="la"); ty+=int(15*S)
    else:
        draw.text((x+w/2,y+h/2),name,font=F_TXB,fill=TEXT,anchor="mm")
    return {"cx":x+w/2,"cy":y+h/2,"l":x,"r":x+w,"t":y,"b":y+h,"w":w,"h":h}

def package_shape(img, draw, x, y, w, h, title, items=None, fill=CREAM_BODY):
    tabh=int(22*S); tabw=min(w*0.45, tw(draw,title,F_TXB)+int(24*S))
    draw.rectangle([x,y,x+tabw,y+tabh], fill=fill, outline=BORDER, width=max(1,int(1.3*S)))
    draw.rectangle([x,y+tabh,x+w,y+h], fill=fill, outline=BORDER, width=max(1,int(1.3*S)))
    draw.text((x+int(10*S),y+tabh/2),title,font=F_TXB,fill=TEXT,anchor="lm")
    if items:
        ty=y+tabh+int(8*S)
        for it in items:
            draw.text((x+int(12*S),ty),"• "+it,font=F_SM,fill=(60,60,60),anchor="la"); ty+=int(15*S)
    return {"cx":x+w/2,"cy":y+h/2,"l":x,"r":x+w,"t":y,"b":y+h,"w":w,"h":h}

def component_shape(img, draw, x, y, w, h, name, stereo="«component»", fill=BLUE_BODY):
    draw.rectangle([x,y,x+w,y+h], fill=fill, outline=BORDERB, width=max(1,int(1.3*S)))
    # icono de componente
    ix=x+w-int(26*S); iy=y+int(10*S)
    draw.rectangle([ix,iy,ix+int(16*S),iy+int(13*S)], outline=(80,80,80), fill=(255,255,255), width=max(1,int(1*S)))
    draw.rectangle([ix-int(4*S),iy+int(2*S),ix+int(2*S),iy+int(5*S)], outline=(80,80,80), fill=(255,255,255))
    draw.rectangle([ix-int(4*S),iy+int(7*S),ix+int(2*S),iy+int(10*S)], outline=(80,80,80), fill=(255,255,255))
    draw.text((x+w/2,y+h/2-int(8*S)),stereo,font=F_ST,fill=(70,70,70),anchor="mm")
    draw.text((x+w/2,y+h/2+int(8*S)),name,font=F_TXB,fill=TEXT,anchor="mm")
    return {"cx":x+w/2,"cy":y+h/2,"l":x,"r":x+w,"t":y,"b":y+h,"w":w,"h":h}

def label_on(draw, p, text, font=F_SM, bg=(255,255,255)):
    w=tw(draw,text,font); h=th(font)
    draw.rectangle([p[0]-w/2-int(3*S),p[1]-h/2,p[0]+w/2+int(3*S),p[1]+h/2],fill=bg)
    draw.text((p[0],p[1]),text,font=font,fill=(60,60,60),anchor="mm")

def frame(img, draw, W, H, title):
    D.draw_frame(img, draw, W, H, title)

def canvas(W,H):
    img=Image.new("RGB",(int(W),int(H)),(255,255,255))
    return img, ImageDraw.Draw(img)
