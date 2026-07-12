#!/usr/bin/env python3
import argparse
import re
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET
import zipfile
from collections import OrderedDict, defaultdict
from pathlib import Path


XLSX_NS = {"a": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}
WORKBOOK_REL_NS = "{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id"
MAX_NAMA_VAR_LEN = 100
MAX_BIAYA_VAR_LEN = 200

CATEGORY_ALIASES = {
    "tea": "Tea Based",
}

NEW_CATEGORY_ICONS = {
    "frappe": "local_cafe",
    "happy hour": "local_offer",
    "minuman botol": "local_drink",
    "paket bundling ramadhan": "lunch_dining",
    "take away": "takeout_dining",
}


def normalize(value: str) -> str:
    return re.sub(r"\s+", " ", (value or "").strip().lower())


def sql_quote(value: str) -> str:
    return "'" + (value or "").replace("\\", "\\\\").replace("'", "''") + "'"


def cell_col_index(cell_ref: str) -> int:
    letters = re.match(r"([A-Z]+)", cell_ref).group(1)
    value = 0
    for char in letters:
        value = value * 26 + (ord(char) - 64)
    return value - 1


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


def pick_price(row: dict[str, str]) -> int:
    for key in ("sell_price", "pos_sell_price", "market_price", "buy_price"):
        raw = (row.get(key) or "").strip()
        if not raw:
            continue
        try:
            value = int(round(float(raw)))
        except ValueError:
            continue
        if value > 0:
            return value
    return 0


def cafe_variant_name(row: dict[str, str]) -> str:
    return ((row.get("variant_names") or "").strip() or (row.get("variant_label") or "").strip())


def carwash_product_name(row: dict[str, str]) -> str:
    name = (row.get("name") or "").strip()
    variant = cafe_variant_name(row)
    if variant:
        return f"{name} - {variant}"
    return name


def mysql_query_lines(cmd_prefix: list[str], sql: str) -> list[str]:
    command = cmd_prefix + ["-N", "-B", "-e", sql]
    output = subprocess.check_output(command, text=True)
    return [line for line in output.splitlines() if line.strip()]


def load_existing_state(cmd_prefix: list[str]):
    categories = OrderedDict()
    for line in mysql_query_lines(
        cmd_prefix,
        "SELECT id_cat, name_cat, icon FROM tb_category ORDER BY id_cat",
    ):
        id_cat, name_cat, icon = line.split("\t")
        categories[normalize(name_cat)] = {
            "id": int(id_cat),
            "name": name_cat,
            "icon": icon,
        }

    cafe_products = defaultdict(list)
    for line in mysql_query_lines(
        cmd_prefix,
        "SELECT id_prod, nama_prod FROM tb_datacafe ORDER BY id_prod",
    ):
        id_prod, name = line.split("\t", 1)
        cafe_products[normalize(name)].append(int(id_prod))

    carwash_products = defaultdict(list)
    for line in mysql_query_lines(
        cmd_prefix,
        "SELECT id_produk, produk FROM tb_datacarwash ORDER BY id_produk",
    ):
        id_produk, name = line.split("\t", 1)
        carwash_products[normalize(name)].append(int(id_produk))

    return categories, cafe_products, carwash_products


def canonical_category(source_name: str, categories: OrderedDict) -> str:
    source_name = (source_name or "").strip()
    normalized = normalize(source_name)
    if normalized in CATEGORY_ALIASES:
        return CATEGORY_ALIASES[normalized]
    if normalized in categories:
        return categories[normalized]["name"]
    return source_name


