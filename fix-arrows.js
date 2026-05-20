#!/usr/bin/env node
/* fix-arrows.js — remplace tous les ↗ (U+2197) par ↗︎ (U+2197 + U+FE0E variation selector text)
   dans M1, M3, M4 HTML files. Idempotent (re-applique sans dupliquer le U+FE0E).
*/
const fs = require('fs');
const path = require('path');

const TARGETS = [
  'page-1-accueil-gutenberg-natif-v3.html',
  'page-3-methode-html-monobloc-v2.html',
  'page-4-methode-html-multiblocs-v2.html',
];

const ARROW = '↗';           // ↗
const VS = '︎';              // variation selector text
const ARROW_VS = ARROW + VS;      // ↗︎

let totalReplaced = 0;
for (const f of TARGETS) {
  const fullPath = path.resolve(__dirname, f);
  let src = fs.readFileSync(fullPath, 'utf8');
  // Idempotence : retire d'abord tous les U+FE0E existants après un ↗, puis re-applique
  src = src.split(ARROW_VS).join(ARROW);
  const bareArrows = (src.match(new RegExp(ARROW, 'g')) || []).length;
  const out = src.split(ARROW).join(ARROW_VS);
  fs.writeFileSync(fullPath, out, 'utf8');
  console.log(`  ${f.padEnd(50)} → ${bareArrows} ↗ → ↗︎`);
  totalReplaced += bareArrows;
}
console.log(`\nTotal : ${totalReplaced} flèches remplacées.`);
