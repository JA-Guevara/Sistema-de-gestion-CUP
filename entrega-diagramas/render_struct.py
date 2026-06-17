# -*- coding: utf-8 -*-
"""Diagramas de Componentes, Paquetes y Modelo Conceptual (estilo Enterprise Architect)."""
import math
from PIL import Image, ImageDraw
import diagramas as D
import uml_core as U
S = D.S

# ---------------- COMPONENTES ----------------
def render_componentes(outpath):
    layers = [
        ("Frontend",      [("Twig + Bootstrap","«ui»"),("Stimulus / Chart.js","«ui»")]),
        ("Capa UI (Controllers)", [("Controllers por modulo","«component»"),("Security: CsrfManager / SessionGuard / PermissionGuard","«component»")]),
        ("Application Services",  [("Casos de Uso (Application/UseCase)","«component»"),("DTOs / Request","«component»")]),
        ("Domain",        [("Entidades + Catalogos + Policies","«component»")]),
        ("Infrastructure",[("Repositories (Doctrine ORM)","«component»"),("StripeGateway (Payment)","«component»"),("Mailer (SMTP)","«component»"),("Bitacora (Audit)","«component»")]),
        ("Database",      [("PostgreSQL (Neon)","«database»")]),
    ]
    tmp=Image.new("RGB",(10,10)); md=ImageDraw.Draw(tmp)
    LH=int(118*S); TOP=int(60*S); MARGIN=int(45*S); GAPX=int(26*S)
    # ancho por componente
    def cw(name):
        return max(int(200*S), U.tw(md,name,U.F_TXB)+int(50*S))
    W=int(1620*S)
    layouts=[]
    y=TOP
    for lname,comps in layers:
        total=sum(cw(c[0]) for c in comps)+GAPX*(len(comps)-1)
        x0=(W-total)/2
        row=[]
        x=x0
        for name,st in comps:
            w=cw(name); row.append((x,name,st,w)); x+=w+GAPX
        layouts.append((lname,y,row)); y+=LH
    H=int(y+int(40*S))
    img,draw=U.canvas(W,H)
    centers=[]
    for lname,yy,row in layouts:
        draw.text((MARGIN,yy+int(8*S)),lname,font=U.F_T2,fill=(40,60,95),anchor="la")
        for x,name,st,w in row:
            U.component_shape(img,draw,x,yy+int(30*S),w,int(64*S),name,stereo=st)
        centers.append((yy+int(30*S),yy+int(94*S),row))
    # dependencias entre capas (flechas «use» discontinuas hacia abajo)
    for i in range(len(centers)-1):
        _,yb,rowA=centers[i]; yt,_,rowB=centers[i+1]
        ax=W/2;
        U.dashed(draw,[(ax,yb),(ax,yt)],color=(110,110,110),w=1.3)
        U._ah_open(draw,(ax,yb),(ax,yt),color=(110,110,110))
    U.label_on(draw,(W/2,(centers[-2][1]+centers[-1][0])/2),"«JDBC/SQL»",font=U.F_ST)
    U.frame(img,draw,W,H,"component Diagrama de Componentes - SIGECUP-FICCT (Symfony 7 Hexagonal)")
    img.save(outpath); return img.size

