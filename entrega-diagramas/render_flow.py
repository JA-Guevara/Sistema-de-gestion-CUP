# -*- coding: utf-8 -*-
"""Diagramas de Actividades (con swimlanes) y de Estados (UML, estilo Enterprise Architect)."""
import math
from PIL import Image, ImageDraw
import diagramas as D
import uml_core as U
S = D.S

LANE_FILL = [(247,250,253),(245,248,243),(252,250,243),(250,246,250)]

def _anchor(n, side):
    if side=="t": return (n["cx"], n["t"])
    if side=="b": return (n["cx"], n["b"])
    if side=="l": return (n["l"], n["cy"])
    if side=="r": return (n["r"], n["cy"])
    return (n["cx"], n["cy"])

def render_activity(spec, outpath):
    """spec: {titulo, lanes:[..], nodes:[{id,kind,text,lane,row}], edges:[{a,b,label,side?}]}"""
    lanes=spec["lanes"]; nodes={n["id"]:dict(n) for n in spec["nodes"]}
    tmp=Image.new("RGB",(10,10)); md=ImageDraw.Draw(tmp)
    LANE_W=int(330*S); HEAD=int(40*S); TOP=int(60*S); ROW=int(108*S); MARGIN=int(30*S)
    lane_x={ln: MARGIN+i*LANE_W for i,ln in enumerate(lanes)}
    lane_cx={ln: lane_x[ln]+LANE_W/2 for ln in lanes}
    maxrow=max(n["row"] for n in nodes.values())
    W=int(MARGIN*2+len(lanes)*LANE_W); H=int(TOP+HEAD+(maxrow+1)*ROW+int(50*S))

    img,draw=U.canvas(W,H)
    # swimlanes
    for i,ln in enumerate(lanes):
        x0=lane_x[ln]
        draw.rectangle([x0,TOP,x0+LANE_W,H-int(30*S)],fill=LANE_FILL[i%len(LANE_FILL)],outline=(150,150,150),width=max(1,int(1.2*S)))
        draw.rectangle([x0,TOP,x0+LANE_W,TOP+HEAD],fill=(225,230,238),outline=(150,150,150),width=max(1,int(1.2*S)))
        draw.text((x0+LANE_W/2,TOP+HEAD/2),ln,font=U.F_TXB,fill=(40,40,40),anchor="mm")

    # posicionar nodos
    for nid,n in nodes.items():
        cx=lane_cx[n["lane"]]; cy=TOP+HEAD+int(40*S)+n["row"]*ROW
        k=n["kind"]
        if k=="initial":
            r=int(12*S); n.update(cx=cx,cy=cy,l=cx-r,r=cx+r,t=cy-r,b=cy+r)
        elif k=="final":
            r=int(14*S); n.update(cx=cx,cy=cy,l=cx-r,r=cx+r,t=cy-r,b=cy+r)
        elif k=="decision":
            w=int(150*S); h=int(78*S); n.update(cx=cx,cy=cy,l=cx-w/2,r=cx+w/2,t=cy-h/2,b=cy+h/2,w=w,h=h)
        elif k in ("fork","join"):
            w=int(170*S); n.update(cx=cx,cy=cy,l=cx-w/2,r=cx+w/2,t=cy-int(5*S),b=cy+int(5*S),w=w)
        else:
            lines=U.wrap(md,n["text"],U.F_TX,int(LANE_W-60*S))
            w=max(int(180*S),max(U.tw(md,l,U.F_TX) for l in lines)+int(34*S)); h=max(int(50*S),len(lines)*int(16*S)+int(22*S))
            n.update(cx=cx,cy=cy,l=cx-w/2,r=cx+w/2,t=cy-h/2,b=cy+h/2,w=w,h=h)

    # aristas
    for e in spec["edges"]:
        a=nodes[e["a"]]; b=nodes[e["b"]]
        if abs(a["cy"]-b["cy"])<2 or a["lane"]!=b["lane"]:
            # horizontal-ish: salir por lado
            sa = "r" if b["cx"]>=a["cx"] else "l"
            sb = "l" if b["cx"]>=a["cx"] else "r"
            p0=_anchor(a,sa); p1=_anchor(b,sb)
            pts=[p0,((p0[0]+p1[0])/2,p0[1]),((p0[0]+p1[0])/2,p1[1]),p1]
        else:
            p0=_anchor(a,"b"); p1=_anchor(b,"t")
            pts=[p0,p1] if abs(p0[0]-p1[0])<2 else [p0,(p0[0],(p0[1]+p1[1])/2),(p1[0],(p0[1]+p1[1])/2),p1]
        draw.line(pts,fill=U.LINE,width=max(1,int(1.3*S)))
        U._ah_open(draw,pts[-2],pts[-1])
        if e.get("label"):
            mx,my=pts[len(pts)//2]; U.label_on(draw,(mx,my),e["label"])

    # dibujar nodos
    for nid,n in nodes.items():
        k=n["kind"]
        if k=="initial": U.node_initial(draw,n["cx"],n["cy"])
        elif k=="final": U.node_final(draw,n["cx"],n["cy"])
        elif k=="decision": U.diamond_dec(draw,n["cx"],n["cy"],n["w"],n["h"],n.get("text",""))
        elif k in ("fork","join"): U.fork_bar(draw,n["cx"],n["cy"],n["w"])
        else: U.rounded(img,draw,n["l"],n["t"],n["w"],n["h"],n["text"],fill=(235,243,250),border=U.BORDERB)

    U.frame(img,draw,W,H,spec["titulo"])
    img.save(outpath); return img.size

# ---------------- Diagrama de Estados ----------------
def render_state(spec, outpath):
    """spec: {titulo, states:[{id,name,body?,col,row}], transitions:[{a,b,label,via?}], initial:id, finals:[id]}"""
    states={s["id"]:dict(s) for s in spec["states"]}
    tmp=Image.new("RGB",(10,10)); md=ImageDraw.Draw(tmp)
    COLW=int(360*S); ROW=int(135*S); TOP=int(80*S); MARGIN=int(50*S)
    ncol=max(s["col"] for s in states.values())+1
    nrow=max(s["row"] for s in states.values())+1
    W=int(MARGIN*2+ncol*COLW); H=int(TOP+nrow*ROW+int(70*S))
    img,draw=U.canvas(W,H)
    for sid,s in states.items():
        cx=MARGIN+s["col"]*COLW+COLW/2; cy=TOP+s["row"]*ROW
        body=s.get("body")
        w=int(230*S); h=int(74*S) if not body else int(46*S)+len(body)*int(15*S)
        s.update(cx=cx,cy=cy,l=cx-w/2,r=cx+w/2,t=cy-h/2,b=cy+h/2,w=w,h=h)
    # initial node
    init=states.get(spec.get("initial"))
    if spec.get("initial"):
        ix=init["cx"]-init["w"]/2-int(48*S); iy=init["cy"]
        U.node_initial(draw,ix,iy)
        draw.line([(ix+int(10*S),iy),(init["l"],iy)],fill=U.LINE,width=max(1,int(1.3*S)))
        U._ah_filled(draw,(ix,iy),(init["l"],init["cy"]))
    # transitions
    def anch(s,side):
        return {"t":(s["cx"],s["t"]),"b":(s["cx"],s["b"]),"l":(s["l"],s["cy"]),"r":(s["r"],s["cy"])}[side]
    for t in spec["transitions"]:
        a=states[t["a"]]; b=states[t["b"]]
        if a["row"]<b["row"] and a["col"]==b["col"]:
            p0=anch(a,"b"); p1=anch(b,"t"); pts=[p0,p1]
        elif a["row"]==b["row"]:
            sa="r" if b["cx"]>a["cx"] else "l"; sb="l" if b["cx"]>a["cx"] else "r"
            p0=anch(a,sa); p1=anch(b,sb); pts=[p0,p1]
        else:
            # ruta lateral (derecha)
            p0=anch(a,"r"); p1=anch(b,"r"); off=max(a["r"],b["r"])+int(40*S)
            pts=[p0,(off,p0[1]),(off,p1[1]),p1]
        draw.line(pts,fill=U.LINE,width=max(1,int(1.3*S)))
        U._ah_filled(draw,pts[-2],pts[-1])
        if t.get("label"):
            mx,my=pts[len(pts)//2] if len(pts)>2 else ((p0[0]+p1[0])/2,(p0[1]+p1[1])/2)
            U.label_on(draw,(mx,my),t["label"])
    # states
    for sid,s in states.items():
        U.state_box(img,draw,s["l"],s["t"],s["w"],s["h"],s["name"],s.get("body"))
    # finals
    for fid in spec.get("finals",[]):
        s=states.get(fid)
        if not s: continue
        fx=s["r"]+int(50*S); fy=s["cy"]
        draw.line([(s["r"],fy),(fx-int(12*S),fy)],fill=U.LINE,width=max(1,int(1.3*S)))
        U._ah_filled(draw,(s["r"],fy),(fx-int(12*S),fy))
        U.node_final(draw,fx,fy)
    U.frame(img,draw,W,H,spec["titulo"])
    img.save(outpath); return img.size
