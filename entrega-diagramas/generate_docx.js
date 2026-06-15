const fs = require("fs");
const {
  Document, Packer, Paragraph, TextRun, ImageRun, AlignmentType,
  HeadingLevel, PageBreak, BorderStyle, Table, TableRow, TableCell,
  WidthType, ShadingType, Footer, PageNumber,
} = require("docx");

const M = JSON.parse(fs.readFileSync("data/manifest.json", "utf-8"));

const BRIEF = {
  "CU-01": "Control seguro de inicio y cierre de sesion mediante credenciales, y gestion de los datos de perfil del usuario.",
  "CU-02": "CRUD de cuentas administrativas y asignacion de permisos segun el modelo de roles (RBAC).",
  "CU-03": "Configuracion de las Gestiones (periodos) del CUP y definicion de la matriz de cupos maximos por carrera.",
  "CU-04": "El aspirante completa su formulario, indica su preferencia de turno y envia los 10 requisitos documentales al sistema.",
  "CU-05": "La facultad realiza el check-in de los 10 requisitos; si hay observaciones se notifica al postulante, si todo esta correcto se habilita la fase de pago.",
  "CU-06": "El postulante realiza el pago (habilitado tras un check-in exitoso) y la coordinacion concilia la transaccion para confirmar su habilitacion.",
  "CU-07": "Panel administrativo para buscar, filtrar y hacer seguimiento integral del estado de los expedientes de los aspirantes.",
  "CU-08": "Administracion de postulaciones docentes, validacion de titulos de postgrado, registro de entrevistas y aprobacion de contratacion.",
  "CU-09": "El sistema calcula los grupos necesarios, asigna docentes y distribuye automaticamente a los postulantes pagados segun su preferencia de turno.",
  "CU-10": "Registro de asistencia nominal y asentamiento de calificaciones (0-100) para los 3 examenes parciales de cada materia.",
  "CU-11": "El postulante consulta el grupo y horario que la facultad le asigno, ademas de visualizar el avance de sus notas parciales.",
  "CU-12": "Visualizacion del cronograma semanal, materias asignadas, aulas fisicas y cantidad de estudiantes por grupo.",
  "CU-13": "Ejecucion masiva del algoritmo que asigna plazas por estricto merito academico en 1ra o 2da opcion de carrera, o deriva a lista de espera.",
  "CU-14": "Vista final del dictamen del proceso (ADMITIDO, LISTA_ESPERA, REPROBADO) y descarga de la Constancia Oficial de Admision en PDF.",
  "CU-15": "Despliegue del dashboard ejecutivo en tiempo real y exportacion de los reportes oficiales en formatos PDF y Excel.",
  "CU-16": "Consulta avanzada de la bitacora transaccional para auditar modificaciones de datos mediante registro de IPs, fechas y objetos JSONB.",
};

const BLUE = "1F4E79", GRAY = "595959", LIGHT = "D9E2F3";

function img(it) {
  return new Paragraph({
    alignment: AlignmentType.CENTER,
    spacing: { before: 120, after: 80 },
    children: [new ImageRun({
      type: "png",
      data: fs.readFileSync(it.img),
      transformation: { width: it.target_w, height: it.target_h },
      altText: { title: it.titulo, description: it.titulo, name: it.img },
    })],
  });
}

function caption(label, value) {
  return new Paragraph({
    spacing: { after: 40 },
    children: [
      new TextRun({ text: label + ": ", bold: true, size: 17, color: BLUE }),
      new TextRun({ text: value, size: 17, color: "404040" }),
    ],
  });
}

function ruleParagraph() {
  return new Paragraph({
    border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: BLUE, space: 1 } },
    spacing: { after: 60 },
    children: [new TextRun("")],
  });
}

