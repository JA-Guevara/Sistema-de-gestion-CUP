const fs = require("fs");
const {
  Document, Packer, Paragraph, TextRun, ImageRun, AlignmentType, HeadingLevel,
  PageBreak, BorderStyle, Footer, PageNumber, Table, TableRow, TableCell,
  WidthType, ShadingType, TableOfContents, VerticalAlign, LevelFormat,
} = require("docx");

const M = JSON.parse(fs.readFileSync("data/master_manifest.json", "utf-8"));
const BLUE = "1F4E79", GRAY = "595959";

// ---------------- helpers ----------------
function img(it) {
  return new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 100, after: 80 },
    children: [new ImageRun({ type: "png", data: fs.readFileSync(it.img),
      transformation: { width: it.target_w, height: it.target_h },
      altText: { title: it.titulo, description: it.titulo, name: it.img } })] });
}
function h2(t) { return new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, children: [new TextRun(t)] }); }
function h1(t) { return new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun(t)] }); }
function rule() { return new Paragraph({ border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: BLUE, space: 1 } }, spacing: { after: 80 }, children: [new TextRun("")] }); }
function field(label, text) {
  return new Paragraph({ spacing: { after: 60 }, children: [
    new TextRun({ text: label + ": ", bold: true, size: 19, color: BLUE }),
    new TextRun({ text: text, size: 19, color: "333333" })] });
}
function bullets(label, items) {
  const out = [new Paragraph({ spacing: { after: 20 }, children: [new TextRun({ text: label + ":", bold: true, size: 19, color: BLUE })] })];
  for (const it of items) out.push(new Paragraph({ numbering: { reference: "bul", level: 0 }, spacing: { after: 10 }, children: [new TextRun({ text: it, size: 18, color: "333333" })] }));
  return out;
}
function para(text, size=19) { return new Paragraph({ spacing: { after: 90 }, children: [new TextRun({ text, size, color: "333333" })] }); }

// bloque completo: {objetivo, descripcion, elementos[], relaciones[], justificacion}
function docBlock(d) {
  const out = [];
  if (d.objetivo) out.push(field("Objetivo", d.objetivo));
  if (d.descripcion) out.push(field("Descripcion", d.descripcion));
  if (d.elementos) out.push(...bullets("Elementos", d.elementos));
  if (d.relaciones) out.push(...bullets("Relaciones", d.relaciones));
  if (d.justificacion) out.push(field("Justificacion arquitectonica", d.justificacion));
  return out;
}

