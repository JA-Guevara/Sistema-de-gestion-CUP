# -*- coding: utf-8 -*-
"""
Renderizador de diagramas estilo Enterprise Architect (tema Earth/Sand) con Pillow.
- Diagramas de analisis de clases (Actor -> Frontera -> Control -> Entidad)
- Diagrama de arquitectura logica (capas)
- Diagrama de despliegue (nodos 3D)
Todo en alta resolucion (PNG) para incrustar en Word.
"""
import os, json, sys
from PIL import Image, ImageDraw, ImageFont

# ----------------------------------------------------------------------------
# Configuracion visual (tema EA "Earth")
# ----------------------------------------------------------------------------
S = 2  # factor de escala (alta resolucion)

# Colores
C_TITLE_TOP   = (243, 236, 205)   # barra de titulo (degradado arriba)
C_TITLE_BOT   = (225, 210, 158)   # barra de titulo (degradado abajo)
C_BODY        = (252, 249, 239)   # cuerpo de la clase (crema)
C_BORDER      = (150, 138, 95)    # borde gris-tostado
C_NAME        = (0, 0, 0)         # nombre de clase (negro)
C_STEREO      = (90, 80, 45)      # estereotipo «...»
C_MEMBER      = (74, 92, 36)      # texto de miembros (verde oliva EA)
C_VIS_PUB     = (40, 110, 40)     # visibilidad + (verde)
C_VIS_PRI     = (150, 40, 40)     # visibilidad - (rojo)
C_LINE        = (90, 90, 90)      # lineas de asociacion
C_FRAME       = (120, 120, 120)   # marco del diagrama
C_FRAME_TAB   = (236, 236, 230)   # pestana del marco
C_ACTOR       = (60, 60, 60)
C_BG          = (255, 255, 255)

FONT_DIR = r"C:\Windows\Fonts"
def _font(name, size):
    return ImageFont.truetype(os.path.join(FONT_DIR, name), int(size * S))

F_NAME   = _font("arialbd.ttf", 14)
F_STEREO = _font("ariali.ttf", 11)
F_MEMBER = _font("arial.ttf", 12)
F_ACTOR  = _font("arialbd.ttf", 12)
F_FRAME  = _font("arialbd.ttf", 13)
F_LABEL  = _font("arial.ttf", 10)
F_NODE   = _font("arialbd.ttf", 13)
F_NODE_S = _font("ariali.ttf", 11)
F_LAYER  = _font("arialbd.ttf", 13)
F_COMP   = _font("arial.ttf", 11)

PAD   = int(10 * S)   # padding interno horizontal
VPAD  = int(6 * S)    # padding vertical compartimento
LH    = int(20 * S)   # alto de linea de miembro

def tw(draw, text, font):
    b = draw.textbbox((0, 0), text, font=font)
    return b[2] - b[0]

def th(font):
    a, d = font.getmetrics()
    return a + d

# ----------------------------------------------------------------------------
# Primitivas
# ----------------------------------------------------------------------------
def gradient_rect(img, x0, y0, x1, y1, top, bot):
    """Degradado vertical dentro de un rectangulo."""
    h = max(1, int(y1 - y0))
    for i in range(h):
        t = i / h
        r = int(top[0] + (bot[0]-top[0])*t)
        g = int(top[1] + (bot[1]-top[1])*t)
        b = int(top[2] + (bot[2]-top[2])*t)
        ImageDraw.Draw(img).line([(x0, y0+i), (x1, y0+i)], fill=(r, g, b))

def measure_class(draw, stereo, name, attrs, methods):
    """Devuelve (w, h, title_h, attr_h, has_attr, has_meth)."""
    name_lines = [name]
    widths = [tw(draw, name, F_NAME)]
    if stereo:
        widths.append(tw(draw, stereo, F_STEREO))
    for a in attrs:
        widths.append(tw(draw, a, F_MEMBER))
    for m in methods:
        widths.append(tw(draw, m, F_MEMBER))
    w = max(widths) + 2*PAD
    w = max(w, int(150 * S))

    title_h = VPAD*2 + th(F_NAME) + (th(F_STEREO)+int(2*S) if stereo else 0)
    has_attr = len(attrs) > 0
    has_meth = len(methods) > 0
    attr_h = (VPAD*2 + LH*len(attrs)) if has_attr else 0
    meth_h = (VPAD*2 + LH*len(methods)) if has_meth else 0
    if not has_attr and not has_meth:
        attr_h = VPAD  # pequeno cuerpo vacio
    h = title_h + attr_h + meth_h
    return w, h, title_h, attr_h, has_attr, has_meth, meth_h

