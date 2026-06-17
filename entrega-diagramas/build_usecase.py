# -*- coding: utf-8 -*-
"""Autoria + render de los diagramas de Contexto y Casos de Uso (general + 9 modulos)."""
import json
import render_usecase as UC

man = []

# ---------- 1. Contexto ----------
ctx = {
  "titulo": "context Diagrama de Contexto - Sistema CUP (FICCT)",
  "sistema": "Sistema Web Integrado de Gestion del CUP (SIGECUP-FICCT)",
  "actores": ["Postulante","Estudiante","Docente","Coordinador Academico","Autoridad","Administrador"],
  "externos": ["Stripe (Pasarela de Pago)","Servidor SMTP (Gmail)","Base de Datos PostgreSQL (Neon)"],
  "rel_actores": [("Postulante","se inscribe / paga"),("Estudiante","consulta notas/horario"),("Docente","registra notas"),
                  ("Coordinador Academico","gestiona el proceso"),("Autoridad","consulta reportes"),("Administrador","administra el sistema")],
  "rel_externos": [("Stripe (Pasarela de Pago)","cobra arancel (HTTPS)"),("Servidor SMTP (Gmail)","envia correos"),("Base de Datos PostgreSQL (Neon)","persiste datos")],
}
print("contexto:", UC.render_contexto(ctx, "png/UC_CONTEXTO.png")); man.append(("UC_CONTEXTO.png","Diagrama de Contexto"))

# helper
def mk(titulo, actores, casos, aso, inc=None, ext=None, gen=None, sistema=None):
    return {"titulo":titulo,"sistema":sistema or "Sistema Web Integrado de Gestion del CUP - FICCT",
            "actores":actores,"casos":casos,"asociaciones":aso,
            "includes":inc or [],"extends":ext or [],"generaliza":gen or []}

# ---------- 2. Casos de Uso GENERAL ----------
A=lambda n,s:{"nombre":n,"side":s}
def C(i,n,col): return {"id":i,"nombre":n,"col":col}
general = mk(
  "uc Diagrama General de Casos de Uso - SIGECUP-FICCT",
  [A("Postulante","L"),A("Estudiante","L"),A("Docente","L"),A("Coordinador Academico","R"),A("Autoridad","R"),A("Administrador","R")],
  [
    C("CU-04","Gestionar Postulacion",0), C("CU-06","Gestionar Pago",0), C("CU-14","Consultar Resultados de Admision",0),
    C("CU-11","Consultar Informacion Academica",0),
    C("CU-05","Validar Documentacion",1), C("CU-09","Planificacion Academica",1), C("CU-13","Ejecutar Admision Final",1),
    C("CU-08","Gestionar Docentes",1), C("CU-07","Gestionar Postulantes",1),
    C("CU-10","Gestionar Evaluaciones (Notas)",2), C("CU-12","Consultar Carga Academica",2),
    C("CU-15","Reportes y Dashboard",2), C("CU-03","Gestionar Estructura Academica",2),
    C("CU-02","Gestionar Usuarios y Seguridad",3), C("CU-16","Auditoria (Bitacora)",3), C("CU-01","Autenticarse",3),
  ],
  [("Postulante","CU-04"),("Postulante","CU-06"),("Postulante","CU-14"),("Estudiante","CU-11"),
   ("Docente","CU-10"),("Docente","CU-12"),
   ("Coordinador Academico","CU-05"),("Coordinador Academico","CU-09"),("Coordinador Academico","CU-13"),
   ("Coordinador Academico","CU-07"),("Coordinador Academico","CU-08"),("Coordinador Academico","CU-15"),
   ("Autoridad","CU-15"),
   ("Administrador","CU-02"),("Administrador","CU-03"),("Administrador","CU-16"),("Administrador","CU-08")],
  inc=[("CU-06","CU-04"),("CU-13","CU-10"),("CU-09","CU-05"),("CU-14","CU-13")],
  ext=[("CU-05","CU-06")],
)
# todos incluyen autenticacion
for cu in ["CU-02","CU-03","CU-04","CU-05","CU-06","CU-07","CU-08","CU-09","CU-10","CU-11","CU-12","CU-13","CU-14","CU-15","CU-16"]:
    general["includes"].append((cu,"CU-01"))