// ================= CONTENIDO DOCUMENTAL =================
const DOC = {};
DOC.contexto = {
  objetivo: "Delimitar la frontera del sistema, identificando los actores humanos y los sistemas externos con los que interactua el CUP.",
  descripcion: "Vista de mas alto nivel: el sistema se representa como una caja negra (un unico nodo) rodeada por los seis actores del negocio y los tres sistemas externos con los que se integra.",
  elementos: ["Sistema central: Sistema Web Integrado de Gestion del CUP (SIGECUP-FICCT).",
    "Actores: Postulante, Estudiante, Docente, Coordinador Academico, Autoridad, Administrador.",
    "Sistemas externos: Stripe (pasarela de pago), Servidor SMTP Gmail (correo), PostgreSQL/Neon (persistencia)."],
  relaciones: ["Cada actor mantiene un flujo de interaccion con el sistema (inscribirse/pagar, registrar notas, gestionar el proceso, consultar reportes, administrar).",
    "El sistema consume servicios externos mediante HTTPS (Stripe), SMTP (correo) y TCP/IP+SSL (base de datos)."],
  justificacion: "Establece el alcance y las dependencias externas antes del analisis detallado; es la base para el diagrama de despliegue y de componentes.",
};
DOC.uc_general = {
  objetivo: "Ofrecer una vision integral de la funcionalidad del sistema relacionando los seis actores con los 16 casos de uso.",
  descripcion: "Diagrama de casos de uso UML con la frontera del sistema; agrupa los 16 CU por area funcional y muestra las relaciones include, extend y la dependencia comun de autenticacion.",
  elementos: ["6 actores (Postulante, Estudiante, Docente, Coordinador, Autoridad, Administrador).",
    "16 casos de uso (CU-01 a CU-16) dentro de la frontera del sistema."],
  relaciones: ["Asociaciones actor-caso de uso segun el rol.",
    "«include»: todos los CU operativos incluyen CU-01 Autenticarse; CU-06 incluye CU-04; CU-13 incluye CU-10; CU-09 incluye CU-05; CU-14 incluye CU-13.",
    "«extend»: CU-05 (Validacion) extiende a CU-06 (habilita el pago)."],
  justificacion: "Es el artefacto guia del Proceso Unificado (dirigido por casos de uso); de el derivan los diagramas BCE y de secuencia.",
};
const UCMOD = {
  "Autenticacion": { objetivo: "Modelar el acceso seguro y la gestion de cuenta.", elementos: ["Actores: Usuario (generaliza a todos los roles), Administrador.", "CU: Iniciar/Cerrar Sesion, Recuperar Contrasena, Desbloquear Cuenta, Registrarse, Gestionar Perfil, Cambiar Contrasena."], relaciones: ["«include»: Recuperar Contrasena incluye Enviar Correo de Recuperacion.", "«extend»: Iniciar Sesion se extiende con Desbloquear Cuenta (tras N intentos fallidos)."], justificacion: "Aisla el control de acceso (RBAC, sesion unica, bloqueo) como modulo transversal de seguridad." },
  "Postulaciones": { objetivo: "Modelar la preinscripcion del postulante y su seguimiento administrativo.", elementos: ["Actores: Postulante, Coordinador Academico.", "CU: Registrar Preinscripcion, Subir Documentos, Editar Borrador, Presentar, Consultar, Solicitar Anulacion, Listar/Buscar, Ver Expediente, Anular/Eliminar."], relaciones: ["«include»: Presentar Postulacion incluye Validar Datos (CI/correo unicos).", "«extend»: Registrar Preinscripcion se extiende con Subir Documentos; Presentar con Editar Borrador."], justificacion: "Separa el flujo del postulante (autoservicio) del panel administrativo del coordinador." },
  "Pagos": { objetivo: "Modelar el cobro del arancel mediante pasarela y su conciliacion.", elementos: ["Actores: Postulante, Coordinador.", "CU: Iniciar Pago, Pagar en Pasarela, Confirmar Pago (webhook), Crear Sesion Checkout, Conciliar Pagos, Registrar Pago Manual, Editar Estado."], relaciones: ["«include»: Iniciar Pago incluye Crear Sesion Checkout; Pagar incluye Confirmar Pago.", "«extend»: Pagar se extiende con Cancelar Pago."], justificacion: "Refleja la integracion real con Stripe (confirmacion por webhook autoritativo) y el respaldo manual del coordinador." },
  "Docentes": { objetivo: "Modelar la seleccion y contratacion del cuerpo docente.", elementos: ["Actores: Coordinador, Administrador.", "CU: Listar Postulaciones Docente, Validar Titulos, Agendar Entrevista, Aprobar Contratacion, Rechazar, Asignar Rol Docente."], relaciones: ["«include»: Aprobar Contratacion incluye Asignar Rol Docente.", "«extend»: Validar Titulos se extiende con Rechazar Postulacion."], justificacion: "El docente se materializa como una postulacion (tipo DOCENTE) que, al confirmarse, asigna el rol; valida requisitos de postgrado." },
  "Estudiantes": { objetivo: "Modelar la conversion del postulante en estudiante y sus consultas.", elementos: ["Actores: Coordinador, Estudiante.", "CU: Convertir Postulante en Estudiante, Generar Codigo, Listar por Grupo, Dar de Baja/Reasignar, Consultar Horario, Consultar Boletin."], relaciones: ["«include»: Convertir Postulante incluye Generar Codigo de Estudiante."], justificacion: "El estudiante surge de una inscripcion confirmada; concentra las consultas de transparencia academica." },
  "Asignaciones": { objetivo: "Modelar la planificacion academica (grupos, docentes, horarios).", elementos: ["Actor: Coordinador Academico.", "CU: Generar Grupos, Distribuir Estudiantes, Asignar/Quitar Docente, Asignar/Quitar Estudiante, Programar Horario, Validar Requisitos, Verificar Solapes."], relaciones: ["«include»: Asignar Docente incluye Validar Requisitos y Limite (max 4); Programar Horario incluye Verificar Solape de Aula/Horario."], justificacion: "Es el nucleo logistico; concentra las reglas de negocio criticas (CEIL(total/cupo), limite de 4 grupos, no solape)." },
  "Notas": { objetivo: "Modelar el registro de calificaciones y su consulta.", elementos: ["Actores: Docente, Estudiante.", "CU: Ver Mis Materias, Abrir Planilla, Registrar Notas (0-100), Importar Notas, Exportar Planilla, Consultar Boletin, Calcular Promedio."], relaciones: ["«include»: Registrar Notas incluye Calcular Promedio y Estado.", "«extend»: Registrar Notas se extiende con Importar Notas (Excel)."], justificacion: "Automatiza el calculo del promedio ponderado y el estado APROBADO/REPROBADO (insumo de la admision)." },
  "Admisiones": { objetivo: "Modelar la admision final por merito y la consulta de resultados.", elementos: ["Actores: Coordinador, Postulante.", "CU: Ejecutar Admision Final, Verificar Promedios, Verificar Cupos, Notificar, Consultar Resultado, Descargar Constancia."], relaciones: ["«include»: Ejecutar Admision incluye Verificar Promedios, Verificar Cupos y Notificar por Correo.", "«extend»: Consultar Resultado se extiende con Descargar Constancia (PDF) si fue admitido."], justificacion: "Encapsula el algoritmo de prelacion 1ra/2da opcion y lista de espera segun cupos." },
  "Reportes": { objetivo: "Modelar la inteligencia de negocio (dashboard y reportes).", elementos: ["Actores: Autoridad, Coordinador.", "CU: Ver Dashboard, Generar Reporte, Filtrar, Exportar PDF, Exportar Excel, Consultar via Asistente."], relaciones: ["«include»: Generar Reporte incluye Filtrar.", "«extend»: Generar Reporte se extiende con Exportar a PDF y Exportar a Excel."], justificacion: "Provee soporte a la toma de decisiones (solo lectura) sin afectar el flujo transaccional." },
};
const ACTDOC = {
  "Inscripcion": { objetivo: "Describir el flujo de preinscripcion del postulante hasta dejar el expediente PRESENTADO.", elementos: ["Particiones (lanes): Postulante, Sistema, Coordinador.", "Acciones: completar formulario, adjuntar requisitos, presentar, validar CI/correo, guardar, agendar revision.", "Decision: Datos validos?"], relaciones: ["Flujo de control con un punto de decision que reencamina a correccion del formulario si la validacion falla."], justificacion: "Modela el subproceso de captura de datos y carrera con sus validaciones de unicidad." },
  "Pago": { objetivo: "Describir el flujo de pago del arancel via pasarela y su confirmacion.", elementos: ["Lanes: Postulante, Sistema, Stripe.", "Acciones: solicitar pago, crear sesion, pagar, webhook, confirmar.", "Decision: Documentacion VALIDADA?"], relaciones: ["Si la documentacion no esta validada el flujo termina; si lo esta, continua a la pasarela y vuelve por el webhook."], justificacion: "Refleja la confirmacion asincrona (webhook) como punto autoritativo del pago." },
  "Admision": { objetivo: "Describir el algoritmo de admision final por merito academico.", elementos: ["Lanes: Coordinador, Sistema.", "Acciones: ejecutar, filtrar aprobados, ordenar, admitir 1ra/2da, lista de espera, descontar cupo, notificar.", "Decisiones: cupo en 1ra opcion?, cupo en 2da opcion?"], relaciones: ["Estructura de decisiones anidadas (1ra -> 2da -> lista de espera) con confluencia en el descuento de cupo y la notificacion."], justificacion: "Formaliza la regla de prelacion por promedio y disponibilidad de cupos." },
  "Asignacion": { objetivo: "Describir la planificacion academica (grupos, docentes, horarios).", elementos: ["Lanes: Coordinador, Sistema.", "Acciones: calcular grupos, distribuir, asignar docente, programar horario, guardar.", "Decisiones: cumple requisitos y <=4 grupos?, solape de aula/horario?"], relaciones: ["Dos validaciones con reencaminamiento (alertar / corregir) antes de persistir la planificacion."], justificacion: "Hace explicitas las reglas de carga docente y de no solapamiento de recursos." },
  "Evaluacion": { objetivo: "Describir el registro de notas y el calculo de promedio/estado.", elementos: ["Lanes: Docente, Sistema.", "Acciones: abrir planilla, registrar notas, guardar, calcular promedio, marcar APROBADO/REPROBADO.", "Decisiones: notas 0-100?, promedio >= 60?"], relaciones: ["Validacion de rango y bifurcacion final segun el promedio ponderado."], justificacion: "Automatiza la determinacion del estado academico, insumo del proceso de admision." },
};
const ESTDOC = {
  "Postulacion": { objetivo: "Modelar el ciclo de vida de una postulacion (entidad Inscripcion).", elementos: ["Estados reales: BORRADOR, PRESENTADA, VALIDADA, CONFIRMADA, RECHAZADA, ANULADA.", "Pseudoestados: inicial y finales."], relaciones: ["Transiciones: presentar(), validar() [acta conforme], confirmarPago()/confirmar(), rechazar(), anular()."], justificacion: "Refleja con exactitud la maquina de estados implementada en EstadoInscripcion y los metodos de la entidad Inscripcion." },
  "Estudiante": { objetivo: "Modelar el ciclo de vida del estudiante dentro del CUP.", elementos: ["Estados: Preinscrito, Inscrito, Asignado, Evaluado, Admitido, No Admitido.", "Pseudoestados: inicial y finales."], relaciones: ["Transiciones por eventos: pago confirmado, asignacion a grupo, registro de notas, resultado de admision."], justificacion: "Da trazabilidad del recorrido academico del aspirante desde la preinscripcion hasta la admision." },
};
DOC.componentes = {
  objetivo: "Mostrar la organizacion del software en componentes y sus dependencias, segun la arquitectura hexagonal sobre Symfony 7.",
  descripcion: "Componentes agrupados por capa: Frontend, UI (Controllers + Security), Application Services, Domain, Infrastructure (Repositories, Payment, Mailer, Audit) y Database.",
  elementos: ["Frontend: Twig + Bootstrap, Stimulus/Chart.js.", "UI: Controllers por modulo, CsrfManager/SessionGuard/PermissionGuard.",
    "Application: Casos de Uso, DTOs.", "Domain: Entidades, Catalogos, Policies.",
    "Infrastructure: Repositorios Doctrine, StripeGateway, Mailer, Bitacora.", "Database: PostgreSQL (Neon)."],
  relaciones: ["Dependencias dirigidas hacia el dominio (regla de dependencia hexagonal): UI -> Application -> Domain; Infrastructure -> Domain; Repositories -> Database (SQL)."],
  justificacion: "Evidencia el desacople: el dominio no depende de detalles tecnicos; la infraestructura implementa los puertos.",
};
DOC.despliegue = {
  objetivo: "Mostrar la disposicion fisica real de los nodos de ejecucion y sus protocolos.",
  descripcion: "Dispositivos cliente acceden por HTTPS a la plataforma serverless Vercel (PHP 8 + Symfony 7), que persiste en Neon PostgreSQL e integra Stripe y SMTP.",
  elementos: ["«device» Cliente Web (navegadores).", "«executionEnvironment» Vercel (vercel-php, Symfony 7, Twig, api/index.php).",
    "«database» Neon PostgreSQL (AWS).", "«server» Stripe API y «server» Google SMTP."],
  relaciones: ["Cliente <-> Vercel (HTTPS/TLS); Vercel <-> Neon (TCP/IP+SSL, Doctrine); Vercel <-> Stripe (REST + webhook); Vercel -> SMTP (587)."],
  justificacion: "Refleja la infraestructura real del repositorio (Vercel + Neon), no la planeada (Upsun); util para operacion y seguridad.",
};
DOC.paquetes = {
  objetivo: "Organizar el codigo por modulos (bounded contexts) y mostrar sus dependencias.",
  descripcion: "Cada modulo es un paquete que internamente contiene las cuatro capas Domain/Application/Infrastructure/UI; se muestran las dependencias «import» entre modulos.",
  elementos: ["11 paquetes: Auth, Usuario, Gestion, Academico, Inscripcion, Notas, Asignacion, Dashboard, Bitacora, Perfil, Home."],
  relaciones: ["Dependencias clave: Inscripcion -> Gestion/Academico/Auth/Bitacora; Notas -> Academico/Inscripcion; Asignacion -> Notas/Academico; Dashboard -> Inscripcion/Notas; Usuario -> Auth/Bitacora."],
  justificacion: "Hace visible el Diseno Orientado a Dominio (DDD) y el bajo acoplamiento entre contextos.",
};
DOC.clases = {
  objetivo: "Representar el modelo de dominio completo: clases, atributos, metodos, relaciones y cardinalidades.",
  descripcion: "Diagrama de clases con las 25 entidades reales (Doctrine ORM), agrupadas por contexto, con estereotipo «entity», atributos tipados y operaciones de dominio.",
  elementos: ["25 clases entidad reales (User, Role, Permission, Gestion y sus configuraciones, Carrera, Materia, Aula, Turno, Grupo, Horario, Inscripcion, Documento, VerificacionDocumento, CalendarioRevision, Pago, Nota, AsignacionGrupo, AsignacionDocente, LogEntry, ...)."],
  relaciones: ["Asociaciones *..1 (ManyToOne) con navegabilidad; composiciones 1..* (Gestion compone sus configuraciones/cupos/periodos; Inscripcion compone Documentos y Verificaciones); cardinalidades en ambos extremos."],
  justificacion: "Es la fuente de verdad estructural; mapea 1:1 con el esquema relacional y guia la persistencia ORM. No se simplifica: incluye todos los atributos y metodos publicos.",
};
DOC.conceptual = {
  objetivo: "Representar las entidades del negocio y sus relaciones, independiente de la implementacion.",
  descripcion: "Modelo conceptual (nivel de analisis) con las entidades de dominio del CUP y la jerarquia Persona (Postulante, Estudiante, Docente).",
  elementos: ["Entidades de negocio: Gestion, Carrera, Persona, Usuario, Rol, Permiso, Postulante, Estudiante, Docente, Postulacion, Pago, Admision, Grupo, Materia, Turno, Aula, Horario, Evaluacion, Nota, ResultadoFinal, Bitacora."],
  relaciones: ["Generalizacion Persona <- Postulante/Estudiante/Docente.", "Reglas: un Grupo tiene 4 Materias y un Horario y un Aula; un Docente imparte entre 1 y 4 Grupos; un Estudiante pertenece a un Grupo y tiene muchas Notas; la Admision depende de promedio y cupos."],
  justificacion: "Comunica el negocio a interesados no tecnicos; en la implementacion, Postulante/Estudiante/Docente se materializan como Inscripcion (tipo) + Usuario (rol) — ver nota de trazabilidad.",
};
DOC.logico = {
  objetivo: "Especificar las tablas, columnas y claves del modelo relacional, independientes del motor.",
  descripcion: "Modelo logico derivado de las entidades Doctrine: tablas con sus columnas, claves primarias (PK) y foraneas (FK) y cardinalidades.",
  elementos: ["27 tablas reales (incluidas las de union user_roles y role_permissions) con sus columnas y tipos logicos."],
  relaciones: ["Integridad referencial mediante FK (p.ej. inscripciones.user_id -> users; grupos.materia_id -> materias; notas.inscripcion_id -> inscripciones)."],
  justificacion: "Normalizado (3FN) y trazable al modelo conceptual; base directa del modelo fisico.",
};
DOC.fisico = {
  objetivo: "Definir la implementacion en PostgreSQL: tipos de datos, restricciones y claves.",
  descripcion: "Modelo fisico con los nombres de tabla reales, tipos PostgreSQL (VARCHAR(n), INTEGER, BOOLEAN, TIMESTAMP, JSONB), NOT NULL, UNIQUE y relaciones FK.",
  elementos: ["Tablas reales: users, roles, permissions, gestiones (+ configuraciones/cupos/periodos/carreras/historial), carreras, materias, aulas, turnos, grupos, horarios, inscripciones, inscripcion_documentos, inscripcion_verificaciones, inscripcion_calendario_revision, pagos, notas, notas_docente_materia, notas_inscripcion_grupo, log_entries."],
  relaciones: ["Claves foraneas con sus columnas *_id y restricciones; indices unicos (p.ej. users.email)."],
  justificacion: "Listo para implementar/validar contra el esquema de Doctrine Migrations; refleja el estado real de la base de datos.",
};