def draw_class(img, draw, x, y, stereo, name, attrs, methods):
    w, h, title_h, attr_h, has_attr, has_meth, meth_h = measure_class(draw, stereo, name, attrs, methods)
    rad = int(7 * S)
    # cuerpo
    draw.rounded_rectangle([x, y, x+w, y+h], radius=rad, fill=C_BODY, outline=C_BORDER, width=max(1, int(1.3*S)))
    # barra de titulo (degradado) recortada por las esquinas redondeadas
    tmp = Image.new("RGB", (int(w), int(title_h)), C_BODY)
    gradient_rect(tmp, 0, 0, w, title_h, C_TITLE_TOP, C_TITLE_BOT)
    mask = Image.new("L", (int(w), int(title_h)), 0)
    md = ImageDraw.Draw(mask)
    md.rounded_rectangle([0, 0, w, title_h+rad], radius=rad, fill=255)
    md.rectangle([0, title_h-1, w, title_h], fill=255)
    img.paste(tmp, (int(x), int(y)), mask)
    # re-borde de la barra de titulo
    draw.line([(x, y+title_h), (x+w, y+title_h)], fill=C_BORDER, width=max(1, int(1.3*S)))
    draw.rounded_rectangle([x, y, x+w, y+h], radius=rad, outline=C_BORDER, width=max(1, int(1.3*S)))

    cy = y + VPAD
    if stereo:
        draw.text((x + w/2, cy), stereo, font=F_STEREO, fill=C_STEREO, anchor="ma")
        cy += th(F_STEREO) + int(2*S)
    draw.text((x + w/2, cy), name, font=F_NAME, fill=C_NAME, anchor="ma")

    yy = y + title_h
    if has_attr:
        ty = yy + VPAD
        for a in attrs:
            _draw_member(draw, x+PAD, ty, a)
            ty += LH
        yy += attr_h
        if has_meth:
            draw.line([(x, yy), (x+w, yy)], fill=C_BORDER, width=max(1, int(1.0*S)))
    if has_meth:
        ty = yy + VPAD
        for m in methods:
            _draw_member(draw, x+PAD, ty, m)
            ty += LH
    return w, h

def _draw_member(draw, x, y, text):
    """Dibuja un miembro con la visibilidad coloreada."""
    vis = ""
    rest = text
    t = text.strip()
    if t[:1] in "+-#~":
        vis = t[0]
        rest = t[1:].lstrip()
    if vis:
        col = C_VIS_PUB if vis == '+' else (C_VIS_PRI if vis == '-' else C_MEMBER)
        draw.text((x, y), vis, font=F_MEMBER, fill=col, anchor="la")
        vw = tw(draw, vis + " ", F_MEMBER)
        draw.text((x + vw, y), rest, font=F_MEMBER, fill=C_MEMBER, anchor="la")
    else:
        draw.text((x, y), rest, font=F_MEMBER, fill=C_MEMBER, anchor="la")

def draw_actor(draw, cx, top, label):
    """Dibuja un actor (figura de palo) centrado en cx, comenzando en top. Devuelve alto total."""
    head_r = int(11 * S)
    cyc = top + head_r
    draw.ellipse([cx-head_r, cyc-head_r, cx+head_r, cyc+head_r], outline=C_ACTOR, width=max(1, int(1.6*S)), fill=(255, 247, 230))
    body_top = cyc + head_r
    body_bot = body_top + int(26 * S)
    draw.line([(cx, body_top), (cx, body_bot)], fill=C_ACTOR, width=max(1, int(1.6*S)))
    arm_y = body_top + int(8 * S)
    draw.line([(cx-int(15*S), arm_y), (cx+int(15*S), arm_y)], fill=C_ACTOR, width=max(1, int(1.6*S)))
    leg_y = body_bot + int(18 * S)
    draw.line([(cx, body_bot), (cx-int(13*S), leg_y)], fill=C_ACTOR, width=max(1, int(1.6*S)))
    draw.line([(cx, body_bot), (cx+int(13*S), leg_y)], fill=C_ACTOR, width=max(1, int(1.6*S)))
    lbl_y = leg_y + int(6 * S)
    draw.text((cx, lbl_y), label, font=F_ACTOR, fill=(20, 20, 20), anchor="ma")
    return (lbl_y + th(F_ACTOR)) - top, leg_y  # alto total, y de conexion (cintura)