// ---------- Portada ----------
const cover = [
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 1400, after: 60 },
    children: [new TextRun({ text: "UNIVERSIDAD AUTONOMA GABRIEL RENE MORENO", bold: true, size: 26, color: BLUE })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 600 },
    children: [new TextRun({ text: "Facultad de Ingenieria en Ciencias de la Computacion y Telecomunicaciones (FICCT)", size: 20, color: GRAY })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 120 },
    children: [new TextRun({ text: "DIAGRAMAS DE ANALISIS DE CLASES,", bold: true, size: 40, color: "000000" })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 120 },
    children: [new TextRun({ text: "ARQUITECTURA LOGICA Y DESPLIEGUE", bold: true, size: 40, color: "000000" })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 500 },
    children: [new TextRun({ text: "Sistema SIGECUP-FICCT  -  Gestion y Admision al Curso Preuniversitario", size: 22, color: BLUE, italics: true })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 60 },
    children: [new TextRun({ text: "Modelado UML derivado del codigo fuente real (Symfony 7.4 / Arquitectura Hexagonal)", size: 18, color: GRAY })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 700, after: 40 },
    children: [new TextRun({ text: "Materia: Sistemas de Informacion 1   -   Grupo 11", size: 20, color: "404040" })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 40 },
    children: [new TextRun({ text: "Docente: M.Sc. Ing. Angelica Garzon Cuellar", size: 20, color: "404040" })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 200, after: 20 },
    children: [new TextRun({ text: "Integrantes:", bold: true, size: 20, color: "404040" })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: "Astete Paz Diego Andres  -  221043748", size: 19, color: "404040" })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: "Guevara Caballero Jose Armando  -  219023255", size: 19, color: "404040" })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 600 },
    children: [new TextRun({ text: "Santa Cruz - Bolivia", size: 18, color: GRAY })] }),
  new Paragraph({ children: [new PageBreak()] }),
];

// ---------- Introduccion / metodologia ----------
const intro = [
  new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun("Introduccion y metodologia de modelado")] }),
  new Paragraph({ spacing: { after: 120 }, children: [new TextRun({ size: 20, text:
    "Este documento contiene los 16 diagramas de analisis de clases (uno por caso de uso), el diagrama de arquitectura logica y el diagrama de despliegue del sistema SIGECUP-FICCT. Todos los diagramas fueron construidos de forma fiel al codigo fuente real del repositorio (sin datos inventados): las entidades, sus atributos y metodos, los controladores y los casos de uso provienen directamente de las clases PHP y plantillas Twig del proyecto." })] }),
  new Paragraph({ spacing: { after: 120 }, children: [new TextRun({ size: 20, text:
    "Los diagramas de analisis de clases siguen la notacion de robustez del Proceso Unificado (estilo Enterprise Architect), que descompone cada caso de uso en tres tipos de clases de analisis:" })] }),
  new Paragraph({ spacing: { after: 30 }, children: [
    new TextRun({ text: "Frontera («boundary»): ", bold: true, size: 20, color: BLUE }),
    new TextRun({ size: 20, text: "las vistas/formularios Twig con los que interactua el actor (campos y acciones de la interfaz)." })] }),
  new Paragraph({ spacing: { after: 30 }, children: [
    new TextRun({ text: "Control («control»): ", bold: true, size: 20, color: BLUE }),
    new TextRun({ size: 20, text: "los Controllers de la capa UI y los Casos de Uso de la capa de Aplicacion que orquestan la logica." })] }),
  new Paragraph({ spacing: { after: 120 }, children: [
    new TextRun({ text: "Entidad («entity»): ", bold: true, size: 20, color: BLUE }),
    new TextRun({ size: 20, text: "las entidades de dominio (Domain/Entity) mapeadas con Doctrine ORM, con sus atributos y metodos reales." })] }),
  new Paragraph({ spacing: { after: 120 }, children: [new TextRun({ size: 20, text:
    "Esta correspondencia es directa con la arquitectura hexagonal del sistema: Frontera = UI/View, Control = UI/Controller + Application/UseCase, Entidad = Domain/Entity. Bajo cada diagrama se incluye la trazabilidad a los archivos reales del codigo." })] }),
  new Paragraph({ children: [new PageBreak()] }),
];

// ---------- Seccion 1: Analisis de clases ----------
const sec1 = [
  new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun("1. Diagramas de Analisis de Clases")] }),
  new Paragraph({ spacing: { after: 80 }, children: [new TextRun({ size: 20, italics: true, color: GRAY,
    text: "Un diagrama por cada uno de los 16 casos de uso del sistema. Cada diagrama relaciona los actores con las clases frontera, control y entidad reales que realizan el caso de uso." })] }),
];

