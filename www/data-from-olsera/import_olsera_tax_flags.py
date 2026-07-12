#!/usr/bin/env python3
import html
import re
import subprocess
import tempfile
import xml.etree.ElementTree as ET
import zipfile
from collections import OrderedDict
from pathlib import Path


XLSX_NS = {"a": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}
WORKBOOK_REL_NS = "{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id"
XLSX_PATH = Path("/home/relia/automated-cafe/www/data-from-olsera/product-1_1000-2026-07-12__.xlsx")

NAME_ALIASES = {
    "ricebowlbeef": "RICE BOWL BEEF TERIAKY",
    "ricebowlchicken": "RICE BOWL CHIKEN TERIAKY",
    "paketigabakar": "PAKET IGA BAKAR 01",
    "miegorengdejati": "MIE GORENG SPESIAL DE'JATI",
    "nasigoreng": "NASI GORENG SPESIAL",
    "mixsnack": "MIX SNACK 1",
    "miegodog": "MIE GODOG DE'JATI",
    "latteoriginal": "COFFEE LATTE ORIGINAL",
    "mintlatte": "MATCHA MINT LATTE",
    "milkshake": "VANILLA MILKSHAKE",
    "pakethematayambakariceteamineral": "Paket Hrmat ayam bakar + Ice tea/mineral",
    "dejatisignaturebeli3lebihhemat": "DE'JATI SIGNATURE \nBELI 3 LEBIH HEMAT",
    "dejatisignaturenbeli3lebihhemat": "DE'JATI SIGNATURE \nBELI 3 LEBIH HEMAT",
    "yogurtpunch": "YOGHURT PUNCH",
    "coffeemocktail": "COFFE MOCKTAIL",
    "caramelmacchiato": "CARAMEL MACHIATO",
    "telurmatasapi": "TELUH MATA SAPI",
}


def normalize(value: str) -> str:
    value = html.unescape((value or "").strip().lower())
    value = re.sub(r"\s+", " ", value)
    return value


def slugify(value: str) -> str:
    value = html.unescape((value or "").strip().lower())
    value = re.sub(r"[^a-z0-9]+", "", value)
    return value


def sql_quote(value: str) -> str:
    return "'" + (value or "").replace("\\", "\\\\").replace("'", "''") + "'"


def cell_col_index(cell_ref: str) -> int:
    letters = re.match(r"([A-Z]+)", cell_ref).group(1)
    result = 0
    for char in letters:
        result = result * 26 + (ord(char) - 64)
    return result - 1


def parse_xlsx(path: Path) -> list[dict[str, str]]:
    with zipfile.ZipFile(path) as archive:
        shared_strings = []
        if "xl/sharedStrings.xml" in archive.namelist():
            root = ET.fromstring(archive.read("xl/sharedStrings.xml"))
            for item in root.findall("a:si", XLSX_NS):
                shared_strings.append(
                    "".join(text.text or "" for text in item.iterfind(".//a:t", XLSX_NS))
                )

        workbook = ET.fromstring(archive.read("xl/workbook.xml"))
        rels = ET.fromstring(archive.read("xl/_rels/workbook.xml.rels"))
        rel_map = {rel.attrib["Id"]: rel.attrib["Target"] for rel in rels}
        first_sheet = workbook.find("a:sheets", XLSX_NS)[0]
        sheet_path = "xl/" + rel_map[first_sheet.attrib[WORKBOOK_REL_NS]]
        sheet = ET.fromstring(archive.read(sheet_path))

        sparse_rows = []
        max_cols = 0
        for row in sheet.findall(".//a:sheetData/a:row", XLSX_NS):
            values = {}
            for cell in row.findall("a:c", XLSX_NS):
                idx = cell_col_index(cell.attrib["r"])
                max_cols = max(max_cols, idx + 1)
                raw_value = cell.find("a:v", XLSX_NS)
                value = ""
                if raw_value is not None:
                    value = raw_value.text or ""
                    if cell.attrib.get("t") == "s":
                        value = shared_strings[int(value)]
                values[idx] = value
            sparse_rows.append(values)

    rows = [[row.get(i, "") for i in range(max_cols)] for row in sparse_rows]
    headers = rows[0]
    return [dict(zip(headers, row)) for row in rows[1:]]


def build_tax_map(records: list[dict[str, str]]):
    tax_map = OrderedDict()
    for row in records:
        if normalize(row.get("category", "")) == "dejati car wash":
            continue
        name = (row.get("name") or "").strip()
        if not name:
            continue
        tax_map[slugify(name)] = {
            "name": name,
            "tax_free_item": 1 if normalize(row.get("tax_free_item", "")) == "yes" else 0,
            "non_service_charge": 1 if normalize(row.get("non_service_charge", "")) == "yes" else 0,
        }
    return tax_map


def load_db_rows() -> list[dict[str, str]]:
    cmd = [
        "docker", "exec", "-i", "lampp_db",
        "mysql", "-N", "-B", "-uroot", "-pakpidev3", "-D", "dejati",
        "-e", "SELECT id_prod, nama_prod FROM tb_datacafe ORDER BY id_prod",
    ]
    output = subprocess.check_output(cmd, text=True)
    rows = []
    for line in output.splitlines():
        id_prod, nama_prod = line.split("\t", 1)
        rows.append({"id_prod": int(id_prod), "nama_prod": nama_prod})
    return rows


def build_updates(db_rows, tax_map):
    statements = []
    matched = 0
    unmatched = []

    for row in db_rows:
        db_name = row["nama_prod"]
        direct_key = slugify(db_name)
        matched_tax = tax_map.get(direct_key)

        if matched_tax is None and direct_key in NAME_ALIASES:
            matched_tax = tax_map.get(slugify(NAME_ALIASES[direct_key]))

        if matched_tax is None:
            for key, tax_data in tax_map.items():
                prefix = slugify(tax_data["name"])
                if direct_key.startswith(prefix) and db_name.lower().startswith(tax_data["name"].lower() + " -"):
                    matched_tax = tax_data
                    break

        if matched_tax is None:
            unmatched.append(db_name)
            continue

        statements.append(
            "UPDATE tb_datacafe SET "
            f"tax_free_item = {matched_tax['tax_free_item']}, "
            f"non_service_charge = {matched_tax['non_service_charge']} "
            f"WHERE id_prod = {row['id_prod']};"
        )
        matched += 1

    return statements, matched, unmatched


def main():
    records = parse_xlsx(XLSX_PATH)
    tax_map = build_tax_map(records)
    db_rows = load_db_rows()
    statements, matched, unmatched = build_updates(db_rows, tax_map)

    print(f"DB cafe rows          : {len(db_rows)}")
    print(f"Tax rows from Olsera  : {len(tax_map)}")
    print(f"Matched rows          : {matched}")
    print(f"Unmatched rows        : {len(unmatched)}")

    if unmatched:
        print("Sample unmatched      : " + ", ".join(unmatched[:10]))

    if not statements:
        return

    with tempfile.NamedTemporaryFile("w", suffix=".sql", delete=False) as handle:
        handle.write("\n".join(statements) + "\n")
        sql_file = Path(handle.name)

    try:
        command = "docker exec -i lampp_db mysql -uroot -pakpidev3 -D dejati < " + str(sql_file)
        subprocess.run(command, shell=True, check=True)
    finally:
        sql_file.unlink(missing_ok=True)


if __name__ == "__main__":
    main()