// ================= CONSTRUCCION =================
const children = [];

// Portada
children.push(
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 1000, after: 40 }, children: [new TextRun({ text: "UNIVERSIDAD AUTONOMA GABRIEL RENE MORENO  -  FICCT", bold: true, size: 24, color: BLUE })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 500 }, children: [new TextRun({ text: "Sistemas de Informacion 1  -  Grupo 11", size: 19, color: GRAY })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 60 }, children: [new TextRun({ text: "DOCUMENTACION UML COMPLETA", bold: true, size: 48, color: "000000" })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 40 }, children: [new TextRun({ text: "Y MODELO DE DATOS", bold: true, size: 32, color: "000000" })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 40 }, children: [new TextRun({ text: "Sistema Web Integrado de Gestion del Curso Preuniversitario (CUP)", size: 22, color: BLUE })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 420 }, children: [new TextRun({ text: "FICCT - UAGRM", size: 20, color: GRAY, italics: true })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 40 }, children: [new TextRun({ text: "Stack: Symfony 7 · PHP 8 · PostgreSQL · Doctrine ORM · Twig · Bootstrap", size: 18, color: GRAY })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 40 }, children: [new TextRun({ text: "Modelado profesional segun estandares de Enterprise Architect (Sparx Systems)", size: 18, color: GRAY })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 380 }, children: [new TextRun({ text: "Integrantes: Astete Paz Diego Andres (221043748) · Guevara Caballero Jose Armando (219023255)", size: 17, color: "404040" })] }),
  new Paragraph({ children: [new PageBreak()] }),
);