# ---------------- PAQUETES ----------------
def render_paquetes(outpath):
    modulos = ["Auth","Usuario","Gestion","Academico","Inscripcion","Notas","Asignacion","Dashboard","Bitacora","Perfil","Home"]
    deps = [("Inscripcion","Gestion"),("Inscripcion","Academico"),("Inscripcion","Auth"),
            ("Notas","Academico"),("Notas","Inscripcion"),("Asignacion","Notas"),("Asignacion","Academico"),
            ("Dashboard","Inscripcion"),("Dashboard","Notas"),("Usuario","Auth"),("Academico","Gestion"),
            ("Inscripcion","Bitacora"),("Usuario","Bitacora"),("Perfil","Auth")]
    tmp=Image.new("RGB",(10,10)); md=ImageDraw.Draw(tmp)
    cols=4; PW=int(330*S); PH=int(150*S); GAPX=int(60*S); GAPY=int(70*S); TOP=int(80*S); MARGIN=int(50*S)
    W=int(MARGIN*2+cols*PW+(cols-1)*GAPX)
    rows=math.ceil(len(modulos)/cols)
    H=int(TOP+rows*PH+(rows-1)*GAPY+int(70*S))
    img,draw=U.canvas(W,H)
    pos={}
    for i,m in enumerate(modulos):
        r,c=divmod(i,cols)
        x=MARGIN+c*(PW+GAPX); y=TOP+r*(PH+GAPY)
        U.package_shape(img,draw,x,y,PW,PH,f"App\\{m}",["Domain","Application","Infrastructure","UI"])
        pos[m]={"l":x,"r":x+PW,"t":y,"b":y+PH,"cx":x+PW/2,"cy":y+PH/2}
    for a,b in deps:
        if a in pos and b in pos:
            p1,p2=U.clip_box(pos[a],pos[b])
            U.dashed(draw,[p1,p2],color=(120,120,150),w=1.1)
            U._ah_open(draw,p1,p2,color=(120,120,150))
    draw.text((MARGIN,TOP-int(34*S)),"Cada paquete de modulo (bounded context) contiene las 4 capas: Domain · Application · Infrastructure · UI.  Las flechas «import» muestran dependencias entre modulos.",font=U.F_SM,fill=(90,90,90),anchor="la")
    U.frame(img,draw,W,H,"package Diagrama de Paquetes - SIGECUP-FICCT (Bounded Contexts)")
    img.save(outpath); return img.size

# ---------------- MODELO CONCEPTUAL ----------------
def _cbox(img, draw, x, y, name, attrs):
    tmp=Image.new("RGB",(10,10)); md=ImageDraw.Draw(tmp)
    wln=max([U.tw(md,name,U.F_TXB)]+[U.tw(md,a,U.F_SM) for a in attrs])
    w=max(int(150*S),wln+int(28*S)); hh=int(26*S); h=hh+len(attrs)*int(16*S)+int(8*S)
    draw.rectangle([x,y,x+w,y+h],fill=(252,249,239),outline=U.BORDER,width=max(1,int(1.3*S)))
    tmp2=Image.new("RGB",(int(w),int(hh)),(252,249,239)); D.gradient_rect(tmp2,0,0,w,hh,U.CREAM_T,U.CREAM_B)
    img.paste(tmp2,(int(x),int(y))); draw.rectangle([x,y,x+w,y+h],outline=U.BORDER,width=max(1,int(1.3*S)))
    draw.line([(x,y+hh),(x+w,y+hh)],fill=U.BORDER,width=max(1,int(1.1*S)))
    draw.text((x+w/2,y+int(6*S)),name,font=U.F_TXB,fill=U.TEXT,anchor="ma")
    ty=y+hh+int(4*S)
    for a in attrs:
        draw.text((x+int(8*S),ty),a,font=U.F_SM,fill=(70,70,70),anchor="la"); ty+=int(16*S)
    return {"cx":x+w/2,"cy":y+h/2,"l":x,"r":x+w,"t":y,"b":y+h,"w":w,"h":h}

