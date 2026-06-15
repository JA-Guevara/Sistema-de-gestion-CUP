# -*- coding: utf-8 -*-
"""Diagrama de despliegue UML estilo Enterprise Architect (nodos 3D)."""
import os
from PIL import Image, ImageDraw
import diagramas as D
S = D.S

# Paleta
DEV_FILL = (251, 246, 233)     # «device» crema
ENV_FILL = (224, 238, 250)     # «executionEnvironment»/server azul
DB_FILL  = (223, 236, 250)
NET_FILL = (236, 236, 242)
HDR_DEV_T=(243,236,205); HDR_DEV_B=(225,210,158)
HDR_ENV_T=(206,226,245); HDR_ENV_B=(176,206,236)
COMP_FILL=(255,255,255)
BORDER = D.C_BORDER
LINE = (90,90,90)

F_T  = D._font("arialbd.ttf", 13)
F_S  = D._font("ariali.ttf", 11)
F_C  = D._font("arial.ttf", 10)
F_LB = D._font("arial.ttf", 10)

def _tw(draw,t,f):
    b=draw.textbbox((0,0),t,font=f); return b[2]-b[0]
def _th(f):
    a,d=f.getmetrics(); return a+d

def node_box(img, draw, x, y, title, stereo, content, fill, hdr_t, hdr_b, as_components=False, icon=None):
    pad=int(12*S)
    lh=int(20*S)
    # medir
    wlines=[_tw(draw,title,F_T), _tw(draw,stereo,F_S)]
    for c in content: wlines.append(_tw(draw,c,F_C)+ (int(22*S) if as_components else 0))
    w=max(wlines)+2*pad
    w=max(w,int(180*S))
    hdr_h=pad+_th(F_S)+int(2*S)+_th(F_T)+int(6*S)
    if as_components:
        body_h=int(10*S)+len(content)*(int(26*S)+int(8*S))+int(4*S)
    else:
        body_h=int(8*S)+len(content)*lh+int(8*S)
    h=hdr_h+body_h
    dep=int(15*S)
    # cara 3D
    top=[(x,y),(x+w,y),(x+w+dep,y-dep),(x+dep,y-dep)]
    side=[(x+w,y),(x+w,y+h),(x+w+dep,y+h-dep),(x+w+dep,y-dep)]
    draw.polygon(top, fill=tuple(min(255,c+10) for c in fill), outline=BORDER)
    draw.polygon(side, fill=tuple(max(0,c-25) for c in fill), outline=BORDER)
    draw.rectangle([x,y,x+w,y+h], fill=fill, outline=BORDER, width=max(1,int(1.3*S)))
    # header degradado
    tmp=Image.new("RGB",(int(w),int(hdr_h)),fill)
    D.gradient_rect(tmp,0,0,w,hdr_h,hdr_t,hdr_b)
    img.paste(tmp,(int(x),int(y)))
    draw.rectangle([x,y,x+w,y+h], outline=BORDER, width=max(1,int(1.3*S)))
    draw.line([(x,y+hdr_h),(x+w,y+hdr_h)],fill=BORDER,width=max(1,int(1.2*S)))
    cy=y+pad/2+int(2*S)
    draw.text((x+w/2,cy),stereo,font=F_S,fill=(70,70,70),anchor="ma")
    cy+=_th(F_S)+int(2*S)
    draw.text((x+w/2,cy),title,font=F_T,fill=(15,15,15),anchor="ma")
    # icono de nodo (esquina sup. der.) opcional
    # contenido
    yy=y+hdr_h+int(8*S)
    if as_components:
        for c in content:
            ch=int(26*S)
            cw=w-2*pad
            draw.rounded_rectangle([x+pad,yy,x+pad+cw,yy+ch],radius=int(4*S),fill=COMP_FILL,outline=(120,120,120),width=max(1,int(1*S)))
            # icono de componente UML
            ix=x+pad+int(8*S); iy=yy+ch/2
            draw.rectangle([ix,iy-int(6*S),ix+int(13*S),iy+int(6*S)],outline=(90,90,90),width=max(1,int(1*S)),fill=(235,242,250))
            draw.rectangle([ix-int(3*S),iy-int(4*S),ix+int(3*S),iy-int(1*S)],outline=(90,90,90),fill=COMP_FILL)
            draw.rectangle([ix-int(3*S),iy+int(1*S),ix+int(3*S),iy+int(4*S)],outline=(90,90,90),fill=COMP_FILL)
            draw.text((ix+int(20*S),yy+ch/2),c,font=F_C,fill=(35,35,35),anchor="lm")
            yy+=ch+int(8*S)
    else:
        for c in content:
            draw.text((x+pad,yy),"•  "+c,font=F_C,fill=(45,45,45),anchor="la")
            yy+=lh
    return {'x':x,'y':y,'w':w,'h':h,'cx':x+w/2,'cy':y+h/2,'r':x+w,'b':y+h,'dep':dep}

