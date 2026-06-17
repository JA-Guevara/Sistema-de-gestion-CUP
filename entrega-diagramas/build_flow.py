# -*- coding: utf-8 -*-
"""Autoria + render de Actividades (5) y Estados (2)."""
import json
import render_flow as RF

man = {"actividades": [], "estados": []}

def N(id,kind,text,lane,row): return {"id":id,"kind":kind,"text":text,"lane":lane,"row":row}
def E(a,b,label=None): return {"a":a,"b":b,"label":label}

# ---------- ACTIVIDADES ----------
act_insc = {
 "titulo":"act Proceso de Inscripcion (Preinscripcion)",
 "lanes":["Postulante","Sistema (CUP)","Coordinador Academico"],
 "nodes":[
   N("i","initial","","Postulante",0),
   N("a1","action","Completar formulario: datos, carrera 1ra/2da y turno","Postulante",1),
   N("a2","action","Adjuntar requisitos documentales","Postulante",2),
   N("a3","action","Presentar preinscripcion","Postulante",3),
   N("a4","action","Validar CI y correo unicos / gestion abierta","Sistema (CUP)",4),
   N("d1","decision","Datos validos?","Sistema (CUP)",5),
   N("a5","action","Guardar Inscripcion (estado PRESENTADA)","Sistema (CUP)",6),
   N("a6","action","Agendar revision automatica de documentos","Sistema (CUP)",7),
   N("a7","action","Recibir expediente para validacion","Coordinador Academico",8),
   N("f","final","","Coordinador Academico",9),
 ],
 "edges":[E("i","a1"),E("a1","a2"),E("a2","a3"),E("a3","a4"),E("a4","d1"),
          E("d1","a1","No (corregir)"),E("d1","a5","Si"),E("a5","a6"),E("a6","a7"),E("a7","f")],
}
act_pago = {
 "titulo":"act Proceso de Pago del Arancel",
 "lanes":["Postulante","Sistema (CUP)","Stripe (Pasarela)"],
 "nodes":[
   N("i","initial","","Postulante",0),
   N("a1","action","Solicitar pago del arancel","Postulante",1),
   N("d1","decision","Documentacion VALIDADA?","Sistema (CUP)",2),
   N("a2","action","Crear Pago PENDIENTE y sesion Checkout","Sistema (CUP)",3),
   N("a3","action","Pagar con tarjeta","Stripe (Pasarela)",4),
   N("a4","action","Notificar webhook (payment_status=paid)","Stripe (Pasarela)",5),
   N("a5","action","Confirmar pago e inscripcion (asignar rol)","Sistema (CUP)",6),
   N("fno","final","","Sistema (CUP)",3),
   N("f","final","","Sistema (CUP)",7),
 ],
 "edges":[E("i","a1"),E("a1","d1"),E("d1","fno","No (no habilitado)"),E("d1","a2","Si"),
          E("a2","a3"),E("a3","a4"),E("a4","a5"),E("a5","f")],
}
act_adm = {
 "titulo":"act Proceso de Admision Final",
 "lanes":["Coordinador Academico","Sistema (CUP)"],
 "nodes":[
   N("i","initial","","Coordinador Academico",0),
   N("a1","action","Ejecutar algoritmo de Admision Final","Coordinador Academico",1),
   N("a2","action","Filtrar aprobados (promedio >= 60)","Sistema (CUP)",2),
   N("a3","action","Ordenar por merito (promedio desc)","Sistema (CUP)",3),
   N("d1","decision","Cupo en 1ra opcion?","Sistema (CUP)",4),
   N("a4","action","Admitir en 1ra opcion","Sistema (CUP)",5),
   N("d2","decision","Cupo en 2da opcion?","Coordinador Academico",5),
   N("a5","action","Admitir en 2da opcion","Coordinador Academico",6),
   N("a6","action","Mover a LISTA_ESPERA","Coordinador Academico",7),
   N("a7","action","Descontar cupo y notificar por correo","Sistema (CUP)",8),
   N("f","final","","Sistema (CUP)",9),
 ],
 "edges":[E("i","a1"),E("a1","a2"),E("a2","a3"),E("a3","d1"),
          E("d1","a4","Si"),E("d1","d2","No"),E("d2","a5","Si"),E("d2","a6","No"),
          E("a4","a7"),E("a5","a7"),E("a6","a7"),E("a7","f")],
}
act_asig = {
 "titulo":"act Proceso de Asignacion Academica",
 "lanes":["Coordinador Academico","Sistema (CUP)"],
 "nodes":[
   N("i","initial","","Coordinador Academico",0),
   N("a1","action","Iniciar planificacion academica","Coordinador Academico",1),
   N("a2","action","Calcular grupos CEIL(total / cupo)","Sistema (CUP)",2),
   N("a3","action","Distribuir estudiantes por turno","Sistema (CUP)",3),
   N("a4","action","Asignar docente a grupo","Coordinador Academico",4),
   N("d1","decision","Cumple requisitos y <= 4 grupos?","Sistema (CUP)",5),
   N("a5","action","Programar horario y aula","Coordinador Academico",6),
   N("d2","decision","Solape de aula/horario?","Sistema (CUP)",7),
   N("a6","action","Guardar planificacion","Sistema (CUP)",8),
   N("f","final","","Sistema (CUP)",9),
 ],
 "edges":[E("i","a1"),E("a1","a2"),E("a2","a3"),E("a3","a4"),E("a4","d1"),
          E("d1","a4","No (alertar)"),E("d1","a5","Si"),E("a5","d2"),
          E("d2","a5","Si (corregir)"),E("d2","a6","No"),E("a6","f")],
}
act_eval = {
 "titulo":"act Proceso de Evaluacion (Notas)",
 "lanes":["Docente","Sistema (CUP)"],
 "nodes":[
   N("i","initial","","Docente",0),
   N("a1","action","Abrir planilla del grupo/materia","Docente",1),
   N("a2","action","Registrar notas de 3 examenes (0-100)","Docente",2),
   N("d1","decision","Notas en rango 0-100?","Sistema (CUP)",3),
   N("a3","action","Guardar notas (auditado)","Sistema (CUP)",4),
   N("a4","action","Calcular promedio ponderado","Sistema (CUP)",5),
   N("d2","decision","Promedio >= 60?","Sistema (CUP)",6),
   N("a5","action","Marcar APROBADO","Docente",7),
   N("a6","action","Marcar REPROBADO","Sistema (CUP)",7),
   N("f","final","","Sistema (CUP)",8),
 ],
 "edges":[E("i","a1"),E("a1","a2"),E("a2","d1"),E("d1","a2","No (corregir)"),E("d1","a3","Si"),
          E("a3","a4"),E("a4","d2"),E("d2","a5","Si"),E("d2","a6","No"),E("a5","f"),E("a6","f")],
}

