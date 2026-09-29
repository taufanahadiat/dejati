#!/usr/bin/env python3
"""Read Olsera's SKU XLSX using only the Python standard library."""
import json
import re
import sys
import zipfile
import xml.etree.ElementTree as ET
from decimal import Decimal, InvalidOperation
from pathlib import PurePosixPath

NS = {'s': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
FIELDS = {'product': 'product', 'variant': 'variant', 'group': 'group', 'sku': 'sku',
          'currency': 'currency', 'quantity': 'sold qty', 'gross_sales': 'subtotal sales amount',
          'discount_amount': 'discount item amount', 'return_amount': 'total sales return',
          'total_sales': 'total sales amount'}
NUMBERS = {'quantity', 'gross_sales', 'discount_amount', 'return_amount', 'total_sales'}


def parse_xlsx(path):
    with zipfile.ZipFile(path) as z:
        if sum(i.file_size for i in z.infolist()) > 40_000_000:
            raise ValueError('Excel terlalu besar')
        strings = []
        if 'xl/sharedStrings.xml' in z.namelist():
            strings = [''.join(t.text or '' for t in si.findall('.//s:t', NS))
                       for si in ET.fromstring(z.read('xl/sharedStrings.xml'))]
        wb = ET.fromstring(z.read('xl/workbook.xml'))
        sheet = wb.find('s:sheets', NS)[0]
        rel_id = sheet.get('{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id')
        rels = ET.fromstring(z.read('xl/_rels/workbook.xml.rels'))
        target = next(r.get('Target') for r in rels if r.get('Id') == rel_id)
        target = target.lstrip('/') if target.startswith('/') else str(PurePosixPath('xl') / target)
        if '..' in PurePosixPath(target).parts:
            raise ValueError('Lokasi sheet tidak valid')
        root = ET.fromstring(z.read(target))
        raw = []
        for row in root.findall('s:sheetData/s:row', NS):
            values = {}
            for cell in row.findall('s:c', NS):
                col = re.match(r'[A-Z]+', cell.get('r', '')).group()
                if cell.find('s:f', NS) is not None:
                    raise ValueError('Formula tidak diizinkan dalam laporan SKU')
                v = cell.find('s:v', NS)
                value = '' if v is None else v.text or ''
                if cell.get('t') == 's':
                    value = strings[int(value)]
                elif cell.get('t') == 'inlineStr':
                    value = ''.join(t.text or '' for t in cell.findall('.//s:t', NS))
                elif cell.get('t') == 'e':
                    raise ValueError('Excel memuat error sel')
                values[col] = value
            if any(str(v).strip() for v in values.values()):
                raw.append(values)
    if not raw:
        raise ValueError('Header Excel tidak ditemukan')
    header = {v.strip().lower(): k for k, v in raw[0].items()}
    if not set(FIELDS.values()).issubset(header):
        raise ValueError('Kolom Excel Olsera berubah atau laporan bukan penjualan SKU')
    records = []
    for source in raw[1:]:
        record = {}
        for key, name in FIELDS.items():
            value = source.get(header[name], '').strip()
            if key in NUMBERS:
                try:
                    number = Decimal(value)
                except InvalidOperation as exc:
                    raise ValueError('Nilai angka tidak valid: ' + name) from exc
                if not number.is_finite():
                    raise ValueError('Nilai angka tidak finite')
                value = str(number)
            record[key] = value
        if not record['product']:
            raise ValueError('Ada baris tanpa nama produk')
        records.append(record)
    return records


if __name__ == '__main__':
    try:
        print(json.dumps(parse_xlsx(sys.argv[1]), ensure_ascii=False))
    except Exception as exc:
        print(str(exc), file=sys.stderr)
        sys.exit(1)