def connector(draw, p0, p1):
    """Conector ortogonal simple entre el lado derecho de una caja y el izquierdo de otra."""
    x0, y0 = p0
    x1, y1 = p1
    midx = (x0 + x1) / 2
    pts = [(x0, y0), (midx, y0), (midx, y1), (x1, y1)]
    draw.line(pts, fill=C_LINE, width=max(1, int(1.2*S)))

def draw_frame(img, draw, W, H, title):
    m = int(8 * S)
    draw.rectangle([m, m, W-m, H-m], outline=C_FRAME, width=max(1, int(1.4*S)))
    # pestana
    tabw = tw(draw, title, F_FRAME) + int(24*S)
    tabh = int(26 * S)
    notch = int(14 * S)
    pts = [(m, m), (m+tabw, m), (m+tabw-notch, m+tabh), (m, m+tabh)]
    draw.polygon(pts, fill=C_FRAME_TAB, outline=C_FRAME)
    draw.text((m+int(10*S), m+tabh/2), title, font=F_FRAME, fill=(40, 40, 40), anchor="lm")

# ----------------------------------------------------------------------------
# Diagrama de analisis de clases
# ----------------------------------------------------------------------------
def render_analisis(d, outpath):
    GAP_COL = int(70 * S)      # separacion entre columnas
    MARGIN  = int(45 * S)
    TOP     = int(55 * S)

    # medir cajas en columnas: [fronteras], [controles], [entidades]
    tmpimg = Image.new("RGB", (10, 10))
    md = ImageDraw.Draw(tmpimg)

    def meas_list(items, kind):
        out = []
        for it in items:
            stereo = {'b':'«boundary»','c':'«control»','e':'«entity»'}[kind]
            attrs = it.get('atributos', []) or []
            meths = it.get('metodos', []) or []
            w, h, *_ = measure_class(md, stereo, it['nombre'], attrs, meths)
            out.append({'it': it, 'stereo': stereo, 'attrs': attrs, 'meths': meths, 'w': w, 'h': h})
        return out

    col_f = meas_list(d['fronteras'], 'b')
    col_c = meas_list(d['controles'], 'c')
    col_e = meas_list(d['entidades'], 'e')

    # ancho de actor
    actor_w = int(90 * S)
    cols = [col_f, col_c, col_e]
    col_w = [max((b['w'] for b in c), default=int(150*S)) for c in cols]

    # x de cada columna
    x_actor = MARGIN
    x_f = x_actor + actor_w + GAP_COL
    x_c = x_f + col_w[0] + GAP_COL
    x_e = x_c + col_w[1] + GAP_COL
    xs = [x_f, x_c, x_e]
    W = int(x_e + col_w[2] + MARGIN)

    GAP_ROW = int(30 * S)
    def stack_height(col):
        if not col: return 0
        return sum(b['h'] for b in col) + GAP_ROW*(len(col)-1)

    actors = d['actores']
    actor_block_h = len(actors) * int(95 * S)
    content_h = max(stack_height(col_f), stack_height(col_c), stack_height(col_e), actor_block_h)
    H = int(TOP + content_h + int(70 * S))

    img = Image.new("RGB", (W, H), C_BG)
    draw = ImageDraw.Draw(img)

    # posicionar y dibujar columnas (centradas verticalmente)
    def place(col, x):
        sh = stack_height(col)
        y = TOP + (content_h - sh)/2
        for b in col:
            b['x'] = x
            b['y'] = y
            b['cy'] = y + b['h']/2
            y += b['h'] + GAP_ROW
        return col

    place(col_f, x_f); place(col_c, x_c); place(col_e, x_e)

    # actores (centrados verticalmente en su bloque)
    actor_y0 = TOP + (content_h - actor_block_h)/2
    actor_conns = []
    cxa = x_actor + actor_w/2
    ay = actor_y0
    for a in actors:
        _, wy = draw_actor(draw, cxa, ay, a)
        actor_conns.append((cxa + int(15*S), wy - int(8*S)))
        ay += int(95 * S)

    # identificar caja de "Casos de Uso" dentro de la columna de control
    casos = [b for b in col_c if 'caso' in b['it']['nombre'].lower()]
    ctrls = [b for b in col_c if 'caso' not in b['it']['nombre'].lower()]
    casos_box = casos[0] if casos else None

    # conectores actor -> primera frontera (todos los actores convergen en la pantalla principal)
    if col_f:
        target = col_f[0]
        for (ax, ayc) in actor_conns:
            connector(draw, (ax, ayc), (target['x'], target['cy']))

    # frontera -> controlador (emparejado por indice si coinciden cantidades)
    if col_f and ctrls:
        if len(col_f) == len(ctrls):
            for f, c in zip(col_f, ctrls):
                connector(draw, (f['x']+f['w'], f['cy']), (c['x'], c['cy']))
        else:
            for f in col_f:
                connector(draw, (f['x']+f['w'], f['cy']), (ctrls[0]['x'], ctrls[0]['cy']))

    if casos_box:
        # cada controlador -> casos de uso
        for c in ctrls:
            connector(draw, (c['x']+c['w'], c['cy']), (casos_box['x'], casos_box['cy']))
        # casos de uso -> cada entidad
        for ent in col_e:
            connector(draw, (casos_box['x']+casos_box['w'], casos_box['cy']), (ent['x'], ent['cy']))
    elif ctrls and col_e:
        # sin caja de casos de uso: ultimo controlador -> cada entidad
        src = ctrls[-1]
        for ent in col_e:
            connector(draw, (src['x']+src['w'], src['cy']), (ent['x'], ent['cy']))
    elif col_f and col_e and not col_c:
        src = col_f[0]
        for ent in col_e:
            connector(draw, (src['x']+src['w'], src['cy']), (ent['x'], ent['cy']))

    # dibujar cajas (encima de las lineas)
    for col in (col_f, col_c, col_e):
        for b in col:
            draw_class(img, draw, b['x'], b['y'], b['stereo'], b['it']['nombre'], b['attrs'], b['meths'])

    title = "class %s %s" % (d['cu'], d['titulo'])
    draw_frame(img, draw, W, H, title)
    img.save(outpath)
    return outpath


