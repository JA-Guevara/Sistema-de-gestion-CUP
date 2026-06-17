const fs = require("fs");
const { execSync } = require("child_process");
const {
  Document, Packer, Paragraph, TextRun, ImageRun, AlignmentType, HeadingLevel,
  PageBreak, BorderStyle, Footer, PageNumber, Table, TableRow, TableCell,
  WidthType, ShadingType, TableOfContents, VerticalAlign,
} = require("docx");

const MA = JSON.parse(fs.readFileSync("data/manifest.json", "utf-8"));        // analisis, arquitectura, despliegue
const MS = JSON.parse(fs.readFileSync("data/manifest_secuencias.json", "utf-8")); // 16 secuencias

const BLUE = "1F4E79", BLUE2 = "2E5C9A", GRAY = "595959", LIGHT = "D9E2F3";

const BRIEF = {
  "CU-01": "Inicio y cierre de sesion con credenciales (validacion de hash, sesion unica e intentos fallidos) y redireccion por rol.",
  "CU-02": "CRUD de cuentas administrativas y asignacion de permisos segun el modelo de roles (RBAC).",
  "CU-03": "Configuracion de las Gestiones (periodos) del CUP, apertura/cierre de inscripcion y definicion de cupos por carrera.",
  "CU-04": "El aspirante completa su formulario, indica su preferencia de turno y carga los requisitos, y presenta la pre-inscripcion.",
  "CU-05": "La facultad realiza el check-in del acta de recepcion; aprueba (habilita el pago) u observa los requisitos.",
  "CU-06": "El postulante paga el arancel via pasarela Stripe Checkout; la confirmacion ocurre por webhook y pagina de exito, o registro manual.",
  "CU-07": "Panel administrativo para buscar, filtrar y consultar el expediente de los postulantes; editar o anular.",
  "CU-08": "Administracion de postulaciones docentes (Inscripcion tipo DOCENTE): validacion de postgrado, entrevista y contratacion.",
  "CU-09": "El sistema calcula los grupos, distribuye estudiantes por turno, asigna docentes (limite 4) y programa horarios/aulas.",
  "CU-10": "El docente abre la planilla del grupo, registra las calificaciones (0-100) de los 3 examenes y el sistema calcula promedio y estado.",
  "CU-11": "El postulante consulta en solo lectura su horario asignado y su boletin de notas y promedios.",
  "CU-12": "El docente consulta en solo lectura sus grupos asignados, horario y la lista nominal de estudiantes.",
  "CU-13": "Ejecucion masiva del algoritmo de admision por merito: 1ra opcion, 2da opcion o lista de espera, descontando cupos.",
  "CU-14": "El postulante consulta su dictamen final (ADMITIDO/LISTA_ESPERA/REPROBADO) y descarga la constancia en PDF.",
  "CU-15": "Dashboard ejecutivo en tiempo real y exportacion de los reportes oficiales en PDF/Excel.",
  "CU-16": "Consulta de la bitacora transaccional con filtros (fecha/IP/usuario/accion) y detalle comparativo (JSONB).",
};

function img(it) {
  return new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 100, after: 60 },
    children: [new ImageRun({ type: "png", data: fs.readFileSync(it.img),
      transformation: { width: it.target_w, height: it.target_h },
      altText: { title: it.titulo || it.img, description: it.titulo || it.img, name: it.img } })] });
}
function caption(label, value) {
  return new Paragraph({ spacing: { after: 40 }, children: [
    new TextRun({ text: label + ": ", bold: true, size: 17, color: BLUE }),
    new TextRun({ text: value, size: 17, color: "404040" })] });
}
function rule() {
  return new Paragraph({ border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: BLUE, space: 1 } },
    spacing: { after: 60 }, children: [new TextRun("")] });
}
function p(text, size = 20, opts = {}) {
  return new Paragraph({ spacing: { after: opts.after ?? 100 }, alignment: opts.align,
    children: [new TextRun({ text, size, color: opts.color || "333333", bold: opts.bold, italics: opts.italics })] });
}
function fitInline(path) {
  const [w, h] = execSync(`python -c "from PIL import Image;i=Image.open(r'${path}');print(i.size[0],i.size[1])"`).toString().trim().split(/\s+/).map(Number);
  const MAXW = 7.3, MAXH = 8.7, DPI = 96, a = w / h;
  let tw, th;
  if (a >= MAXW / MAXH) { tw = MAXW; th = MAXW / a; } else { th = MAXH; tw = MAXH * a; }
  return { img: path, target_w: Math.round(tw * DPI), target_h: Math.round(th * DPI), titulo: "Secuencia integral de inscripcion" };
}

