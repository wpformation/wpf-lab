#!/usr/bin/env node
/*
  S4-octies — Inject Footer Lab unifié + CSS partagé (footer + menu actif + banner renforcé)
  sur les 6 pages (M1, M2, M3, M4, M5, HUB).
*/
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
    if (r && r.status === 429) { console.log(`  ${label} 429 try ${i+1} — sleep 22s`); await sleep(22000); continue; }
    console.log(`FAIL ${label} HTTP ${r ? r.status : 'null'} body=${r ? r.body.slice(0,200) : 'n/a'}`);
    return r;
  }
}

const SHARED_CSS_MARKER = '/* ==================== S4-OCTIES SHARED (footer + menu + banner) ==================== */';
const SHARED_CSS_END = '/* end footer-lab-shared */';
const FOOTER_MARKER_START = '<!-- S4-OCTIES FOOTER LAB START -->';
const FOOTER_MARKER_END = '<!-- S4-OCTIES FOOTER LAB END -->';

const sharedCss = fs.readFileSync(path.resolve(__dirname, 'footer-lab-shared.css'), 'utf8');
const sharedCssWrapped = `\n${SHARED_CSS_MARKER}\n${sharedCss}\n`;

const CONFIG = [
  { key: 'M1',  id: 131, markupFile: 'page-1-accueil-gutenberg-natif-v3.html',   cssFile: 'page-1-overrides.css',                  cssLocation: 'meta' },
  { key: 'M2',  id: 129, markupFile: 'page-2-methode-wpformation-v3.html',        cssFile: 'page-2-methode-wpformation-v2.css',     cssLocation: 'meta' },
  { key: 'M3',  id: 135, markupFile: 'page-3-methode-html-monobloc-v2.html',      cssFile: null,                                    cssLocation: 'inline-style' },
  { key: 'M4',  id: 137, markupFile: 'page-4-methode-html-multiblocs-v2.html',    cssFile: 'page-4-methode-html-multiblocs-v2.css', cssLocation: 'meta' },
  { key: 'M5',  id: 133, markupFile: 'page-5-methode-spectra-pro-v3.html',        cssFile: 'page-5-methode-spectra-pro-v3.css',     cssLocation: 'meta' },
  { key: 'HUB', id: 225, markupFile: 'page-0-homepage-hub-v2.html',               cssFile: 'page-0-homepage-hub-v2.css',            cssLocation: 'meta' },
];

function injectFooterIntoMarkup(markup, footerHtml) {
  // Idempotence: remove previous footer between markers
  const reFooter = new RegExp(`${FOOTER_MARKER_START.replace(/[/*().[\]-]/g, '\\$&')}[\\s\\S]*?${FOOTER_MARKER_END.replace(/[/*().[\]-]/g, '\\$&')}`, 'g');
  markup = markup.replace(reFooter, '');
  // Append wrapped footer
  return markup.trimEnd() + `\n\n${FOOTER_MARKER_START}\n${footerHtml}\n${FOOTER_MARKER_END}\n`;
}

function injectSharedCssIntoCss(css) {
  // Idempotence: remove previous shared block
  const reShared = new RegExp(`\\n*${SHARED_CSS_MARKER.replace(/[/*().[\]-]/g, '\\$&')}[\\s\\S]*?${SHARED_CSS_END.replace(/[/*\-]/g, '\\$&')}`, 'g');
  css = css.replace(reShared, '');
  return css.trimEnd() + sharedCssWrapped;
}

function injectSharedCssIntoInlineStyle(markup) {
  // Idempotence
  const reShared = new RegExp(`\\n*${SHARED_CSS_MARKER.replace(/[/*().[\]-]/g, '\\$&')}[\\s\\S]*?${SHARED_CSS_END.replace(/[/*\-]/g, '\\$&')}`, 'g');
  markup = markup.replace(reShared, '');
  // Insert just before </style>
  return markup.replace(/<\/style>/i, sharedCssWrapped + '\n</style>');
}

(async () => {
  console.log('═══════════════════════════════════════════════════════════');
  console.log('S4-octies — Footer Lab unifié + Menu actif + Banner renforcé');
  console.log('═══════════════════════════════════════════════════════════');

  for (const p of CONFIG) {
    const markupPath = path.resolve(__dirname, p.markupFile);
    const footerPath = path.resolve(__dirname, `footer-lab-${p.key}.html`);

    let markup = fs.readFileSync(markupPath, 'utf8');
    const footerHtml = fs.readFileSync(footerPath, 'utf8');

    // Inject footer
    markup = injectFooterIntoMarkup(markup, footerHtml);

    // Inject CSS
    let css = null;
    if (p.cssLocation === 'inline-style') {
      markup = injectSharedCssIntoInlineStyle(markup);
    } else {
      const cssPath = path.resolve(__dirname, p.cssFile);
      css = fs.readFileSync(cssPath, 'utf8');
      css = injectSharedCssIntoCss(css);
      fs.writeFileSync(cssPath, css, 'utf8');
    }

    fs.writeFileSync(markupPath, markup, 'utf8');
    console.log(`\n  ${p.key} (${p.id}) : footer inject + css ${p.cssLocation} (markup ${markup.length} chars${css ? `, css ${css.length} chars` : ''})`);

    // Push markup
    console.log(`  > Push markup → ${p.id}`);
    await withRetry(() => req('POST', `${base}/wp-json/wp/v2/pages/${p.id}`, { content: markup }), `${p.key} markup`);
    await sleep(18000);

    // Push CSS meta if applicable
    if (p.cssLocation === 'meta' && css) {
      // For HUB which uses __PAGE_ID__ template
      let cssToPush = css;
      if (p.key === 'HUB') {
        cssToPush = css.replace(/__PAGE_ID__/g, String(p.id));
      }
      console.log(`  > Push CSS meta → ${p.id}`);
      await withRetry(() => req('POST', `${base}/wp-json/wp/v2/pages/${p.id}`, {
        meta: { _uag_custom_page_level_css: cssToPush }
      }), `${p.key} css`);
      await sleep(18000);
    }
  }

  console.log('\n═══════════════════════════════════════════════════════════');
  console.log('Done.');
  console.log('  Vérifier : footer ink sur 6 pages + menu Astra current item');
  console.log('═══════════════════════════════════════════════════════════');
})().catch(e => { console.error('ERROR:', e); process.exit(1); });
