/* global ExcelJS, pdfMake, flatpickr, jQuery */
(function (root) {
    'use strict';
    async function excelBlob(report) {
        const workbook = new ExcelJS.Workbook();
        workbook.creator = 'Dejati';
        const sheet = workbook.addWorksheet('Laporan Penjualan', {
            pageSetup: {orientation: 'landscape', paperSize: 9, fitToPage: true, fitToWidth: 1, fitToHeight: 0}
        });
        const maxColumns = Math.max(...report.sections.map(section => section.columns.length), 1);
        sheet.columns = Array.from({length: maxColumns}, () => ({width: 22}));
        function heading(text, size = 11, color = 'FFFFFF') {
            const row = sheet.addRow([text]);
            sheet.mergeCells(row.number, 1, row.number, maxColumns);
            row.font = {name: 'Calibri', size, bold: size > 11};
            row.alignment = {vertical: 'middle', wrapText: true};
            row.getCell(1).fill = {type: 'pattern', pattern: 'solid', fgColor: {argb: 'FF' + color}};
            row.height = text.length > 120 ? 34 : 24;
        }
        heading(report.title, 18);
        heading(`Periode: ${report.start} s/d ${report.end}`);
        heading(`Layanan: ${report.divisions.join(', ')}`);
        sheet.addRow([]);
        report.sections.forEach(section => {
            heading(section.title, 13, 'E9ECEF');
            const header = sheet.addRow(section.columns);
            header.font = {name: 'Calibri', size: 11, bold: true};
            header.alignment = {wrapText: true, vertical: 'middle'};
            header.height = 30;
            if (!section.rows.length) heading('Tidak ada data untuk pilihan ini.');
            section.rows.forEach(values => {
                // ExcelJS writes plain strings as text, including names starting with '='.
                const row = sheet.addRow(values);
                row.font = {name: 'Calibri', size: 11, bold: values[0] === 'Subtotal'};
                row.alignment = {wrapText: true, vertical: 'top'};
                const longest = Math.max(...values.map(value => typeof value === 'string' ? value.length : 0));
                row.height = Math.min(180, Math.max(30, Math.ceil(longest / 22) * 15));
                section.money.forEach(index => { row.getCell(index + 1).numFmt = '#,##0;[Red]-#,##0'; });
            });
            sheet.addRow([]);
        });
        const buffer = await workbook.xlsx.writeBuffer();
        return new Blob([buffer], {type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
    }
    function pdfDefinition(report) {
        const content = [
            {text: report.title, style: 'title'},
            {text: `Periode: ${report.start} s/d ${report.end} | Layanan: ${report.divisions.join(', ')}`, margin: [0, 0, 0, 8]}
        ];
        report.sections.forEach(section => {
            content.push({text: section.title, style: 'section', headlineLevel: 1});
            if (!section.rows.length) {
                content.push({text: 'Tidak ada data untuk pilihan ini.', italics: true});
                return;
            }
            const body = [section.columns.map(text => ({text, bold: true, fillColor: '#e9ecef'}))];
            section.rows.forEach(row => body.push(row.map((value, i) => ({
                text: typeof value === 'number' && section.money.includes(i) ? value.toLocaleString('id-ID') : String(value),
                alignment: typeof value === 'number' ? 'right' : 'left', bold: row[0] === 'Subtotal'
            }))));
            content.push({table: {headerRows: 1, widths: section.columns.map(() => '*'), body},
                fontSize: section.columns.length > 10 ? 7 : 8, layout: 'lightHorizontalLines'});
        });
        return {pageSize: 'A4', pageOrientation: 'landscape', pageMargins: [25, 25, 25, 30],
            defaultStyle: {font: 'Roboto', fontSize: 9}, content,
            styles: {title: {fontSize: 17, bold: true, margin: [0, 0, 0, 8]}, section: {fontSize: 11, bold: true, margin: [0, 14, 0, 6]}},
            footer: (current, total) => ({text: `${current} / ${total}`, alignment: 'right', margin: [0, 0, 25, 0], fontSize: 8}),
            pageBreakBefore: (node, following) => node.headlineLevel === 1 && following.length === 0};
    }
    function download(blob, filename) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = filename;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 60000);
    }
    function init(start, end) {
        const $ = jQuery;
        let format = 'excel', busy = false;
        const dateString = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        const picker = flatpickr('#exportDateRange', {mode: 'range', dateFormat: 'Y-m-d', altInput: true,
            altFormat: 'd M Y', defaultDate: [start, end], locale: {firstDayOfWeek: 1, rangeSeparator: ' — '},
            static: true,
            onReady: function(dates, text, instance) { instance.altInput.parentNode.classList.add('d-block'); },
            // Keep a single-date selection when focus moves to the Export button.
            onClose: function(dates, text, instance) {
                if (dates.length === 1) instance.setDate([dates[0], dates[0]], false);
            }});
        $('.export-report').on('click', function() {
            format = $(this).data('format');
            picker.setDate([start, end], false);
            $('#exportReportTitle').text('Export ' + (format === 'pdf' ? 'PDF' : 'Excel'));
            $('#exportReportError').addClass('d-none').text('');
            $('#exportReportModal').modal('show');
        });
        $('#exportReportModal').on('hide.bs.modal', function(event) { if (busy) event.preventDefault(); });
        $('#exportReportForm').on('submit', async function(event) {
            event.preventDefault(); if (busy) return;
            const dates = picker.selectedDates;
            const divisions = $('.export-division:checked').map(function() {return this.value;}).get();
            const error = $('#exportReportError').addClass('d-none').text('');
            if (!dates.length || !divisions.length) {
                error.text('Pilih tanggal dan minimal satu layanan.').removeClass('d-none'); return;
            }
            busy = true;
            $('#exportReportForm :input, #exportReportModal .close').prop('disabled', true);
            $('#exportReportProgress').removeClass('d-none');
            try {
                const report = await $.ajax({url: '/include/report/export_data', dataType: 'json', data: {
                    start: dateString(dates[0]), end: dateString(dates[1] || dates[0]),
                    type: $('#exportReportType').val(), divisions
                }});
                const filename = `Penjualan_${$('#exportReportType').val()}_${report.start}_${report.end}`;
                if (format === 'excel') download(await excelBlob(report), filename + '.xlsx');
                else {
                    const blob = await new Promise(resolve => pdfMake.createPdf(pdfDefinition(report)).getBlob(resolve));
                    download(blob, filename + '.pdf');
                }
                busy = false;
                $('#exportReportModal').modal('hide');
            } catch (e) {
                error.text(e.responseJSON?.message || e.message || 'Ekspor gagal. Silakan coba kembali.').removeClass('d-none');
            } finally {
                busy = false;
                $('#exportReportForm :input, #exportReportModal .close').prop('disabled', false);
                $('#exportReportProgress').addClass('d-none');
            }
        });
    }
    root.SalesReportExport = {init, excelBlob, pdfDefinition};
})(window);
