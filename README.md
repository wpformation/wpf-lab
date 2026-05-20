# WPF Lab — 5 façons de créer une page WordPress avec Claude Code (mai 2026)

> Code source complet du lab WPFormation. 6 pages publiées sur [test.wpformation.com](https://test.wpformation.com/), 5 méthodes testées sur 4 piliers. Article complet : [wpformation.com/creer-page-wordpress-claude-code/](https://wpformation.com/creer-page-wordpress-claude-code/).

## Les 6 pages en ligne

| Page | URL | ID WordPress |
|---|---|---|
| HUB éditoriale | https://test.wpformation.com/ | 225 |
| Méthode 1 — Gutenberg natif pur | https://test.wpformation.com/accueil-gutenberg-natif/ | 131 |
| **Méthode 2 — sweet spot Gutenberg + CSS** ★ | https://test.wpformation.com/methode-wpformation/ | 129 |
| Méthode 3 — HTML monobloc | https://test.wpformation.com/methode-html-monobloc/ | 135 |
| Méthode 4 — HTML multi-blocs | https://test.wpformation.com/methode-html-multiblocs/ | 137 |
| Méthode 5 — Spectra Pro | https://test.wpformation.com/methode-spectra-pro/ | 133 |

## Le code source (la méthode 2)

| Fichier | Rôle |
|---|---|
| [`markup-methode-2.html`](./markup-methode-2.html) | Markup Gutenberg natif complet (513 blocs, 99,2 % natif). Importable tel quel dans un éditeur Gutenberg vierge. |
| [`css-lab-methode-2.css`](./css-lab-methode-2.css) | CSS Lab page-level complet (113 Ko). Stocké dans la meta `_uag_custom_page_level_css` exposée par Spectra (gratuit). |
| [`footer-lab-shared.css`](./footer-lab-shared.css) | Source unique pour en-tête, menu, bannière et pied de page communs aux 6 pages. |

## Les scripts d'orchestration

| Fichier | Rôle |
|---|---|
| [`fix-arrows.js`](./fix-arrows.js) | Convertit les flèches Unicode `↗` (U+2197) en `↗︎` (avec variation selector U+FE0E) pour éviter le rendu emoji bleu de Chromium. Idempotent. |
| [`push-s4-octies-footers.js`](./push-s4-octies-footers.js) | Push master des 6 pages (markup + CSS meta) avec sleep 18 s entre POST et 12 nouvelles tentatives avec sleep 22 s sur HTTP 429 (limitation Tiger Protect O2switch). |
| [`inject-style-into-m5.js`](./inject-style-into-m5.js) | Contournement obligatoire pour Spectra Pro qui ne génère pas le CSS frontend sur la M5. Ré-injecte le CSS partagé en inline dans le markup. |

## Pré-requis

- WordPress 6.5+ (ou 7.0+)
- Un thème compatible Gutenberg (Astra gratuit testé)
- **Spectra gratuit** (la version Pro n'est PAS nécessaire pour la méthode 2)
- Un Application Password sur un compte admin
- Claude Code installé (plan Anthropic Pro, Max ou Team)

## Licence

MIT. Forkez, modifiez, utilisez en production. Une mention de la source dans votre projet est appréciée mais pas obligatoire.

## Auteur

Fabrice Ducarme — [WPFormation](https://wpformation.com).
14 ans WordPress, 8 plugins WP.org (2,1 M+ téléchargements cumulés), formation IA + WordPress certifiée Qualiopi.

---

Webinar live le 18 juin 2026 à 18 h 30 : je rejoue les 5 méthodes en direct, côte à côte, 1 h plus questions-réponses. [Inscription gratuite](https://wpformation.com/creer-page-wordpress-claude-code/#webinar).