// Indice
children.push(new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun("Indice")] }));
children.push(new TableOfContents("Tabla de contenido", { hyperlink: true, headingStyleRange: "1-2" }));
children.push(new Paragraph({ spacing: { before: 60 }, children: [new TextRun({ italics: true, size: 16, color: GRAY, text: "(En Word: clic derecho sobre la tabla > Actualizar campos.)" })] }));

// Introduccion
children.push(h1("0. Introduccion y consideraciones de modelado"));
children.push(rule());
children.push(para("Este documento reune el modelado UML completo y el modelo de datos del Sistema Web Integrado de Gestion del Curso Preuniversitario (CUP) de la FICCT, elaborado con criterios profesionales de analisis y diseno y con la notacion de Enterprise Architect."));
children.push(field("Stack tecnologico", "Symfony 7, PHP 8, PostgreSQL, Doctrine ORM, Twig, Bootstrap; arquitectura hexagonal (Domain/Application/Infrastructure/UI) con Diseno Orientado a Dominio (11 bounded contexts)."));
children.push(...bullets("Actores", ["Administrador", "Coordinador Academico", "Docente", "Estudiante", "Postulante", "Autoridad"]));
children.push(field("Trazabilidad y fidelidad", "Los diagramas estructurales (clases, modelo logico y fisico) y de comportamiento detallado (BCE y secuencia) son fieles al codigo fuente real: entidades, atributos, metodos, controladores y casos de uso provienen de las clases PHP y plantillas Twig del repositorio. El modelo conceptual usa el vocabulario del negocio (Persona, Postulante, Estudiante, Docente); en la implementacion estos se materializan como la entidad Inscripcion (tipo = ESTUDIANTE | DOCENTE) junto con Usuario + Rol, y las asociaciones grupo-docente / grupo-estudiante como AsignacionDocente / AsignacionGrupo."));
children.push(field("Reglas de negocio modeladas", "Un docente imparte entre 1 y 4 grupos; un grupo tiene 4 materias (Matematicas, Fisica, Ingles, Computacion), un horario y un aula, y pertenece a una gestion; un estudiante pertenece a un grupo y tiene multiples notas; un postulante puede convertirse en estudiante; la admision depende del promedio y los cupos."));