def build_cafe_products(records: list[dict[str, str]], categories: OrderedDict):
    grouped = OrderedDict()
    for row in records:
        if (row.get("category") or "").strip() == "Dejati Car Wash":
            continue

        name = (row.get("name") or "").strip()
        if not name:
            continue

        category = canonical_category(row.get("category") or "", categories)
        key = (normalize(category), normalize(name))
        grouped.setdefault(
            key,
            {
                "name": name,
                "category": category,
                "rows": [],
            },
        )["rows"].append(row)

    products = []
    for item in grouped.values():
        variant_map = OrderedDict()
        fallback_price = 0

        for row in item["rows"]:
            row_price = pick_price(row)
            if row_price > 0 and fallback_price == 0:
                fallback_price = row_price

            variant_name = cafe_variant_name(row)
            if variant_name:
                variant_map[variant_name] = row_price

        if variant_map:
            nama_var = ";".join(variant_map.keys())
            biaya_var = ";".join(str(value) for value in variant_map.values())

            if len(nama_var) > MAX_NAMA_VAR_LEN or len(biaya_var) > MAX_BIAYA_VAR_LEN:
                for variant_name, variant_price in variant_map.items():
                    products.append(
                        {
                            "name": f"{item['name']} - {variant_name}",
                            "category": item["category"],
                            "variant": 0,
                            "nama_var": None,
                            "biaya_var": None,
                            "biaya": variant_price,
                        }
                    )
            else:
                products.append(
                    {
                        "name": item["name"],
                        "category": item["category"],
                        "variant": 1,
                        "nama_var": nama_var,
                        "biaya_var": biaya_var,
                        "biaya": None,
                    }
                )
        else:
            products.append(
                {
                    "name": item["name"],
                    "category": item["category"],
                    "variant": 0,
                    "nama_var": None,
                    "biaya_var": None,
                    "biaya": fallback_price,
                }
            )

    return products


def build_carwash_products(records: list[dict[str, str]]):
    grouped = OrderedDict()
    for row in records:
        if (row.get("category") or "").strip() != "Dejati Car Wash":
            continue
        name = carwash_product_name(row)
        if not name:
            continue
        grouped[normalize(name)] = {
            "name": name,
            "biaya": pick_price(row),
        }
    return list(grouped.values())


def build_sql(
    cafe_products,
    carwash_products,
    categories,
    existing_cafe,
    existing_carwash,
    updated_by,
):
    statements = []
    created_categories = []
    inserted_cafe = 0
    updated_cafe = 0
    inserted_carwash = 0
    updated_carwash = 0

    known_categories = OrderedDict(categories)
    for product in cafe_products:
        normalized = normalize(product["category"])
        if normalized not in known_categories:
            icon = NEW_CATEGORY_ICONS.get(normalized, "category")
            statements.append(
                "INSERT INTO tb_category (name_cat, icon) "
                f"SELECT {sql_quote(product['category'])}, {sql_quote(icon)} "
                f"WHERE NOT EXISTS (SELECT 1 FROM tb_category WHERE LOWER(TRIM(name_cat)) = {sql_quote(normalized)});"
            )
            known_categories[normalized] = {
                "id": None,
                "name": product["category"],
                "icon": icon,
            }
            created_categories.append(product["category"])

    for product in cafe_products:
        category_lookup = (
            f"(SELECT id_cat FROM tb_category WHERE LOWER(TRIM(name_cat)) = {sql_quote(normalize(product['category']))} LIMIT 1)"
        )
        name_lookup = normalize(product["name"])
        nama_var_sql = "NULL" if product["nama_var"] is None else sql_quote(product["nama_var"])
        biaya_var_sql = "NULL" if product["biaya_var"] is None else sql_quote(product["biaya_var"])
        biaya_sql = "NULL" if product["biaya"] is None else str(product["biaya"])
        ids = existing_cafe.get(name_lookup, [])

        if ids:
            for id_prod in ids:
                statements.append(
                    "UPDATE tb_datacafe SET "
                    f"nama_prod = {sql_quote(product['name'])}, "
                    f"id_cat = {category_lookup}, "
                    f"variant = {product['variant']}, "
                    f"nama_var = {nama_var_sql}, "
                    f"biaya_var = {biaya_var_sql}, "
                    f"biaya = {biaya_sql}, "
                    "updated_at = NOW(), "
                    f"updated_by = {updated_by} "
                    f"WHERE id_prod = {id_prod};"
                )
            updated_cafe += len(ids)
        else:
            statements.append(
                "INSERT INTO tb_datacafe "
                "(nama_prod, id_cat, variant, nama_var, biaya_var, biaya, foto, updated_at, updated_by) "
                "SELECT "
                f"{sql_quote(product['name'])}, "
                f"{category_lookup}, "
                f"{product['variant']}, "
                f"{nama_var_sql}, "
                f"{biaya_var_sql}, "
                f"{biaya_sql}, "
                "NULL, NOW(), "
                f"{updated_by};"
            )
            inserted_cafe += 1

    for product in carwash_products:
        name_lookup = normalize(product["name"])
        ids = existing_carwash.get(name_lookup, [])
        if ids:
            for id_produk in ids:
                statements.append(
                    "UPDATE tb_datacarwash SET "
                    f"produk = {sql_quote(product['name'])}, "
                    f"biaya = {product['biaya']}, "
                    "updated_at = NOW(), "
                    f"updated_by = {updated_by} "
                    f"WHERE id_produk = {id_produk};"
                )
            updated_carwash += len(ids)
        else:
            statements.append(
                "INSERT INTO tb_datacarwash (produk, biaya, updated_at, updated_by) "
                f"VALUES ({sql_quote(product['name'])}, {product['biaya']}, NOW(), {updated_by});"
            )
            inserted_carwash += 1

    return {
        "sql": "\n".join(statements) + "\n",
        "created_categories": created_categories,
        "inserted_cafe": inserted_cafe,
        "updated_cafe": updated_cafe,
        "inserted_carwash": inserted_carwash,
        "updated_carwash": updated_carwash,
        "distinct_cafe": len(cafe_products),
        "distinct_carwash": len(carwash_products),
    }