print("general:", UC.render_usecase(general,"png/UC_GENERAL.png")); man.append(("UC_GENERAL.png","Casos de Uso General"))

# ---------- 3. Por modulo ----------
modulos = []

modulos.append(("UC_M_AUTENTICACION","Autenticacion y Seguridad", mk(
  "uc Modulo Autenticacion y Seguridad",
  [A("Usuario","L"),A("Administrador","R")],
  [C("u1","Iniciar Sesion",0),C("u2","Cerrar Sesion",0),C("u3","Recuperar Contrasena",0),
   C("u4","Desbloquear Cuenta",0),C("u5","Registrarse",0),C("u6","Gestionar Perfil",1),C("u7","Cambiar Contrasena",1)],
  [("Usuario","u1"),("Usuario","u2"),("Usuario","u3"),("Usuario","u4"),("Usuario","u5"),("Usuario","u6"),("Usuario","u7"),("Administrador","u6")],
  inc=[("u1","u_val") ] if False else [], ext=[("u1","u4")],
)))
modulos[-1][2]["includes"]=[("u3","u8")]
modulos[-1][2]["casos"].append(C("u8","Enviar Correo de Recuperacion",1))

modulos.append(("UC_M_POSTULACIONES","Postulaciones", mk(
  "uc Modulo Gestion de Postulaciones",
  [A("Postulante","L"),A("Coordinador Academico","R")],
  [C("p1","Registrar Preinscripcion",0),C("p2","Subir Documentos",0),C("p3","Editar Borrador",0),
   C("p4","Presentar Postulacion",0),C("p5","Consultar Postulacion",0),C("p6","Solicitar Anulacion",0),
   C("p7","Listar / Buscar Postulantes",1),C("p8","Ver Expediente",1),C("p9","Anular / Eliminar",1)],
  [("Postulante","p1"),("Postulante","p2"),("Postulante","p3"),("Postulante","p4"),("Postulante","p5"),("Postulante","p6"),
   ("Coordinador Academico","p7"),("Coordinador Academico","p8"),("Coordinador Academico","p9")],
  inc=[("p4","p_val")], ext=[("p1","p2"),("p4","p3")],
)))
modulos[-1][2]["casos"].append(C("p_val","Validar Datos (CI/correo unicos)",0))
modulos[-1][2]["includes"]=[("p4","p_val")]

modulos.append(("UC_M_PAGOS","Pagos", mk(
  "uc Modulo Gestion de Pagos",
  [A("Postulante","L"),A("Coordinador Academico","R")],
  [C("g1","Iniciar Pago",0),C("g2","Pagar en Pasarela (Stripe)",0),C("g3","Confirmar Pago (webhook)",0),
   C("g4","Crear Sesion Checkout",1),C("g5","Conciliar Pagos",1),C("g6","Registrar Pago Manual",1),C("g7","Editar Estado de Pago",1)],
  [("Postulante","g1"),("Postulante","g2"),("Coordinador Academico","g5"),("Coordinador Academico","g6"),("Coordinador Academico","g7")],
  inc=[("g1","g4"),("g2","g3")], ext=[("g2","g_cancel")],
)))
modulos[-1][2]["casos"].append(C("g_cancel","Cancelar Pago",0))

modulos.append(("UC_M_DOCENTES","Docentes", mk(
  "uc Modulo Gestion de Docentes",
  [A("Coordinador Academico","L"),A("Administrador","R")],
  [C("d1","Listar Postulaciones Docente",0),C("d2","Validar Titulos de Postgrado",0),C("d3","Agendar Entrevista",0),
   C("d4","Aprobar Contratacion",0),C("d5","Rechazar Postulacion",0),C("d6","Asignar Rol Docente",1)],
  [("Coordinador Academico","d1"),("Coordinador Academico","d2"),("Coordinador Academico","d3"),
   ("Coordinador Academico","d4"),("Coordinador Academico","d5"),("Administrador","d4")],
  inc=[("d4","d6")], ext=[("d2","d5")],
)))