// 1. Contexto
children.push(h1("1. Diagrama de Contexto"));
children.push(rule());
children.push(...docBlock(DOC.contexto));
children.push(img(M.contexto));

// 2. Casos de uso
children.push(h1("2. Diagramas de Casos de Uso"));
children.push(rule());
children.push(new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun("2.1 Casos de Uso General")] }));
children.push(...docBlock(DOC.uc_general));
children.push(img(M.uc_general));
let idx = 2;
for (const it of M.uc_modulos) {
  idx++;
  const key = Object.keys(UCMOD).find(k => it.titulo.includes(k.split(" ")[0])) || Object.keys(UCMOD).find(k => it.titulo.toLowerCase().includes(k.toLowerCase().slice(0,5)));
  const d = UCMOD[key] || {};
  children.push(new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, children: [new TextRun(`2.${idx-2} ${it.titulo}`)] }));
  children.push(...docBlock(d));
  children.push(img(it));
}

// 3. BCE
children.push(h1("3. Diagramas BCE (Boundary - Control - Entity)"));
children.push(rule());
children.push(...docBlock({
  objetivo: "Realizar cada caso de uso mediante clases de analisis con los estereotipos «boundary», «control» y «entity».",
  descripcion: "Patron de robustez del Proceso Unificado: el actor interactua con la Frontera (vistas Twig), que delega en el Control (Controllers y Casos de Uso), que opera sobre las Entidades de dominio. Se incluye un diagrama por cada uno de los 16 casos de uso.",
  justificacion: "Tiende el puente entre los casos de uso y el diseno; en este sistema mapea con la arquitectura hexagonal: Frontera=UI/View, Control=UI/Controller + Application/UseCase, Entidad=Domain/Entity.",
}));
for (const it of M.bce) {
  children.push(new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, children: [new TextRun(`3. ${it.cu} - ${it.titulo}`)] }));
  children.push(field("Objetivo", `Realizar el caso de uso ${it.cu} (${it.titulo}).`));
  children.push(...bullets("Elementos", [
    "Actor(es): " + it.actores.join(", "),
    "«boundary»: " + it.fronteras.join(", "),
    "«control»: " + it.controles.join(", "),
    "«entity»: " + it.entidades.join(", "),
  ]));
  children.push(field("Relaciones", "Actor -> Frontera -> Control -> Entidad (asociaciones de la realizacion del caso de uso)."));
  children.push(img(it));
}

