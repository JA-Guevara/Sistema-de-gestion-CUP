const fs = require("fs");
const {
  Document, Packer, Paragraph, TextRun, ImageRun, AlignmentType,
  HeadingLevel, BorderStyle, Footer, PageNumber, LevelFormat,
} = require("docx");

const { execSync } = require("child_process");
// dimensiones del PNG
const sizeOut = execSync(
  `python -c "from PIL import Image;im=Image.open('png/SECUENCIA_INSCRIPCION.png');print(im.size[0],im.size[1])"`
).toString().trim().split(/\s+/).map(Number);
const [pw, ph] = sizeOut;
const MAXW = 7.3, MAXH = 8.7, DPI = 96;
const aspect = pw / ph;
let tw, th;
if (aspect >= MAXW / MAXH) { tw = MAXW; th = MAXW / aspect; }
else { th = MAXH; tw = MAXH * aspect; }
const TW = Math.round(tw * DPI), TH = Math.round(th * DPI);

const BLUE = "1F4E79", GRAY = "595959";

const fases = [
  ["1. Pre-inscripcion (datos del postulante y carrera)",
   "El postulante completa el formulario con sus datos personales, su carrera de 1ra y 2da opcion y la preferencia de turno. InscripcionController valida el CSRF y delega en CrearInscripcion, que verifica la gestion activa y los cupos, resuelve las carreras y persiste la Inscripcion (estado BORRADOR o PRESENTADA)."],
  ["2. Documentos (carga de requisitos)",
   "El postulante adjunta los documentos. SubirDocumento valida la extension y el tamano (<=5 MB), mueve el archivo a uploads/ y registra cada Documento asociado a la Inscripcion."],
  ["3. Validacion documental (habilita el pago)",
   "El Coordinador revisa el acta de recepcion y aprueba. ValidarInscripcion exige actaConforme() y cambia el estado a VALIDADA: este es el gate que habilita el pago del arancel."],
  ["4. Pago mediante pasarela (Stripe Checkout)",
   "El postulante inicia el pago. IniciarPagoInscripcion valida que la postulacion este VALIDADA, crea un Pago PENDIENTE y, a traves de StripeGateway, crea una sesion de Stripe Checkout; el sistema redirige al postulante a la URL de pago de Stripe."],
  ["5. Confirmacion (webhook autoritativo + asignacion de rol)",
   "Tras pagar, Stripe notifica por el webhook POST /stripe/webhook (StripeWebhookController). Se verifica la firma (constructWebhookEvent) y ConfirmarPagoStripe.porSession() confirma el pago de forma idempotente: comprueba payment_status='paid', invoca ConfirmarInscripcion (estado CONFIRMADA y user.syncRoles(Estudiante)) y marca el Pago como pagado."],
  ["6. Retorno al postulante (pagina de exito)",
   "Al volver de Stripe, la pagina /pago/exito vuelve a invocar confirmarPago.porSession() (idempotente, por si el webhook aun no llego) y muestra la inscripcion CONFIRMADA con el rol de Estudiante ya habilitado."],
];

const fuentes = [
  "src/Inscripcion/UI/Controller/InscripcionController.php",
  "src/Inscripcion/UI/Controller/PagoController.php",
  "src/Inscripcion/UI/Controller/StripeWebhookController.php",
  "src/Inscripcion/Application/UseCase/CrearInscripcion.php",
  "src/Inscripcion/Application/UseCase/SubirDocumento.php",
  "src/Inscripcion/Application/UseCase/ValidarInscripcion.php",
  "src/Inscripcion/Application/UseCase/IniciarPagoInscripcion.php",
  "src/Inscripcion/Application/UseCase/ConfirmarPagoStripe.php",
  "src/Inscripcion/Application/UseCase/ConfirmarInscripcion.php",
  "src/Inscripcion/Infrastructure/Payment/StripeGateway.php",
  "src/Inscripcion/Domain/Entity/{Inscripcion,Documento,Pago}.php",
];

const children = [];
children.push(new Paragraph({ heading: HeadingLevel.HEADING_1,
  children: [new TextRun("Diagrama de Secuencia - Inscripcion, Pago y Confirmacion")] }));
children.push(new Paragraph({ border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: BLUE, space: 1 } },
  spacing: { after: 120 }, children: [new TextRun("")] }));
children.push(new Paragraph({ spacing: { after: 100 }, children: [new TextRun({ size: 20, text:
  "Este diagrama de secuencia (UML) modela, de forma fiel al codigo fuente del sistema SIGECUP-FICCT, el recorrido completo de una postulacion de estudiante: la captura de sus datos y carrera, la carga de documentos, la validacion documental que habilita el pago, el pago del arancel mediante la pasarela Stripe Checkout y la confirmacion de la inscripcion (que asigna el rol de Estudiante). Los nombres de los metodos, controladores, casos de uso y entidades corresponden a las clases reales del proyecto." })] }));

children.push(new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun("Resumen del flujo (6 fases)")] }));
for (const [t, d] of fases) {
  children.push(new Paragraph({ spacing: { after: 20 }, children: [new TextRun({ text: t, bold: true, size: 20, color: BLUE })] }));
  children.push(new Paragraph({ spacing: { after: 90 }, children: [new TextRun({ size: 19, color: "333333", text: d })] }));
}

children.push(new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, children: [new TextRun("Diagrama")] }));
children.push(new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 80, after: 80 },
  children: [new ImageRun({ type: "png", data: fs.readFileSync("png/SECUENCIA_INSCRIPCION.png"),
    transformation: { width: TW, height: TH },
    altText: { title: "Diagrama de secuencia inscripcion", description: "Flujo de inscripcion, pago y confirmacion", name: "secuencia" } })] }));
children.push(new Paragraph({ spacing: { before: 40 }, children: [
  new TextRun({ text: "Trazabilidad (codigo fuente): ", bold: true, size: 15, color: GRAY }),
  new TextRun({ text: fuentes.join("  ;  "), size: 15, color: "808080", italics: true })] }));

const doc = new Document({
  creator: "SIGECUP-FICCT",
  title: "Diagrama de Secuencia - Inscripcion",
  styles: {
    default: { document: { run: { font: "Arial", size: 22 } } },
    paragraphStyles: [
      { id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: 30, bold: true, color: BLUE, font: "Arial" }, paragraph: { spacing: { before: 200, after: 120 }, outlineLevel: 0 } },
      { id: "Heading2", name: "Heading 2", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: 24, bold: true, color: "000000", font: "Arial" }, paragraph: { spacing: { before: 140, after: 80 }, outlineLevel: 1 } },
    ],
  },
  sections: [{
    properties: { page: { size: { width: 12240, height: 15840 }, margin: { top: 864, right: 864, bottom: 864, left: 864 } } },
    footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER,
      children: [ new TextRun({ text: "SIGECUP-FICCT  -  Diagrama de Secuencia   |   Pagina ", size: 16, color: GRAY }),
        new TextRun({ children: [PageNumber.CURRENT], size: 16, color: GRAY }) ] })] }) },
    children,
  }],
});

Packer.toBuffer(doc).then((buf) => {
  fs.writeFileSync("SIGECUP-FICCT_Diagrama_Secuencia_Inscripcion.docx", buf);
  console.log("DOCX secuencia generado:", buf.length, "bytes; imagen", TW + "x" + TH, "px");
});