# ----------------------------------------------------------------------------
# Diagrama de arquitectura logica (capas)
# ----------------------------------------------------------------------------
def render_arquitectura(d, outpath):
    MARGIN = int(45 * S)
    TOP = int(60 * S)
    layer_pad = int(16 * S)
    comp_h = int(34 * S)
    comp_gap = int(12 * S)
    layer_gap = int(22 * S)
    label_w = int(170 * S)

    capas = d['capas']
    tmpimg = Image.new("RGB", (10, 10)); md = ImageDraw.Draw(tmpimg)

    # disponer componentes en filas dentro de cada capa
    W = int(1500 * S)
    inner_w = W - 2*MARGIN - label_w - layer_pad*2
    def comp_w(text):
        return tw(md, text, F_COMP) + int(28*S)

    layer_layouts = []
    for capa in capas:
        comps = capa['componentes']
        rows = []
        cur = []
        cur_w = 0
        for c in comps:
            cw = comp_w(c)
            if cur and cur_w + cw + comp_gap > inner_w:
                rows.append(cur); cur = []; cur_w = 0
            cur.append((c, cw)); cur_w += cw + comp_gap
        if cur: rows.append(cur)
        lh = layer_pad*2 + len(rows)*comp_h + (len(rows)-1)*comp_gap + int(24*S)
        layer_layouts.append({'capa': capa, 'rows': rows, 'h': lh})

    H = int(TOP + sum(l['h'] for l in layer_layouts) + layer_gap*(len(layer_layouts)-1) + int(60*S))
    img = Image.new("RGB", (W, H), C_BG)
    draw = ImageDraw.Draw(img)

    # paleta de capas (de presentacion a datos)
    pal = [
        (214, 232, 244), (208, 226, 224), (222, 232, 206),
        (244, 236, 212), (236, 224, 240), (230, 230, 236),
        (244, 220, 214), (220, 236, 230),
    ]

    y = TOP
    centers = []
    for i, l in enumerate(layer_layouts):
        capa = l['capa']
        col = pal[i % len(pal)]
        x0 = MARGIN
        x1 = W - MARGIN
        draw.rounded_rectangle([x0, y, x1, y+l['h']], radius=int(8*S), fill=col, outline=C_BORDER, width=max(1,int(1.3*S)))
        # etiqueta de capa (izquierda, vertical-block)
        draw.rectangle([x0, y, x0+label_w, y+l['h']], fill=(255,255,255,0))
        # nombre de capa
        _wrap_text(draw, capa['nombre'], F_LAYER, x0+int(14*S), y+int(14*S), label_w-int(20*S), (30,30,30))
        if capa.get('detalle'):
            _wrap_text(draw, capa['detalle'], F_LABEL, x0+int(14*S), y+int(14*S)+int(40*S), label_w-int(20*S), (90,90,90))
        # componentes
        cx0 = x0 + label_w + layer_pad
        cy = y + layer_pad + int(8*S)
        for row in l['rows']:
            cx = cx0
            for (text, cw) in row:
                draw.rounded_rectangle([cx, cy, cx+cw, cy+comp_h], radius=int(5*S), fill=(255,255,255), outline=(120,120,120), width=max(1,int(1*S)))
                draw.text((cx+cw/2, cy+comp_h/2), text, font=F_COMP, fill=(40,40,40), anchor="mm")
                cx += cw + comp_gap
            cy += comp_h + comp_gap
        centers.append((( x0+x1)/2, y, y+l['h']))
        y += l['h'] + layer_gap

    # flechas entre capas (dependencia hacia abajo)
    for i in range(len(centers)-1):
        cxm, _, yb = centers[i]
        _, yt2, _ = centers[i+1]
        ax = MARGIN + label_w + int(40*S)
        draw.line([(ax, yb), (ax, yt2)], fill=C_LINE, width=max(1,int(1.4*S)))
        # punta de flecha
        draw.polygon([(ax, yt2), (ax-int(5*S), yt2-int(9*S)), (ax+int(5*S), yt2-int(9*S))], fill=C_LINE)

    draw_frame(img, draw, W, H, d.get('titulo', 'Diagrama de Arquitectura Logica'))
    img.save(outpath)
    return outpath