def run_sql(cmd_prefix: list[str], sql: str):
    with tempfile.NamedTemporaryFile("w", suffix=".sql", delete=False) as handle:
        handle.write(sql)
        temp_path = Path(handle.name)

    try:
        subprocess.run(
            cmd_prefix + ["<", str(temp_path)],
            check=True,
            shell=False,
        )
    finally:
        temp_path.unlink(missing_ok=True)


def run_sql_via_shell(sql_file: Path):
    command = (
        "docker exec -i lampp_db mysql -uroot -pakpidev3 -D dejati < "
        + str(sql_file)
    )
    subprocess.run(command, shell=True, check=True)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--xlsx",
        default="/home/relia/automated-cafe/www/data-from-olsera/product-1_1000-2026-07-12__.xlsx",
    )
    parser.add_argument("--updated-by", type=int, default=1)
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    xlsx_path = Path(args.xlsx)
    if not xlsx_path.exists():
        print(f"File tidak ditemukan: {xlsx_path}", file=sys.stderr)
        sys.exit(1)

    mysql_cmd_prefix = ["docker", "exec", "-i", "lampp_db", "mysql", "-uroot", "-pakpidev3", "-D", "dejati"]

    records = parse_xlsx(xlsx_path)
    categories, existing_cafe, existing_carwash = load_existing_state(mysql_cmd_prefix)
    cafe_products = build_cafe_products(records, categories)
    carwash_products = build_carwash_products(records)

    result = build_sql(
        cafe_products=cafe_products,
        carwash_products=carwash_products,
        categories=categories,
        existing_cafe=existing_cafe,
        existing_carwash=existing_carwash,
        updated_by=args.updated_by,
    )

    print(f"Workbook rows           : {len(records)}")
    print(f"Cafe distinct products  : {result['distinct_cafe']}")
    print(f"Carwash distinct items  : {result['distinct_carwash']}")
    print(f"New categories          : {len(result['created_categories'])}")
    print(f"Cafe updates            : {result['updated_cafe']}")
    print(f"Cafe inserts            : {result['inserted_cafe']}")
    print(f"Carwash updates         : {result['updated_carwash']}")
    print(f"Carwash inserts         : {result['inserted_carwash']}")

    if result["created_categories"]:
        print("Created category names  : " + ", ".join(result["created_categories"]))

    if args.dry_run:
        return

    with tempfile.NamedTemporaryFile("w", suffix=".sql", delete=False) as handle:
        handle.write(result["sql"])
        sql_file = Path(handle.name)

    try:
        run_sql_via_shell(sql_file)
    finally:
        sql_file.unlink(missing_ok=True)


if __name__ == "__main__":
    main()
