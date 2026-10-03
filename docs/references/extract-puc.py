#!/usr/bin/env python3
"""Extract the PUC catálogo (Decreto 2650 de 1993, Art. 6) from the reference PDF into the ledger's seed CSV.

    pdftotext -layout docs/references/PUC-Decreto-2650-1993.pdf /tmp/puc.txt
    python3 docs/references/extract-puc.py /tmp/puc.txt > backend/src/Ledger/Infrastructure/Seed/puc.csv

Reads the "CATALOGO DE CUENTAS" chapter only (up to "DESCRIPCIONES Y DINAMICAS"): every class (1 digit), group (2),
cuenta (4) and subcuenta (6). Names wrapped over two lines are joined; the decree's margin notes ("ADICIONADA D.R.…",
"REDENOMINADA…") are dropped, and accounts marked "ELIMINADA" are left out. Free ranges ("240801 a 240898") are not
accounts: the company creates its own there. Nature: débito for classes 1, 5, 6, 7, 8 and crédito for 2, 3, 4, 9,
unless the account or an ancestor is marked (DB) or (CR).

What this copy of the decree gets wrong is corrected by hand, from the official text: the name of 4175 (renamed by
D.R. 2894/94, RENAMED); group 32, cuenta 3205 with its first two subcuentas, and cuentas 3135 and 3140, which the
PDF lost (MISSING, checked against the cuentas named in the descripciones y dinámicas chapter); and the
"(CR)" of the class-1 contra accounts (provisiones, depreciación, amortización and agotamiento acumulados), which the
PDF drops (CONTRA).
"""
import csv
import re
import sys

NOTE = re.compile(r'(ADICIONAD|Adicionad|REDENOMINAD|Redenominad|REPLANTEAD|Nueva denominaci|ELIMINAD|RECODIFICAD|'
                  r'D\.R\.?\s?\d|Decreto Reglamentario)')
RENAMED = {'4175': 'DEVOLUCIONES, REBAJAS Y DESCUENTOS EN VENTAS (DB)'}
MISSING = {
    '32': 'SUPERAVIT DE CAPITAL',
    '3205': 'PRIMA EN COLOCACION DE ACCIONES, CUOTAS O PARTES DE INTERES SOCIAL',
    '320505': 'PRIMA EN COLOCACION DE ACCIONES',
    '320510': 'PRIMA EN COLOCACION DE ACCIONES POR COBRAR (DB)',
    '3135': 'APORTES DEL ESTADO',
    '3140': 'FONDO SOCIAL',
}
CONTRA = {'1299', '1399', '1499', '1592', '1597', '1598', '1599', '1698', '1699', '1798', '1899'}


def main(path: str) -> None:
    text = open(path, encoding='utf-8').read()
    start = text.index('CATALOGO DE CUENTAS')
    end = text.index('DESCRIPCIONES Y DINAMICAS')
    lines = text[start:end].splitlines()[1:]

    accounts: dict[str, dict] = {}
    order: list[str] = []
    last = None
    for raw in lines:
        # Two entries the PDF glued together ("…FINANCIERAS125095OTROS").
        raw = re.sub(r'(?<=[A-Z)])(\d{6})(?=[A-Z])', r'\n\1   ', raw)
        for line in raw.split('\n'):
            line = re.sub(r'[\uf000-\uf8ff•]', '', line).rstrip()
            if not line.strip() or line.strip().startswith('CODIGO'):
                continue
            m = re.match(r'^\s*(\d{1,6})(?:\s+(.*))?$', line)
            if m:
                code, rest = m.group(1), (m.group(2) or '').strip()
                # "417501 a" or "236701 A    ADICIONADA…", not "2310 A CASA MATRIZ"
                if rest == '' or rest in ('a', 'A') or (rest[0] in 'aA' and NOTE.match(rest[1:].strip())):
                    last = None  # a free range ("417501 a" / "417598") or a page number
                    continue
                note = NOTE.search(rest)
                name = rest[: note.start()].strip() if note else rest
                if code in accounts:  # listed twice where the decree replanted a cuenta (144599, 145599)
                    if accounts[code]['name'] != name:
                        raise SystemExit(f'code {code} listed twice: {accounts[code]["name"]!r} vs {name!r}')
                    last = None
                    continue
                accounts[code] = {'name': name, 'eliminated': bool(note and 'ELIMINAD' in rest.upper())}
                order.append(code)
                last = code
                continue
            if last is None:
                continue
            note = NOTE.search(line)
            if note:
                if 'ELIMINAD' in line.upper():
                    accounts[last]['eliminated'] = True
                before = line[: note.start()].strip()
                if before:
                    accounts[last]['name'] += ' ' + before
                continue
            accounts[last]['name'] += ' ' + line.strip()

    for code, name in MISSING.items():
        if code in accounts:
            raise SystemExit(f'{code} is no longer missing: drop it from MISSING')
        accounts[code] = {'name': name, 'eliminated': False}
        order.append(code)
    kept = [c for c in order if not accounts[c]['eliminated']]
    out = csv.writer(sys.stdout, lineterminator='\n')
    out.writerow(['code', 'name', 'nature'])
    for code in sorted(kept):
        name = RENAMED.get(code, re.sub(r'\s+', ' ', accounts[code]['name']).strip())
        nature = 'debit' if code[0] in '15678' else 'credit'
        if code[:4] in CONTRA:
            nature = 'credit'
        for prefix in (code[:n] for n in (1, 2, 4, 6) if n <= len(code)):
            marked = RENAMED.get(prefix, accounts.get(prefix, {}).get('name', ''))
            if '(DB)' in marked:
                nature = 'debit'
            elif '(CR)' in marked:
                nature = 'credit'
        out.writerow([code, name, nature])


if __name__ == '__main__':
    main(sys.argv[1])