modulos.append(("UC_M_ESTUDIANTES","Estudiantes", mk(
  "uc Modulo Gestion de Estudiantes",
  [A("Coordinador Academico","L"),A("Estudiante","R")],
  [C("e1","Convertir Postulante en Estudiante",0),C("e2","Listar Estudiantes por Grupo",0),
   C("e3","Dar de Baja / Reasignar",0),C("e4","Consultar Horario",1),C("e5","Consultar Boletin",1)],
  [("Coordinador Academico","e1"),("Coordinador Academico","e2"),("Coordinador Academico","e3"),
   ("Estudiante","e4"),("Estudiante","e5")],
  inc=[("e1","e6")],
)))
modulos[-1][2]["casos"].append(C("e6","Generar Codigo de Estudiante",0))

modulos.append(("UC_M_ASIGNACIONES","Asignaciones", mk(
  "uc Modulo Gestion de Asignaciones",
  [A("Coordinador Academico","L")],
  [C("s1","Generar Grupos (CEIL total/cupo)",0),C("s2","Distribuir Estudiantes por Turno",0),
   C("s3","Asignar Docente a Grupo",0),C("s4","Quitar Docente",0),C("s5","Asignar Estudiante a Grupo",1),
   C("s6","Quitar Estudiante",1),C("s7","Programar Horario y Aula",1)],
  [("Coordinador Academico","s1"),("Coordinador Academico","s2"),("Coordinador Academico","s3"),
   ("Coordinador Academico","s4"),("Coordinador Academico","s5"),("Coordinador Academico","s6"),("Coordinador Academico","s7")],
  inc=[("s3","s8"),("s7","s9")],
)))
modulos[-1][2]["casos"]+=[C("s8","Validar Requisitos y Limite (max 4)",0),C("s9","Verificar Solape de Aula/Horario",1)]

modulos.append(("UC_M_NOTAS","Notas / Evaluaciones", mk(
  "uc Modulo Gestion de Evaluaciones y Notas",
  [A("Docente","L"),A("Estudiante","R")],
  [C("n1","Ver Mis Materias / Grupos",0),C("n2","Abrir Planilla",0),C("n3","Registrar Notas (0-100)",0),
   C("n4","Importar Notas (Excel)",0),C("n5","Exportar Planilla",0),C("n6","Consultar Boletin",1)],
  [("Docente","n1"),("Docente","n2"),("Docente","n3"),("Docente","n4"),("Docente","n5"),("Estudiante","n6")],
  inc=[("n3","n7")], ext=[("n3","n4")],
)))
modulos[-1][2]["casos"].append(C("n7","Calcular Promedio y Estado",1))

modulos.append(("UC_M_ADMISIONES","Admisiones", mk(
  "uc Modulo Gestion de Admisiones",
  [A("Coordinador Academico","L"),A("Postulante","R")],
  [C("a1","Ejecutar Admision Final",0),C("a2","Verificar Promedios",0),C("a3","Verificar Cupos por Carrera",0),
   C("a4","Consultar Resultado de Admision",1),C("a5","Descargar Constancia (PDF)",1),C("a6","Notificar por Correo",0)],
  [("Coordinador Academico","a1"),("Postulante","a4"),("Postulante","a5")],
  inc=[("a1","a2"),("a1","a3"),("a1","a6")], ext=[("a4","a5")],
)))

modulos.append(("UC_M_REPORTES","Reportes / Dashboard", mk(
  "uc Modulo Reportes y Dashboard",
  [A("Autoridad","L"),A("Coordinador Academico","R")],
  [C("r1","Ver Dashboard (KPIs)",0),C("r2","Generar Reporte",0),C("r3","Filtrar Reporte",0),
   C("r4","Exportar a PDF",1),C("r5","Exportar a Excel",1),C("r6","Consultar via Asistente",1)],
  [("Autoridad","r1"),("Autoridad","r2"),("Autoridad","r4"),("Coordinador Academico","r1"),
   ("Coordinador Academico","r2"),("Coordinador Academico","r3"),("Coordinador Academico","r5")],
  inc=[("r2","r3")], ext=[("r2","r4"),("r2","r5")],
)))

for key,nombre,spec in modulos:
    print(key, UC.render_usecase(spec, f"png/{key}.png"))
    man.append((f"{key}.png", f"Casos de Uso - {nombre}"))

json.dump(man, open("data/manifest_uc.json","w",encoding="utf-8"), ensure_ascii=False, indent=2)
print("TOTAL UC:", len(man))