def db_cylinder(img, draw, x, y, title, stereo, content, fill):
    pad=int(12*S); lh=int(19*S)
    wlines=[_tw(draw,title,F_T),_tw(draw,stereo,F_S)]
    for c in content: wlines.append(_tw(draw,c,F_C))
    w=max(wlines)+2*pad; w=max(w,int(220*S))
    hdr=_th(F_S)+_th(F_T)+int(10*S)
    body=int(8*S)+len(content)*lh+int(10*S)
    ell=int(22*S)
    h=hdr+body+ell
    # cilindro
    draw.ellipse([x,y,x+w,y+ell],fill=tuple(min(255,c+8) for c in fill),outline=BORDER,width=max(1,int(1.3*S)))
    draw.rectangle([x,y+ell/2,x+w,y+h-ell/2],fill=fill,outline=None)
    draw.line([(x,y+ell/2),(x,y+h-ell/2)],fill=BORDER,width=max(1,int(1.3*S)))
    draw.line([(x+w,y+ell/2),(x+w,y+h-ell/2)],fill=BORDER,width=max(1,int(1.3*S)))
    draw.ellipse([x,y+h-ell,x+w,y+h],fill=fill,outline=BORDER,width=max(1,int(1.3*S)))
    draw.arc([x,y,x+w,y+ell],0,180,fill=BORDER,width=max(1,int(1.3*S)))
    cy=y+ell-int(2*S)
    draw.text((x+w/2,cy),stereo,font=F_S,fill=(70,70,70),anchor="ma"); cy+=_th(F_S)
    draw.text((x+w/2,cy),title,font=F_T,fill=(15,15,15),anchor="ma"); cy+=_th(F_T)+int(6*S)
    for c in content:
        draw.text((x+pad,cy),"•  "+c,font=F_C,fill=(45,45,45),anchor="la"); cy+=lh
    return {'x':x,'y':y,'w':w,'h':h,'cx':x+w/2,'cy':y+h/2,'r':x+w,'b':y+h}

def conn(draw, a, b, label, side_a='r', side_b='l', dashed=False):
    pa = _anchor(a, side_a); pb = _anchor(b, side_b)
    midx=(pa[0]+pb[0])/2
    pts=[pa,(midx,pa[1]),(midx,pb[1]),pb]
    if dashed:
        _dashed(draw, pts, LINE)
    else:
        draw.line(pts, fill=LINE, width=max(1,int(1.3*S)))
    # etiqueta cerca del medio
    lx,ly=midx, (pa[1]+pb[1])/2
    tw=_tw(draw,label,F_LB)
    draw.rectangle([lx-tw/2-int(4*S),ly-int(9*S),lx+tw/2+int(4*S),ly+int(9*S)],fill=(255,255,255))
    draw.text((lx,ly),label,font=F_LB,fill=(60,60,60),anchor="mm")