// ---------- Portada ----------
const cover = [
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 1100, after: 50 },
    children: [new TextRun({ text: "UNIVERSIDAD AUTONOMA GABRIEL RENE MORENO", bold: true, size: 26, color: BLUE })] }),
  p("Facultad de Ingenieria en Ciencias de la Computacion y Telecomunicaciones (FICCT)", 19, { align: AlignmentType.CENTER, color: GRAY, after: 500 }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 80 },
    children: [new TextRun({ text: "DOCUMENTACION UML COMPLETA", bold: true, size: 46, color: "000000" })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 60 },
    children: [new TextRun({ text: "SISTEMA SIGECUP-FICCT", bold: true, size: 30, color: BLUE })] }),
  p("Gestion y Admision al Curso Preuniversitario", 20, { align: AlignmentType.CENTER, color: BLUE2, italics: true, after: 420 }),
  p("Analisis de clases  -  Arquitectura logica  -  Despliegue  -  Secuencia (por caso de uso)", 18, { align: AlignmentType.CENTER, color: GRAY, after: 60 }),
  p("Modelado derivado del codigo fuente real (Symfony 7.4 / PHP 8.2 / Arquitectura Hexagonal)", 17, { align: AlignmentType.CENTER, color: GRAY, after: 500 }),
  p("Materia: Sistemas de Informacion 1   -   Grupo 11", 20, { align: AlignmentType.CENTER, color: "404040", after: 40 }),
  p("Docente: M.Sc. Ing. Angelica Garzon Cuellar", 20, { align: AlignmentType.CENTER, color: "404040", after: 160 }),
  p("Integrantes: Astete Paz Diego Andres (221043748)  -  Guevara Caballero Jose Armando (219023255)", 18, { align: AlignmentType.CENTER, color: "404040", after: 360 }),
  p("Santa Cruz - Bolivia", 18, { align: AlignmentType.CENTER, color: GRAY }),
  new Paragraph({ children: [new PageBreak()] }),
];

// ---------- Indice ----------
const toc = [
  new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun("Indice")] }),
  new TableOfContents("Tabla de contenido", { hyperlink: true, headingStyleRange: "1-2" }),
  new Paragraph({ spacing: { before: 80 }, children: [new TextRun({ italics: true, size: 16, color: GRAY,
    text: "(En Word: clic derecho sobre la tabla > Actualizar campos, para refrescar los numeros de pagina.)" })] }),
  new Paragraph({ children: [new PageBreak()] }),
];

// ---------- 1. Casos de uso (tabla) ----------
function cuTable() {
  const border = { style: BorderStyle.SINGLE, size: 1, color: "BBBBBB" };
  const borders = { top: border, bottom: border, left: border, right: border };
  const head = (t, w) => new TableCell({ borders, width: { size: w, type: WidthType.DXA },
    shading: { fill: BLUE, type: ShadingType.CLEAR }, margins: { top: 60, bottom: 60, left: 100, right: 100 },
    verticalAlign: VerticalAlign.CENTER, children: [new Paragraph({ children: [new TextRun({ text: t, bold: true, color: "FFFFFF", size: 18 })] })] });
  const cell = (t, w, b) => new TableCell({ borders, width: { size: w, type: WidthType.DXA },
    margins: { top: 50, bottom: 50, left: 100, right: 100 }, verticalAlign: VerticalAlign.CENTER,
    children: [new Paragraph({ children: [new TextRun({ text: t, size: 17, bold: b, color: b ? BLUE : "333333" })] })] });
  const rows = [new TableRow({ tableHeader: true, children: [head("ID", 900), head("Caso de Uso", 4400), head("Actor(es)", 4060)] })];
  for (const it of MA.analisis) {
    rows.push(new TableRow({ children: [cell(it.cu, 900, true), cell(it.titulo, 4400), cell(it.actores.join(", "), 4060)] }));
  }
  return new Table({ width: { size: 9360, type: WidthType.DXA }, columnWidths: [900, 4400, 4060], rows });
}
const sec0 = [
  new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun("1. Casos de uso del sistema")] }),
  rule(),
  p("El sistema SIGECUP-FICCT cuenta con cinco actores (Postulante, Docente, Coordinador Academico, Autoridad y Administrador) y 16 casos de uso. Para cada uno se incluye su diagrama de analisis de clases (seccion 2) y su diagrama de secuencia (seccion 5).", 20, { after: 120 }),
  cuTable(),
  new Paragraph({ spacing: { before: 120 }, children: [new TextRun({ size: 18, italics: true, color: GRAY,
    text: "Todos los diagramas son fieles al codigo fuente: entidades, atributos, metodos, controladores y casos de uso provienen de las clases PHP y plantillas Twig reales del repositorio." })] }),
];

// ---------- 2. Analisis de clases ----------
const sec2 = [new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun("2. Diagramas de Analisis de Clases")] }),
  p("Notacion de robustez (Actor -> «boundary» Frontera -> «control» Control -> «entity» Entidad). Un diagrama por caso de uso.", 19, { italics: true, color: GRAY, after: 40 })];