// 4. Secuencia
children.push(h1("4. Diagramas de Secuencia"));
children.push(rule());
children.push(...docBlock({
  objetivo: "Mostrar la interaccion temporal entre objetos para realizar cada caso de uso.",
  descripcion: "Un diagrama por caso de uso con lineas de vida (actor, vistas, controllers, casos de uso, repositorios/PostgreSQL y servicios externos), mensajes con los metodos reales, retornos y barras de activacion.",
  justificacion: "Detalla la logica de cada operacion y verifica la suficiencia de las clases del diagrama de clases y BCE.",
}));
children.push(...bullets("Cobertura de los flujos solicitados", [
  "Login -> CU-01;  Registrar Postulacion -> CU-04;  Registrar Pago -> CU-06;  Aprobar Postulacion -> CU-05.",
  "Asignar Estudiante a Grupo y Asignar Docente a Grupo -> CU-09;  Registrar Notas y Calcular Promedios -> CU-10;  Generar Admision -> CU-13.",
  "Ademas se incluye un diagrama de secuencia integral (Inscripcion + Pago + Confirmacion).",
]));
for (const it of M.secuencias) {
  children.push(new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, children: [new TextRun(`4. ${it.cu} - ${it.titulo}`)] }));
  children.push(field("Objetivo", `Detallar la interaccion del flujo principal de ${it.cu}.`));
  children.push(...bullets("Lineas de vida", it.lifelines));
  children.push(img(it));
}
children.push(new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, children: [new TextRun("4.17 Secuencia integral: Inscripcion + Pago + Confirmacion")] }));
children.push(field("Objetivo", "Integrar en un solo flujo el recorrido completo de la postulacion de estudiante (datos, documentos, validacion, pago y confirmacion)."));
children.push(img(M.secuencia_integral));