def _anchor(n, side):
    if side=='r': return (n['r'], n['cy'])
    if side=='l': return (n['x'], n['cy'])
    if side=='t': return (n['cx'], n['y'])
    if side=='b': return (n['cx'], n['b'])
    return (n['cx'], n['cy'])

def _dashed(draw, pts, color):
    for i in range(len(pts)-1):
        x0,y0=pts[i]; x1,y1=pts[i+1]
        import math
        d=math.hypot(x1-x0,y1-y0);
        if d==0: continue
        steps=int(d/(8*S))
        for s in range(steps):
            if s%2==0:
                t0=s/steps; t1=(s+0.6)/steps
                draw.line([(x0+(x1-x0)*t0,y0+(y1-y0)*t0),(x0+(x1-x0)*t1,y0+(y1-y0)*t1)],fill=color,width=max(1,int(1.3*S)))

def render(d, outpath):
    W=int(1580*S); H=int(1120*S)
    img=Image.new("RGB",(W,H),(255,255,255)); draw=ImageDraw.Draw(img)

    by={}
    for n in d['nodos']:
        key=(n['estereotipo'].lower(), n['nombre'].lower())
        by[key]=n
    def find(*subs):
        for (st,nm),n in [( (k[0],k[1]), v) for k,v in by.items()]:
            if all(s in (n['estereotipo']+' '+n['nombre']).lower() for s in subs):
                return n
        return None

    cli = find('device') or find('cliente')
    net = find('network') or find('internet')
    ver = find('executionenvironment') or find('vercel')
    neon= find('database') or find('postgres') or find('neon')
    stripe= find('stripe')
    smtp = find('smtp') or find('google') or find('correo')

    def st(n): return '«%s»' % n['estereotipo']

    # Cliente (izquierda, centro vertical)
    ncli = node_box(img,draw,int(50*S),int(420*S), cli['nombre'].split('(')[0].strip(), st(cli), cli['contenido'], DEV_FILL,HDR_DEV_T,HDR_DEV_B)
    # Internet (network) pequeno
    if net:
        nnet = node_box(img,draw,int(470*S),int(470*S), 'Internet', st(net), net['contenido'], NET_FILL,(232,232,238),(212,212,222))
    # Vercel (centro grande, como componentes)
    nver = node_box(img,draw,int(720*S),int(330*S), 'Servidor de Aplicación (Vercel)', st(ver), ver['contenido'], ENV_FILL,HDR_ENV_T,HDR_ENV_B, as_components=True)
    # Neon DB (derecha arriba) cilindro
    nneon = db_cylinder(img,draw,int(1190*S),int(330*S), neon['nombre'].split('(')[0].strip(), st(neon), [c for c in neon['contenido'] if len(c)<60][:5], DB_FILL)
    # Stripe (derecha abajo)
    nstr = node_box(img,draw,int(1190*S),int(720*S), 'Stripe API', st(stripe), stripe['contenido'], ENV_FILL,HDR_ENV_T,HDR_ENV_B)
    # SMTP (centro abajo)
    nsmtp= node_box(img,draw,int(720*S),int(820*S), 'Google SMTP', st(smtp), smtp['contenido'], ENV_FILL,HDR_ENV_T,HDR_ENV_B)

    # Conexiones
    if net:
        conn(draw, ncli, nnet, 'HTTPS/TLS', 'r','l')
        conn(draw, nnet, nver, 'HTTPS', 'r','l')
    else:
        conn(draw, ncli, nver, 'HTTPS/TLS', 'r','l')
    conn(draw, nver, nneon, 'TCP/IP + SSL  (Doctrine ORM)', 'r','l')
    conn(draw, nver, nstr, 'HTTPS REST + Webhook', 'r','l')
    conn(draw, nver, nsmtp, 'SMTP/TLS :587', 'b','t')

    D.draw_frame(img, draw, W, H, 'deployment '+d.get('titulo','Diagrama de Despliegue'))
    img.save(outpath)
    return outpath
