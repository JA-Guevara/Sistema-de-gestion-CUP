# -*- coding: utf-8 -*-
"""Parser determinista de entidades Doctrine -> modelo (clases + esquema) fiel al codigo."""
import re, json, glob, os

ROOT = r"D:\Uagrm\Sistema-de-gestion-CUP\src"

def find_entity_files():
    files = []
    for p in glob.glob(os.path.join(ROOT, "**", "*.php"), recursive=True):
        try:
            txt = open(p, encoding="utf-8").read()
        except Exception:
            continue
        if "#[ORM\\Entity" in txt and "class " in txt:
            files.append((p, txt))
    return files

PHP2SQL = {
    "int": "INTEGER", "?int": "INTEGER", "string": "VARCHAR", "?string": "VARCHAR",
    "bool": "BOOLEAN", "?bool": "BOOLEAN", "float": "DOUBLE PRECISION", "?float": "DOUBLE PRECISION",
    "\\DateTimeImmutable": "TIMESTAMP", "?\\DateTimeImmutable": "TIMESTAMP",
    "DateTimeImmutable": "TIMESTAMP", "?DateTimeImmutable": "TIMESTAMP",
}
ORM2SQL = {
    "string": "VARCHAR", "text": "TEXT", "integer": "INTEGER", "smallint": "SMALLINT",
    "bigint": "BIGINT", "boolean": "BOOLEAN", "float": "DOUBLE PRECISION", "decimal": "NUMERIC",
    "datetime_immutable": "TIMESTAMP", "datetime": "TIMESTAMP", "date_immutable": "DATE",
    "json": "JSONB", "jsonb": "JSONB",
}

def sql_type(php_type, orm_type, length):
    if orm_type:
        base = ORM2SQL.get(orm_type.lower())
        if base == "VARCHAR" and length:
            return f"VARCHAR({length})"
        if base:
            return base
    base = PHP2SQL.get(php_type, None)
    if base == "VARCHAR":
        return f"VARCHAR({length})" if length else "VARCHAR(255)"
    return base or "VARCHAR(255)"

def parse_entity(path, txt):
    cls = re.search(r"\bclass\s+(\w+)", txt)
    if not cls:
        return None
    name = cls.group(1)
    tbl = re.search(r"#\[ORM\\Table\(name:\s*'([^']+)'", txt)
    table = tbl.group(1) if tbl else name.lower()

    lines = txt.splitlines()
    fields = []   # {name, php, sql, nullable, length, pk, unique, kind}
    rels = []     # {kind, target, prop, owner, cardinality, join, nullable, mappedBy, joinTable}
    methods = []
    pending = []  # ORM attribute lines accumulated

    def flush_attrs():
        s = " ".join(pending)
        pending.clear()
        return s

    for ln in lines:
        s = ln.strip()
        if s.startswith("#[ORM\\") or s.startswith("#[ORM\\") or s.startswith("/**") or s.startswith("*") or s.startswith("*/"):
            if s.startswith("#["):
                pending.append(s)
            continue
        # propiedad
        mprop = re.match(r"(public|protected|private)\s+(readonly\s+)?([\\?\w]+)\s+\$(\w+)", s)
        if mprop and "function" not in s:
            attrs = flush_attrs()
            vis, _, ptype, pname = mprop.group(1), mprop.group(2), mprop.group(3), mprop.group(4)
            is_id = "#[ORM\\Id]" in attrs
            # relaciones
            rel = None
            for kind, card in [("ManyToOne", "*..1"), ("OneToMany", "1..*"), ("ManyToMany", "*..*"), ("OneToOne", "1..1")]:
                if f"ORM\\{kind}" in attrs:
                    tgt = re.search(r"targetEntity:\s*([\w]+)::class", attrs)
                    mapped = re.search(r"mappedBy:\s*'(\w+)'", attrs)
                    jt = re.search(r"JoinTable\(name:\s*'([^']+)'", attrs)
                    nn = "nullable: false" in attrs
                    rel = {"kind": kind, "card": card, "target": tgt.group(1) if tgt else "?",
                           "prop": pname, "owner": kind in ("ManyToOne", "ManyToMany", "OneToOne") and "mappedBy" not in attrs,
                           "nullable": not nn, "mappedBy": mapped.group(1) if mapped else None,
                           "joinTable": jt.group(1) if jt else None}
                    break
            if rel:
                rels.append(rel)
            elif "ORM\\Column" in attrs or is_id:
                col = re.search(r"type:\s*'(\w+)'", attrs)
                length = re.search(r"length:\s*(\d+)", attrs)
                nullable = "nullable: true" in attrs
                unique = "unique: true" in attrs
                L = length.group(1) if length else None
                fields.append({
                    "name": pname, "php": ptype, "sql": sql_type(ptype, col.group(1) if col else None, L),
                    "nullable": nullable and not is_id, "length": L, "pk": is_id, "unique": unique,
                })
            continue
        # metodo publico
        mfun = re.match(r"public\s+function\s+(\w+)\s*\(([^)]*)\)\s*:?\s*([\\?\w]+)?", s)
        if mfun and mfun.group(1) != "__construct":
            ret = mfun.group(3) or ""
            methods.append(f"+ {mfun.group(1)}(): {ret}".rstrip(": "))
            pending.clear()
            continue
        if s and not s.startswith("//") and not s.startswith("use "):
            pending.clear()

    return {"name": name, "table": table, "path": path.replace("\\", "/").split("src/")[-1],
            "fields": fields, "rels": rels, "methods": methods}

def main():
    ents = []
    for p, txt in find_entity_files():
        e = parse_entity(p, txt)
        if e:
            ents.append(e)
    ents.sort(key=lambda e: e["name"])
    os.makedirs("data", exist_ok=True)
    json.dump(ents, open("data/modelo.json", "w", encoding="utf-8"), ensure_ascii=False, indent=2)
    print(f"Entidades: {len(ents)}")
    for e in ents:
        nrel = len(e["rels"])
        print(f"  {e['name']:24s} tabla={e['table']:28s} campos={len(e['fields']):2d} rels={nrel} metodos={len(e['methods'])}")

if __name__ == "__main__":
    main()