// 5. Actividades
children.push(h1("5. Diagramas de Actividades"));
children.push(rule());
for (const it of M.actividades) {
  const key = Object.keys(ACTDOC).find(k => it.titulo.toLowerCase().includes(k.toLowerCase()));
  const d = ACTDOC[key] || {};
  children.push(new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, children: [new TextRun(`5. ${it.titulo}`)] }));
  children.push(...docBlock(d));
  children.push(img(it));
}

// 6. Estados
children.push(h1("6. Diagramas de Estados (Maquina de Estados)"));
children.push(rule());
for (const it of M.estados) {
  const key = Object.keys(ESTDOC).find(k => it.titulo.toLowerCase().includes(k.toLowerCase()));
  const d = ESTDOC[key] || {};
  children.push(new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, children: [new TextRun(`6. ${it.titulo}`)] }));
  children.push(...docBlock(d));
  children.push(img(it));
}

// 7-9 estructurales
function fullSection(num, titulo, d, it) {
  children.push(h1(`${num}. ${titulo}`));
  children.push(rule());
  children.push(...docBlock(d));
  children.push(img(it));
}
fullSection(7, "Diagrama de Componentes", DOC.componentes, M.componentes);
fullSection(8, "Diagrama de Despliegue", DOC.despliegue, M.despliegue);
fullSection(9, "Diagrama de Paquetes", DOC.paquetes, M.paquetes);
fullSection(10, "Diagrama de Clases Completo", DOC.clases, M.clases);