acts=[("ACT_INSCRIPCION","Proceso de Inscripcion",act_insc),("ACT_PAGO","Proceso de Pago",act_pago),
      ("ACT_ADMISION","Proceso de Admision",act_adm),("ACT_ASIGNACION","Proceso de Asignacion Academica",act_asig),
      ("ACT_EVALUACION","Proceso de Evaluacion",act_eval)]
for key,nombre,spec in acts:
    print(key, RF.render_activity(spec, f"png/{key}.png"))
    man["actividades"].append((f"{key}.png",nombre))

# ---------- ESTADOS ----------
def ST(id,name,col,row,body=None): return {"id":id,"name":name,"col":col,"row":row,"body":body}
def T(a,b,label=None): return {"a":a,"b":b,"label":label}

est_post = {
 "titulo":"stm Estados de la Postulacion (Inscripcion)",
 "initial":"borrador","finals":["confirmada","rechazada","anulada"],
 "states":[
   ST("borrador","BORRADOR",0,0,["(Registrada: en edicion)"]),
   ST("presentada","PRESENTADA",0,1,["(Pendiente de revision)"]),
   ST("validada","VALIDADA",0,2,["(Documentacion aprobada;","pago habilitado)"]),
   ST("confirmada","CONFIRMADA",0,3,["(Pago validado / Admitida;","rol asignado)"]),
   ST("rechazada","RECHAZADA",1,1,["(Documentacion observada)"]),
   ST("anulada","ANULADA",1,3,["(Anulada por el postulante)"]),
 ],
 "transitions":[
   T("borrador","presentada","presentar()"),
   T("presentada","validada","validar() [acta conforme]"),
   T("presentada","rechazada","rechazar() [observada]"),
   T("validada","confirmada","confirmarPago() / confirmar()"),
   T("validada","anulada","anular()"),
 ],
}
est_est = {
 "titulo":"stm Estados del Estudiante",
 "initial":"preinscrito","finals":["admitido","noadmitido"],
 "states":[
   ST("preinscrito","Preinscrito",0,0,["(postulacion presentada)"]),
   ST("inscrito","Inscrito",0,1,["(pago confirmado;","convertido a estudiante)"]),
   ST("asignado","Asignado",0,2,["(asignado a grupo,","horario y aula)"]),
   ST("evaluado","Evaluado",0,3,["(notas registradas;","promedio calculado)"]),
   ST("admitido","Admitido",0,4,["(cupo en 1ra/2da opcion)"]),
   ST("noadmitido","No Admitido",1,4,["(sin cupo / reprobado;","lista de espera)"]),
 ],
 "transitions":[
   T("preinscrito","inscrito","pago confirmado"),
   T("inscrito","asignado","asignar a grupo"),
   T("asignado","evaluado","registrar notas"),
   T("evaluado","admitido","promedio>=60 y hay cupo"),
   T("evaluado","noadmitido","sin cupo / reprobado"),
 ],
}
for key,nombre,spec in [("EST_POSTULACION","Estados de la Postulacion",est_post),("EST_ESTUDIANTE","Estados del Estudiante",est_est)]:
    print(key, RF.render_state(spec, f"png/{key}.png"))
    man["estados"].append((f"{key}.png",nombre))

json.dump(man, open("data/manifest_flow.json","w",encoding="utf-8"), ensure_ascii=False, indent=2)
print("OK actividades+estados")
