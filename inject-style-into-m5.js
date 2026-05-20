#!/usr/bin/env node
/* Injecte le CSS page-level M5 directement dans le markup via un wp:html <style>
   pour contourner le problème Spectra qui ne génère pas le frontend CSS sur cette page. */
const fs = require('fs');
const path = require('path');
const https = require('https');

const creds = JSON.parse(fs.readFileSync(path.resolve(__dirname, '..', 'config', 'credentials.json'), 'utf8')).wp_test;
const base = creds.site_url.replace(/\/$/, '');
const auth = 'Basic ' + Buffer.from(creds.admin_user + ':' + creds.app_password).toString('base64');
async function req(method, fullUrl, payload) {
  return new Promise((resolve, reject) => {
    const u = new URL(fullUrl);
    const body = payload ? JSON.stringify(payload) : null;
    const headers = { Authorization: auth, Accept: 'application/json' };
    if (body) { headers['Content-Type'] = 'application/json'; headers['Content-Length'] = Buffer.byteLength(body); }
    const r = https.request({ method, hostname: u.hostname, port: 443, path: u.pathname + (u.search || ''), headers },
      (res) => { const cs = []; res.on('data', c => cs.push(c)); res.on('end', () => resolve({ status: res.statusCode, body: Buffer.concat(cs).toString('utf8') })); });
    r.on('error', reject); if (body) r.write(body); r.end();
  });
}
const sleep = ms => new Promise(r => setTimeout(r, ms));
async function withRetry(fn, label) {
  for (let i = 0; i < 12; i++) {
    const r = await fn();
    if (r && r.status >= 200 && r.status < 300) return r;
    if (r && r.status === 429) { console.log(`  ${label} 429 try ${i+1}`); await sleep(22000); continue; }
    return r;
  }
}

const MARKER_START = '<!-- M5 INLINE STYLE INJECTION START -->';
const MARKER_END = '<!-- M5 INLINE STYLE INJECTION END -->';

(async () => {
  let markup = fs.readFileSync(path.resolve(__dirname, 'page-5-methode-spectra-pro-v3.html'), 'utf8');
  const css = fs.readFileSync(path.resolve(__dirname, 'page-5-methode-spectra-pro-v3.css'), 'utf8');

  // Idempotence : retire l'injection précédente si présente
  const reMarker = new RegExp(`${MARKER_START.replace(/[/*().[\]-]/g, '\\$&')}[\\s\\S]*?${MARKER_END.replace(/[/*().[\]-]/g, '\\$&')}\\n*`, 'g');
  markup = markup.replace(reMarker, '');

  const block = `${MARKER_START}\n<!-- wp:html -->\n<style id="m5-inline-page-css">\n${css}\n</style>\n<!-- /wp:html -->\n${MARKER_END}\n\n`;
  markup = block + markup;

  fs.writeFileSync(path.resolve(__dirname, 'page-5-methode-spectra-pro-v3.html'), markup, 'utf8');
  console.log(`M5 markup with inline style: ${markup.length} chars`);

  console.log('Push markup M5');
  await withRetry(() => req('POST', `${base}/wp-json/wp/v2/pages/133`, { content: markup }), 'M5 markup');
  console.log('Done.');
})().catch(e => { console.error(e); process.exit(1); });