def _wrap_text(draw, text, font, x, y, maxw, color):
    words = text.split()
    line = ""
    yy = y
    for w in words:
        test = (line + " " + w).strip()
        if tw(draw, test, font) > maxw and line:
            draw.text((x, yy), line, font=font, fill=color, anchor="la")
            yy += th(font) + int(2*S)
            line = w
        else:
            line = test
    if line:
        draw.text((x, yy), line, font=font, fill=color, anchor="la")
    return yy + th(font)


# ----------------------------------------------------------------------------
# Diagrama de despliegue (nodos 3D)
# ----------------------------------------------------------------------------
def draw_node3d(draw, x, y, w, h, fill, title, stereo=None, sub=None):
    dep = int(14 * S)
    top = [(x, y), (x+w, y), (x+w+dep, y-dep), (x+dep, y-dep)]
    side = [(x+w, y), (x+w, y+h), (x+w+dep, y+h-dep), (x+w+dep, y-dep)]
    darker = tuple(max(0, c-30) for c in fill)
    draw.polygon(top, fill=tuple(min(255,c+12) for c in fill), outline=C_BORDER)
    draw.polygon(side, fill=darker, outline=C_BORDER)
    draw.rectangle([x, y, x+w, y+h], fill=fill, outline=C_BORDER, width=max(1,int(1.3*S)))
    cy = y + int(10*S)
    if stereo:
        draw.text((x+w/2, cy), stereo, font=F_NODE_S, fill=(70,70,70), anchor="ma")
        cy += th(F_NODE_S) + int(2*S)
    draw.text((x+w/2, cy), title, font=F_NODE, fill=(20,20,20), anchor="ma")

if __name__ == "__main__":
    # prueba rapida con datos de muestra
    sample = {
        "cu": "CU-01", "titulo": "Gestionar Autenticacion y Cuenta",
        "actores": ["Postulante", "Docente", "Coordinador", "Autoridad", "Administrador"],
        "fronteras": [{"nombre": "frmLogin", "atributos": ["email", "password"], "metodos": ["iniciarSesion()", "cerrarSesion()", "recuperar()"]}],
        "controles": [
            {"nombre": "LoginController", "metodos": ["login()", "logout()", "authenticate()"]},
            {"nombre": "Casos de Uso", "metodos": ["loginUser()", "registerUser()", "resetPassword()", "unlockAccount()"]}
        ],
        "entidades": [
            {"nombre": "User", "atributos": ["- id: int", "- email: string", "- passwordHash: string", "- active: bool", "- locked: bool"], "metodos": ["+ hasRole()", "+ hasPermission()", "+ activate()", "+ deactivate()"]},
            {"nombre": "PasswordResetToken", "atributos": ["- id: int", "- token: string", "- expiresAt: datetime"], "metodos": ["+ isExpired()"]}
        ],
    }
    os.makedirs("png", exist_ok=True)
    render_analisis(sample, "png/_test_cu.png")
    print("OK _test_cu.png")
