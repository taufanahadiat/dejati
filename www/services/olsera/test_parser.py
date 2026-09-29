import tempfile
import unittest
import zipfile
from pathlib import Path
from xml.sax.saxutils import escape
from parse_xlsx import parse_xlsx, FIELDS

class ParserTest(unittest.TestCase):
    def workbook(self, rows, inline=True):
        temp=tempfile.TemporaryDirectory();self.addCleanup(temp.cleanup);file=Path(temp.name)/'report.xlsx'
        with zipfile.ZipFile(file,'w') as z:
            z.writestr('xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sales" r:id="rId1"/></sheets></workbook>')
            z.writestr('xl/_rels/workbook.xml.rels','<Relationships><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>')
            content=[];strings=[]
            for ri,row in enumerate(rows,1):
                cells=[]
                for ci,value in enumerate(row):
                    ref=chr(65+ci)+str(ri)
                    if inline: cells.append(f'<c r="{ref}" t="inlineStr"><is><t>{escape(str(value))}</t></is></c>')
                    else: strings.append(str(value));cells.append(f'<c r="{ref}" t="s"><v>{len(strings)-1}</v></c>')
                content.append('<row>'+''.join(cells)+'</row>')
            z.writestr('xl/worksheets/sheet1.xml','<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'+''.join(content)+'</sheetData></worksheet>')
            if not inline:z.writestr('xl/sharedStrings.xml','<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'+''.join('<si><t>'+escape(s)+'</t></si>' for s in strings)+'</sst>')
        return file
    def data(self):return [list(FIELDS.values()),['MIE DE&#039;JATI','','Meals','','IDR','2.5','12000.0','2000','0','10000.0']]
    def test_inline_and_shared(self):
        for inline in [True,False]:
            rows=parse_xlsx(self.workbook(self.data(),inline));self.assertEqual(rows[0]['quantity'],'2.5');self.assertEqual(rows[0]['total_sales'],'10000.0');self.assertEqual(len(rows),1)
    def test_bad_number(self):
        rows=self.data();rows[1][-1]='NaN'
        with self.assertRaises(ValueError):parse_xlsx(self.workbook(rows))
    def test_wrong_report(self):
        with self.assertRaises(ValueError):parse_xlsx(self.workbook([['product','sell_price'],['Kopi','100']]))
    def test_missing_amount(self):
        rows=self.data();rows[1][-1]=''
        with self.assertRaises(ValueError):parse_xlsx(self.workbook(rows))
    def test_header_only_empty(self):self.assertEqual(parse_xlsx(self.workbook([list(FIELDS.values())])),[])

if __name__=='__main__':unittest.main()