// 11. Modelo de datos
children.push(h1("11. Modelo de Base de Datos"));
children.push(rule());
children.push(para("Se presentan los tres niveles del modelo de datos: conceptual (negocio), logico (tablas y claves) y fisico (PostgreSQL)."));
children.push(new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, children: [new TextRun("11.1 Modelo Conceptual")] }));
children.push(...docBlock(DOC.conceptual)); children.push(img(M.conceptual));
children.push(new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, children: [new TextRun("11.2 Modelo Logico")] }));
children.push(...docBlock(DOC.logico)); children.push(img(M.logico));
children.push(new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, children: [new TextRun("11.3 Modelo Fisico (PostgreSQL)")] }));
children.push(...docBlock(DOC.fisico)); children.push(img(M.fisico));

const doc = new Document({
  creator: "SIGECUP-FICCT", title: "Documentacion UML Completa - SIGECUP-FICCT",
  features: { updateFields: true },
  numbering: { config: [{ reference: "bul", levels: [{ level: 0, format: LevelFormat.BULLET, text: "•", alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 520, hanging: 260 } } } }] }] },
  styles: {
    default: { document: { run: { font: "Arial", size: 22 } } },
    paragraphStyles: [
      { id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true, run: { size: 30, bold: true, color: BLUE, font: "Arial" }, paragraph: { spacing: { before: 220, after: 140 }, outlineLevel: 0 } },
      { id: "Heading2", name: "Heading 2", basedOn: "Normal", next: "Normal", quickFormat: true, run: { size: 24, bold: true, color: "000000", font: "Arial" }, paragraph: { spacing: { before: 140, after: 80 }, outlineLevel: 1 } },
    ],
  },
  sections: [{
    properties: { page: { size: { width: 12240, height: 15840 }, margin: { top: 864, right: 864, bottom: 864, left: 864 } } },
    footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [
      new TextRun({ text: "SIGECUP-FICCT  -  Documentacion UML Completa   |   Pagina ", size: 16, color: GRAY }),
      new TextRun({ children: [PageNumber.CURRENT], size: 16, color: GRAY }) ] })] }) },
    children,
  }],
});
Packer.toBuffer(doc).then((buf) => { fs.writeFileSync("SIGECUP-FICCT_Documentacion_UML_Completa.docx", buf); console.log("MASTER COMPLETO:", buf.length, "bytes"); });