def render_conceptual(outpath):
    # entidades de negocio (modelo conceptual) ubicadas en grilla
    E = {
      "Gestion":(0,0,["codigo","nombre","estado","fechas"]),
      "Carrera":(0,1,["nombre","sigla","cupos"]),
      "Persona":(1,0,["ci","nombres","apellidos","correo"]),
      "Usuario":(2,0,["email","passwordHash","activo"]),
      "Rol":(3,0,["nombre"]),
      "Permiso":(3,1,["codigo","modulo","accion"]),
      "Postulante":(1,1,["colegio","turnoPref"]),
      "Estudiante":(1,2,["codigo","estado"]),
      "Docente":(1,3,["profesion","maestria","diplomado"]),
      "Postulacion":(0,2,["tipo","estado","opcion1","opcion2"]),
      "Pago":(0,3,["monto","moneda","estado","referencia"]),
      "Admision":(0,4,["resultado","opcion","promedio"]),
      "Grupo":(2,2,["nombre","cupo"]),
      "Materia":(2,1,["nombre (Mat/Fis/Ing/Comp)"]),
      "Turno":(3,2,["nombre (M/T/N)"]),
      "Aula":(3,3,["codigo","capacidad"]),
      "Horario":(2,3,["dia","horaInicio","horaFin"]),
      "Evaluacion":(2,4,["examen","ponderacion"]),
      "Nota":(3,4,["valor 0-100"]),
      "ResultadoFinal":(1,4,["promedio","estado"]),
      "Bitacora":(4,0,["accion","entidad","datos","ip","fecha"]),
    }
    rels = [  # (a,b,carda,cardb,kind)
      ("Usuario","Rol","*","*","assoc"),("Rol","Permiso","*","*","assoc"),("Usuario","Persona","1","1","assoc"),
      ("Postulante","Persona","","","gen"),("Estudiante","Persona","","","gen"),("Docente","Persona","","","gen"),
      ("Postulante","Postulacion","1","1","assoc"),("Postulacion","Pago","1","0..1","assoc"),
      ("Postulacion","Carrera","*","1","assoc"),("Postulacion","Gestion","*","1","assoc"),
      ("Postulante","Estudiante","1","0..1","assoc"),("Estudiante","Grupo","*","1","assoc"),
      ("Grupo","Gestion","*","1","assoc"),("Grupo","Materia","1","1","assoc"),("Grupo","Turno","*","1","assoc"),
      ("Grupo","Horario","1","1","assoc"),("Grupo","Aula","*","1","assoc"),("Docente","Grupo","1","1..4","assoc"),
      ("Estudiante","Nota","1","*","assoc"),("Materia","Evaluacion","1","*","assoc"),("Evaluacion","Nota","1","*","assoc"),
      ("Estudiante","ResultadoFinal","1","1","assoc"),("ResultadoFinal","Admision","1","0..1","assoc"),
      ("Admision","Carrera","*","1","assoc"),("Bitacora","Usuario","*","1","assoc"),
    ]
    COLW=int(330*S); ROWH=int(150*S); TOP=int(60*S); MARGIN=int(45*S)
    img0,draw0=U.canvas(10,10)
    ncol=max(v[0] for v in E.values())+1; nrow=max(v[1] for v in E.values())+1
    W=int(MARGIN*2+ncol*COLW); H=int(TOP+nrow*ROWH+int(60*S))
    img,draw=U.canvas(W,H)
    boxes={}
    for name,(col,row,attrs) in E.items():
        x=MARGIN+col*COLW; y=TOP+row*ROWH
        boxes[name]=_cbox(img,draw,x,y,name,attrs)
    for a,b,ca,cb,kind in rels:
        if a not in boxes or b not in boxes: continue
        p1,p2=U.clip_box(boxes[a],boxes[b])
        if kind=="gen":
            draw.line([p1,p2],fill=(90,90,90),width=max(1,int(1.2*S)))
            U._ah_triangle(draw,p1,p2)
        else:
            draw.line([p1,p2],fill=(110,110,140),width=max(1,int(1.1*S)))
            if ca: U.label_on(draw,p1,ca)
            if cb: U.label_on(draw,p2,cb)
    U.frame(img,draw,W,H,"data Modelo Conceptual de Datos - SIGECUP-FICCT")
    img.save(outpath); return img.size

if __name__=="__main__":
    print("componentes:", render_componentes("png/COMPONENTES.png"))
    print("paquetes:", render_paquetes("png/PAQUETES.png"))
    print("conceptual:", render_conceptual("png/MODELO_CONCEPTUAL.png"))