for (const it of M.analisis) {
  sec1.push(new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true,
    children: [new TextRun(`${it.cu}  -  ${it.titulo}`)] }));
  sec1.push(ruleParagraph());
  sec1.push(new Paragraph({ spacing: { after: 80 }, children: [new TextRun({ size: 20, text: BRIEF[it.cu] || "" })] }));
  sec1.push(caption("Actor(es)", it.actores.join(", ")));
  sec1.push(caption("Clases frontera", it.fronteras.join(", ")));
  sec1.push(caption("Clases control", it.controles.join(", ")));
  sec1.push(caption("Clases entidad", it.entidades.join(", ")));
  sec1.push(img(it));
  sec1.push(new Paragraph({ spacing: { before: 40 }, children: [
    new TextRun({ text: "Trazabilidad (codigo fuente): ", bold: true, size: 15, color: GRAY }),
    new TextRun({ text: it.fuentes.join("  ;  "), size: 15, color: "808080", italics: true })] }));
}

// ---------- Seccion 2: Arquitectura ----------
const sec2 = [
  new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun("2. Diagrama de Arquitectura Logica")] }),
  ruleParagraph(),
  new Paragraph({ spacing: { after: 80 }, children: [new TextRun({ size: 20, text:
    "Vista en capas de la arquitectura limpia/hexagonal del sistema. De la presentacion (Controllers + vistas Twig) hacia los datos y servicios externos (PostgreSQL Neon, Stripe, SMTP), pasando por las capas de Aplicacion (casos de uso), Dominio (entidades y reglas) e Infraestructura (repositorios Doctrine, pasarela de pago y mailer)." })] }),
  caption("Bounded contexts (modulos reales)", M.arquitectura.modulos.join(", ")),
  caption("Capas", M.arquitectura.capas.join("  >  ")),
  img(M.arquitectura),
];

// ---------- Seccion 3: Despliegue ----------
const sec3 = [
  new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun("3. Diagrama de Despliegue")] }),
  ruleParagraph(),
  new Paragraph({ spacing: { after: 80 }, children: [new TextRun({ size: 20, text:
    "Disposicion fisica real del sistema segun el repositorio: los dispositivos cliente acceden por HTTPS a la plataforma serverless Vercel (runtime vercel-php que ejecuta la aplicacion Symfony 7.4), que persiste en la base de datos gestionada Neon PostgreSQL (AWS) e integra los servicios externos Stripe (pagos) y Gmail SMTP (correos)." })] }),
  new Paragraph({ spacing: { after: 80 }, children: [
    new TextRun({ text: "Nota: ", bold: true, size: 18, color: BLUE }),
    new TextRun({ size: 18, color: "404040", text: "la documentacion del proyecto menciona Upsun/AWS como plataforma planeada, pero el repositorio se despliega realmente en Vercel + Neon (segun vercel.json y DATABASE_URL)." })] }),
  caption("Nodos", M.despliegue.nodos.join("  |  ")),
  img(M.despliegue),
];

const doc = new Document({
  creator: "SIGECUP-FICCT",
  title: "Diagramas UML SIGECUP-FICCT",
  styles: {
    default: { document: { run: { font: "Arial", size: 22 } } },
    paragraphStyles: [
      { id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: 30, bold: true, color: BLUE, font: "Arial" },
        paragraph: { spacing: { before: 240, after: 160 }, outlineLevel: 0 } },
      { id: "Heading2", name: "Heading 2", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: 24, bold: true, color: "000000", font: "Arial" },
        paragraph: { spacing: { before: 120, after: 80 }, outlineLevel: 1 } },
    ],
  },
  sections: [{
    properties: { page: {
      size: { width: 12240, height: 15840 },
      margin: { top: 864, right: 864, bottom: 864, left: 864 },
    } },
    footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER,
      children: [
        new TextRun({ text: "SIGECUP-FICCT  -  Diagramas UML   |   Pagina ", size: 16, color: GRAY }),
        new TextRun({ children: [PageNumber.CURRENT], size: 16, color: GRAY }),
      ] })] }) },
    children: [...cover, ...intro, ...sec1, ...sec2, ...sec3],
  }],
});

Packer.toBuffer(doc).then((buf) => {
  fs.writeFileSync("SIGECUP-FICCT_Diagramas_UML.docx", buf);
  console.log("DOCX generado:", buf.length, "bytes");
});
