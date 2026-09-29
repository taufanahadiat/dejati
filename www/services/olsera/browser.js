const { chromium } = require('playwright-core');
const fs = require('node:fs/promises');
const path = require('node:path');
const crypto = require('node:crypto');
const { promisify } = require('node:util');
const execFile = promisify(require('node:child_process').execFile);
const ORIGIN = 'https://dashboardv2.olsera.co.id';
const STORE = 'dejaticoffeegarden.myolsera.com';
const isSalesResponse = r => new URL(r.url()).pathname === '/api/dejaticoffeegarden/admin/v1/id/reportpenjualan/productsalesbysku' && r.request().method()==='GET';
async function salesLoaded(response) {
  if (!response.ok()) throw Error('Laporan Olsera gagal dimuat');
  await response.finished();
}
const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
function dateParts(date) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || new Date(date + 'T00:00:00Z').toISOString().slice(0,10) !== date) throw Error('Tanggal laporan tidak valid');
  const [year, month, day] = date.split('-').map(Number);
  return { year, month, day, monthText: `${months[month-1]} ${year}`, shortText: `${day} ${months[month-1]} ${String(year).slice(-2)}` };
}
async function selectDate(page, date) {
  const d = dateParts(date);
  const button = page.locator('.date-range-picker-wrapper > button');
  await button.click();
  const picker = page.locator('.daterangepicker:visible');
  await picker.locator('[data-range-key="Custom Range"]').click();
  for (let i=0; i<120; i++) {
    const visible = (await picker.locator('.drp-calendar.left .month').innerText()).trim();
    if (visible === d.monthText) break;
    const [month, year] = visible.split(' ');
    const current = Number(year)*12+months.indexOf(month);
    if (months.indexOf(month)<0) throw Error('Format kalender Olsera berubah');
    await picker.locator(current>d.year*12+d.month-1 ? '.prev.available' : '.next.available').click();
  }
  if ((await picker.locator('.drp-calendar.left .month').innerText()).trim() !== d.monthText) throw Error('Tanggal tidak tersedia');
  const day = picker.locator('.drp-calendar.left td.available:not(.off)').filter({hasText:new RegExp(`^${d.day}$`)});
  await day.click();await day.click();
  const [response]=await Promise.all([page.waitForResponse(isSalesResponse,{timeout:60000}),picker.locator('.applyBtn').click()]);
  await salesLoaded(response);
  await page.waitForFunction(expected => {
    const text=document.querySelector('.date-range-picker-wrapper > button')?.innerText.replace(/\s+/g,' ').trim();
    return text===`${expected} - ${expected}`;
  },d.shortText);
  await page.waitForFunction(() => [...document.querySelectorAll('.content-wrapper .el-loading-mask')].every(e => !e.getClientRects().length || getComputedStyle(e).visibility==='hidden'), null, {timeout:45000});
}
async function downloadReport(date, options={}) {
  dateParts(date);
  const root=options.dataDir || process.env.OLSERA_DATA_DIR || '/data';
  const credentials=options.credentials || JSON.parse(await fs.readFile(process.env.OLSERA_CREDENTIALS || '/run/secrets/olsera-credentials','utf8'));
  const context=await chromium.launchPersistentContext(path.join(root,'profile'), {
    executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless:true,
    timezoneId:'Asia/Jakarta',locale:'en-US',acceptDownloads:true,args:['--no-sandbox','--disable-dev-shm-usage']
  });
  let stage='membuka login';
  const progress=value=>{stage=value;options.onProgress?.(value);};
  const deadline=setTimeout(()=>context.close().catch(()=>{}),240000);
  try {
    const page=await context.newPage();page.setDefaultTimeout(45000);
    await page.goto(ORIGIN+'/selectstore',{waitUntil:'domcontentloaded',timeout:60000});
    await page.locator('input[type="password"], .login-form').first().waitFor();
    // The login form is also used for store selection. Wait for its actual contents.
    await page.waitForFunction(()=>document.querySelector('input[type="password"]') || document.body.innerText.includes('Masuk ke toko'));
    if (await page.locator('input[type="password"]').count()) {
      const form=page.locator('.login-form form');
      await form.locator('input').nth(0).fill(credentials.email);
      await form.locator('input[type="password"]').fill(credentials.password);
      await form.getByRole('button',{name:'Masuk',exact:true}).click();
      await page.waitForURL('**/selectstore',{timeout:45000});
    }
    progress('memilih toko');
    await page.getByText(STORE,{exact:true}).waitFor();
    const enter=page.getByText('Masuk ke toko',{exact:true});
    if (await enter.count()!==1) throw Error('Pemilihan toko Olsera ambigu');
    await enter.click();await page.waitForURL(url=>!['/selectstore','/login'].includes(url.pathname));
    progress('membuka laporan produk');
    const [initialResponse]=await Promise.all([page.waitForResponse(isSalesResponse,{timeout:60000}),page.goto(ORIGIN+'/reports/products',{waitUntil:'domcontentloaded',timeout:60000})]);
    await salesLoaded(initialResponse);
    await page.locator('.date-range-picker-wrapper > button').waitFor();
    progress('memilih tanggal laporan');
    await selectDate(page,date);
    progress('mengunduh Excel');
    const header=page.locator('.el-card__header').filter({has:page.locator('.date-range-picker-wrapper')});
    if (!(await header.innerText()).includes('Penjualan berdasarkan SKU')) throw Error('Jenis laporan Olsera berubah');
    const [download]=await Promise.all([page.waitForEvent('download',{timeout:60000}),header.locator('button').filter({hasText:/EXCEL/}).click()]);
    const name=path.basename(download.suggestedFilename());
    if (!name.endsWith('.xlsx') || !name.includes(`${date}__${date}`)) throw Error('Tanggal file unduhan tidak sesuai closing');
    const folder=path.join(root,'exports',date);await fs.mkdir(folder,{recursive:true,mode:0o700});
    const temporary=path.join(folder,crypto.randomUUID()+'.xlsx');await download.saveAs(temporary);
    if (await download.failure()) throw Error('Unduhan Excel gagal');
    const file=await fs.readFile(temporary);
    if (file.length>10000000 || file.length<100) throw Error('Ukuran file Excel tidak valid');
    const sha256=crypto.createHash('sha256').update(file).digest('hex');
    const archive=path.join(folder,sha256+'.xlsx');await fs.rename(temporary,archive);
    progress('membaca Excel');
    const {stdout}=await execFile('python3',[path.join(__dirname,'parse_xlsx.py'),archive],{maxBuffer:10000000,timeout:30000});
    const rows=JSON.parse(stdout);
    const emptyText=await page.locator('body').innerText();
    const confirmedEmpty=rows.length===0 && /No Data|Tidak ada data|Tidak Ada Data|Data tidak ditemukan/i.test(emptyText);
    if (!rows.length && !confirmedEmpty) throw Error('Excel kosong tanpa konfirmasi laporan kosong');
    return {date,report_date:date,store:STORE,sha256,file_name:name,fetched_at:new Date().toISOString().replace(/\.\d{3}Z$/,'+00:00'),rows,confirmed_empty:confirmedEmpty};
  } catch (error) {
    error.publicMessage='Pengambilan Olsera gagal saat '+stage+'. Periksa koneksi, login, dan format halaman.';
    throw error;
  } finally {clearTimeout(deadline);await context.close();}
}
module.exports={downloadReport,selectDate,dateParts};
if (require.main===module) downloadReport(process.argv[2]).then(r=>console.log(JSON.stringify(r))).catch(()=>{console.error('Pengambilan Excel Olsera gagal. Periksa login, tanggal, dan format laporan.');process.exitCode=1;});
