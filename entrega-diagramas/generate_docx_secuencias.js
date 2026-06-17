const fs = require("fs");
const {
  Document, Packer, Paragraph, TextRun, ImageRun, AlignmentType,
  HeadingLevel, PageBreak, BorderStyle, Footer, PageNumber,
} = require("docx");

const M = JSON.parse(fs.readFileSync("data/manifest_secuencias.json", "utf-8"));

const BRIEF = {
  "CU-01": "Inicio y cierre de sesion con credenciales (validacion de hash, sesion unica e intentos fallidos) y redireccion por rol.",
  "CU-02": "CRUD de cuentas administrativas y asignacion de permisos segun el modelo de roles (RBAC).",
  "CU-03": "Configuracion de las Gestiones (periodos) del CUP, apertura/cierre de inscripcion y definicion de cupos por carrera.",
  "CU-04": "El aspirante completa su formulario, indica su preferencia de turno y carga los requisitos documentales, y presenta la pre-inscripcion.",
  "CU-05": "La facultad realiza el check-in del acta de recepcion; aprueba (habilita el pago) u observa los requisitos.",
  "CU-06": "El postulante paga el arancel via pasarela Stripe Checkout; la confirmacion ocurre por webhook (y como respaldo en la pagina de exito) o por registro manual del coordinador.",
  "CU-07": "Panel administrativo para buscar, filtrar y consultar el expediente de los postulantes; editar o anular.",
  "CU-08": "Administracion de postulaciones docentes (Inscripcion tipo DOCENTE): validacion de postgrado, entrevista y aprobacion de contratacion (asigna rol Docente).",
  "CU-09": "El sistema calcula los grupos, distribuye estudiantes por turno, asigna docentes (limite 4) y programa horarios/aulas.",
  "CU-10": "El docente abre la planilla del grupo, registra las calificaciones (0-100) de los 3 examenes y el sistema calcula promedio y estado.",
  "CU-11": "El postulante consulta en solo lectura su horario asignado y su boletin de notas y promedios.",
  "CU-12": "El docente consulta en solo lectura sus grupos asignados, horario y la lista nominal de estudiantes.",
  "CU-13": "Ejecucion masiva del algoritmo de admision por merito: 1ra opcion, 2da opcion o lista de espera, descontando cupos.",
  "CU-14": "El postulante consulta su dictamen final (ADMITIDO/LISTA_ESPERA/REPROBADO) y descarga la constancia en PDF.",
  "CU-15": "Dashboard ejecutivo en tiempo real y exportacion de los reportes oficiales en PDF/Excel.",
  "CU-16": "Consulta de la bitacora transaccional con filtros (fecha/IP/usuario/accion) y detalle comparativo (JSONB).",
};

const BLUE = "1F4E79", GRAY = "595959";

function img(it) {
  return new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 100, after: 60 },
    children: [new ImageRun({ type: "png", data: fs.readFileSync(it.img),
      transformation: { width: it.target_w, height: it.target_h },
      altText: { title: it.titulo, description: it.titulo, name: it.img } })] });
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

const cover = [
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 1500, after: 60 },
    children: [new TextRun({ text: "UNIVERSIDAD AUTONOMA GABRIEL RENE MORENO  -  FICCT", bold: true, size: 24, color: BLUE })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 700 },
    children: [new TextRun({ text: "Sistema de Informacion 1  -  Grupo 11", size: 20, color: GRAY })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 120 },
    children: [new TextRun({ text: "DIAGRAMAS DE SECUENCIA", bold: true, size: 44, color: "000000" })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 120 },
    children: [new TextRun({ text: "Uno por cada caso de uso (CU-01 a CU-16)", size: 24, color: BLUE })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 600 },
    children: [new TextRun({ text: "Sistema SIGECUP-FICCT  -  Gestion y Admision al Curso Preuniversitario", size: 20, color: GRAY, italics: true })] }),
  new Paragraph({ alignment: AlignmentType.CENTER,
    children: [new TextRun({ text: "Modelado UML derivado del codigo fuente real (Symfony 7.4 / Hexagonal)", size: 18, color: GRAY })] }),
  new Paragraph({ children: [new PageBreak()] }),
  new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun("Introduccion")] }),
  new Paragraph({ spacing: { after: 120 }, children: [new TextRun({ size: 20, text:
    "Este documento contiene un diagrama de secuencia UML (estilo Enterprise Architect) por cada uno de los 16 casos de uso del sistema SIGECUP-FICCT. Cada diagrama representa el flujo principal de exito del caso de uso, con sus lineas de vida (actor, vistas Twig, controladores, casos de uso y persistencia Doctrine/PostgreSQL) y los mensajes con los nombres reales de los metodos del codigo (acciones de los controllers, metodos execute() de los casos de uso y llamadas a repositorios y entidades). Las llamadas sincronas se muestran con flecha solida y los retornos con flecha punteada; las barras verticales son las activaciones." })] }),
  new Paragraph({ children: [new PageBreak()] }),
];

const body = [];
for (const it of M) {
  body.push(new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true,
    children: [new TextRun(`${it.cu}  -  ${it.titulo}`)] }));
  body.push(rule());
  body.push(new Paragraph({ spacing: { after: 70 }, children: [new TextRun({ size: 20, text: BRIEF[it.cu] || "" })] }));
  body.push(caption("Lineas de vida", it.lifelines.join("  |  ")));
  body.push(img(it));
}

const doc = new Document({
  creator: "SIGECUP-FICCT", title: "Diagramas de Secuencia SIGECUP-FICCT",
  styles: {
    default: { document: { run: { font: "Arial", size: 22 } } },
    paragraphStyles: [
      { id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: 28, bold: true, color: BLUE, font: "Arial" }, paragraph: { spacing: { before: 160, after: 120 }, outlineLevel: 0 } },
    ],
  },
  sections: [{
    properties: { page: { size: { width: 12240, height: 15840 }, margin: { top: 864, right: 864, bottom: 864, left: 864 } } },
    footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER,
      children: [ new TextRun({ text: "SIGECUP-FICCT  -  Diagramas de Secuencia   |   Pagina ", size: 16, color: GRAY }),
        new TextRun({ children: [PageNumber.CURRENT], size: 16, color: GRAY }) ] })] }) },
    children: [...cover, ...body],
  }],
});

Packer.toBuffer(doc).then((buf) => {
  fs.writeFileSync("SIGECUP-FICCT_Diagramas_Secuencia.docx", buf);
  console.log("DOCX generado:", buf.length, "bytes;", M.length, "diagramas");
});
