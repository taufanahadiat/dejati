const fs=require('node:fs/promises');
const {downloadReport}=require('./browser');
async function cycle(api, download=downloadReport) {
  const {job}=await api({action:'claim'});
  if (!job) return false;
  try {
    const data=await download(job.date);
    await api({...data,action:'complete',lease:job.lease});
    console.log('Olsera: sinkronisasi selesai '+job.date+' ('+data.rows.length+' baris)');
  } catch (error) {
    // Do not log browser exceptions that may contain credentials or remote page contents.
    const message=error.apiMessage || error.publicMessage || 'Gagal mengunduh/membaca Excel Olsera. Periksa login, koneksi, dan format laporan.';
    await api({action:'fail',date:job.date,lease:job.lease,error:message});
    console.error('Olsera: sinkronisasi tertunda '+job.date);
  }
  return true;
}
async function main() {
  const token=(await fs.readFile('/run/secrets/stock-token','utf8')).trim();
  const api=async body=>{
    const response=await fetch(process.env.OLSERA_API || 'http://lampp_web/api/olsera-sync',{
      method:'POST',headers:{'Content-Type':'application/json','X-Stock-Token':token},body:JSON.stringify(body),signal:AbortSignal.timeout(45000)
    });
    const data=await response.json();
    if (!response.ok) {const error=Error('Olsera API unavailable');if(response.status===422)error.apiMessage=data.error;throw error;}
    return data;
  };
  let stop=false;process.on('SIGTERM',()=>{stop=true;});process.on('SIGINT',()=>{stop=true;});
  while(!stop) {
    try {await cycle(api);} catch {console.error('Olsera: antrean belum dapat diakses');}
    await fs.writeFile('/data/heartbeat',String(Date.now()));
    if (!stop) await new Promise(resolve=>setTimeout(resolve,15000));
  }
}
module.exports={cycle};
if(require.main===module)main().catch(()=>{console.error('Olsera: konfigurasi worker tidak tersedia');process.exitCode=1;});
