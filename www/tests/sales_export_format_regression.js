// Run after sales_export_regression.php; copy /tmp/sales-export-fixture.json from the PHP container.
// node tests/sales_export_format_regression.js /tmp/sales-export-fixture.json
const fs = require('fs');
const assert = require('assert');
const pdfMake = require('../plugins/pdfmake/pdfmake.js');
pdfMake.vfs = require('../plugins/pdfmake/vfs_fonts.js').pdfMake.vfs;
global.window = global;
global.ExcelJS = require('../dist/js/exceljs.min.js');
require('../include/report/export.js');
(async function () {
    const report = JSON.parse(fs.readFileSync(process.argv[2] || '/tmp/sales-export-fixture.json'));
    const blob = await SalesReportExport.excelBlob(report);
    const bytes = Buffer.from(await blob.arrayBuffer());
    const workbook = new ExcelJS.Workbook();
    await workbook.xlsx.load(bytes);
    let numericTotal = false, safeName = false;
    workbook.worksheets[0].eachRow(row => row.eachCell(cell => {
        if (cell.value === 74000 && cell.type === ExcelJS.ValueType.Number) numericTotal = true;
        if (cell.value === '=Coffee & Tea' && cell.type === ExcelJS.ValueType.String) safeName = true;
        assert(cell.type !== ExcelJS.ValueType.Formula, 'Product text never becomes formulas');
    }));
    assert(numericTotal, 'Excel stores actual numeric amounts');
    assert(safeName, 'Product name remains plain text');
    const definition = SalesReportExport.pdfDefinition(report);
    assert(definition.content.some(row => row.text === 'Ringkasan Metode Pembayaran'));
    const pdf = await new Promise(resolve => pdfMake.createPdf(definition).getBuffer(resolve));
    assert(Buffer.from(pdf).subarray(0, 5).toString() === '%PDF-');
    fs.writeFileSync('/tmp/sales-export-test.xlsx', bytes);
    fs.writeFileSync('/tmp/sales-export-test.pdf', pdf);
    console.log('PASS: numeric Excel cells, formula-safe names, and real PDF/XLSX generation');
})().catch(error => { console.error(error); process.exit(1); });