for (const it of MA.analisis) {
  sec2.push(new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, children: [new TextRun(`${it.cu}  -  ${it.titulo}`)] }));
  sec2.push(rule());
  sec2.push(p(BRIEF[it.cu] || "", 20, { after: 70 }));
  sec2.push(caption("Actor(es)", it.actores.join(", ")));
  sec2.push(caption("Clases frontera", it.fronteras.join(", ")));
  sec2.push(caption("Clases control", it.controles.join(", ")));
  sec2.push(caption("Clases entidad", it.entidades.join(", ")));
  sec2.push(img(it));
}

// ---------- 3. Arquitectura ----------
const sec3 = [
  new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun("3. Diagrama de Arquitectura Logica")] }),
  rule(),
  p("Vista en capas de la arquitectura limpia/hexagonal: de la presentacion (Controllers + vistas Twig) a los datos y servicios externos (PostgreSQL Neon, Stripe, SMTP), pasando por Aplicacion (casos de uso), Dominio (entidades y reglas) e Infraestructura (repositorios Doctrine, pasarela de pago, mailer).", 20, { after: 60 }),
  caption("Bounded contexts (modulos reales)", MA.arquitectura.modulos.join(", ")),
  caption("Capas", MA.arquitectura.capas.join("  >  ")),
  img(MA.arquitectura),
];

// ---------- 4. Despliegue ----------
const sec4 = [
  new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun("4. Diagrama de Despliegue")] }),
  rule(),
  p("Disposicion fisica real (segun el repositorio): dispositivos cliente -> HTTPS -> plataforma serverless Vercel (Symfony 7.4) -> Neon PostgreSQL (AWS), con Stripe (pagos) y Gmail SMTP (correos) como servicios externos.", 20, { after: 40 }),
  p("Nota: la documentacion del proyecto menciona Upsun/AWS como plataforma planeada, pero el repositorio se despliega realmente en Vercel + Neon (vercel.json y DATABASE_URL).", 18, { color: "404040", after: 40 }),
  caption("Nodos", MA.despliegue.nodos.join("  |  ")),
  img(MA.despliegue),
];

// ---------- 5. Secuencia por caso de uso ----------
const sec5 = [new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun("5. Diagramas de Secuencia (por caso de uso)")] }),
  p("Flujo principal de exito de cada caso de uso, con lineas de vida y mensajes con los nombres reales de los metodos del codigo. Llamadas con flecha solida, retornos punteados; las barras verticales son activaciones.", 19, { italics: true, color: GRAY, after: 40 })];
for (const it of MS) {
  sec5.push(new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, children: [new TextRun(`${it.cu}  -  ${it.titulo}`)] }));
  sec5.push(rule());
  sec5.push(p(BRIEF[it.cu] || "", 20, { after: 70 }));
  sec5.push(caption("Lineas de vida", it.lifelines.join("  |  ")));
  sec5.push(img(it));
}

// ---------- 6. Anexo: secuencia integral ----------
const anexo = [
  new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun("6. Anexo: Secuencia integral de Inscripcion, Pago y Confirmacion")] }),
  rule(),
  p("Diagrama de secuencia que integra en un solo flujo el recorrido completo de una postulacion de estudiante: datos y carrera, documentos, validacion documental, pago por pasarela Stripe y confirmacion (asignacion del rol Estudiante). Combina los casos de uso CU-04, CU-05 y CU-06.", 20, { after: 60 }),
  img(fitInline("png/SECUENCIA_INSCRIPCION.png")),
];

const doc = new Document({
  creator: "SIGECUP-FICCT", title: "Documentacion UML completa - SIGECUP-FICCT",
  features: { updateFields: true },
  styles: {
    default: { document: { run: { font: "Arial", size: 22 } } },
    paragraphStyles: [
      { id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: 30, bold: true, color: BLUE, font: "Arial" }, paragraph: { spacing: { before: 200, after: 140 }, outlineLevel: 0 } },
      { id: "Heading2", name: "Heading 2", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: 24, bold: true, color: "000000", font: "Arial" }, paragraph: { spacing: { before: 120, after: 80 }, outlineLevel: 1 } },
    ],
  },
  sections: [{
    properties: { page: { size: { width: 12240, height: 15840 }, margin: { top: 864, right: 864, bottom: 864, left: 864 } } },
    footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER,
      children: [ new TextRun({ text: "SIGECUP-FICCT  -  Documentacion UML completa   |   Pagina ", size: 16, color: GRAY }),
        new TextRun({ children: [PageNumber.CURRENT], size: 16, color: GRAY }) ] })] }) },
    children: [...cover, ...toc, ...sec0, ...sec2, ...sec3, ...sec4, ...sec5, ...anexo],
  }],
});

Packer.toBuffer(doc).then((buf) => {
  fs.writeFileSync("SIGECUP-FICCT_Documentacion_UML_Completa.docx", buf);
  console.log("MASTER DOCX generado:", buf.length, "bytes");
});
