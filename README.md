# WPF Lab — Benchmark WordPress × Claude Code

> Repo de référence du **lab d'évaluation comparative** des 7 façons de créer une page WordPress en 2026, joué sur `test.wpformation.com` avec Claude Code.

**Article complet** : <https://wpformation.com/creer-page-wordpress-claude-code/>

---

## De 5 à 7 méthodes — historique du repo

Ce repo a démarré en **mai 2026, session S4**, avec 5 méthodes testées et **M·02 (Gutenberg + CSS personnalisé via Spectra)** déclaré gagnant.

En **session S6 (21 mai 2026)**, deux nouvelles méthodes ont été ajoutées :

- **M·06** — Gutenberg pur + CSS embarqué dans un seul bloc `core/html` (zéro plugin)
- **M·07** — Blocs PHP custom via plugin `wpf-lab` et pattern **PHP-only block registration** WordPress 7.0 (`supports.autoRegister: true`, zéro JS, zéro build)

**Le vainqueur a changé** : **M·07 — 30/30 brut, 37,5/37,5 pondéré**. M·02 reste cité comme **la voie historique** (importante pour les sites legacy), mais surpassée pour toute refonte 2026.

> **Note de transparence (2026-05-27)** : la version initiale de ce repo et de l'article WPFormation nommait ce pattern `autoGenerateControl` et le présentait comme un flag à poser sur chaque attribut. **Faux.** Le vrai mécanisme est `supports.autoRegister: true` au niveau du bloc (l'éditeur Gutenberg dérive automatiquement les contrôles sidebar du `type` de chaque attribut). Le plugin a toujours tourné correctement parce que `autoRegister` était déjà posé en parallèle ; WordPress ignorait silencieusement la clé inconnue. Corrigé en v1.4.0. Source officielle : [PHP-only block registration](https://make.wordpress.org/core/2026/03/03/php-only-block-registration/) (dev note Miguel Fonseca, 3 mars 2026, ticket Trac [#64639](https://core.trac.wordpress.org/ticket/64639), implémentation [@priethor](https://profiles.wordpress.org/priethor/)).

---

## Les 7 méthodes en compétition

| # | Méthode | Score | Démo live |
|---|---|---|---|
| **★1** | **M·07 — Blocs PHP custom (`supports.autoRegister` WP 7.0)** | **30/30** | <https://test.wpformation.com/methode-blocs-php-custom/> |
| 2 | M·06 — Gutenberg pur + CSS dans core/html | 26/30 | <https://test.wpformation.com/methode-gutenberg-pur/> |
| 3 | M·02 — Gutenberg + CSS perso (voie historique) | 24/30 | <https://test.wpformation.com/methode-wpformation/> |
| 4 | M·01 — Gutenberg natif (baseline) | 25/30 | <https://test.wpformation.com/accueil-gutenberg-natif/> |
| 5 | M·04 — HTML multi-blocs | 22/30 | <https://test.wpformation.com/methode-html-multiblocs/> |
| 6 | M·05 — Spectra Pro | 20/30 | <https://test.wpformation.com/methode-spectra-pro/> |
| 6 | M·03 — HTML monobloc | 19/30 | <https://test.wpformation.com/methode-html-monobloc/> |

HUB : <https://test.wpformation.com/>

---

## Structure du repo

```
.
├── README.md
├── plugin-wpf-lab/           ← Le plugin M·07 (11 blocs PHP custom, GPL-2.0+)
├── docs-s6/                  ← Matrice 7 méthodes, note rédacteur, recap S6
├── pages-v3-s6/              ← Markups Gutenberg des 7 pages
├── scripts-s6/               ← Scripts de déploiement + footers + wow CSS
├── captures-s6/              ← Captures clés desktop M1-M7 + zooms M·07 wow
└── (artefacts S4 historiques : css-lab-methode-2.css, markup-methode-2.html…)
```

---

## Le plugin wpf-lab en bref

11 blocs Gutenberg PHP custom, **zéro JavaScript**, grâce à WordPress 7.0 + pattern **PHP-only block registration** (`supports.autoRegister: true`). Doc complète : [plugin-wpf-lab/README.md](plugin-wpf-lab/README.md).

**Usage minimal** :

```html
<!-- wp:wpf/lab-utility /-->
<!-- wp:wpf/lab-localnav /-->
<!-- wp:wpf/lab-banner /-->
<!-- wp:wpf/lab-hero /-->
<!-- wp:wpf/lab-stats /-->
<!-- wp:wpf/lab-why /-->
<!-- wp:wpf/lab-pillars /-->
<!-- wp:wpf/lab-feature-twocol /-->
<!-- wp:wpf/lab-methods-table /-->
<!-- wp:wpf/lab-team /-->
<!-- wp:wpf/lab-cta-final /-->
```

11 lignes, ~3 KB. Tout le design est dans le plugin (versionné Git).

---

## Stack technique

- **WordPress** 7.0+ (pour `supports.autoRegister` — PHP-only block registration)
- **Thème** Astra (free)
- **Plugin Spectra** (free) — requis pour M·02 et M·04 (CSS page-level via meta)
- **Plugin Spectra Pro** — requis pour M·05
- **Plugin wpf-lab** (ce repo, dossier `plugin-wpf-lab/`) — requis pour M·07
- **Hébergeur** o2switch (rate-limit Tiger Protect → sleep 18s + retry 22s × 12 sur 429)

---

## Licence

- **Plugin `wpf-lab`** : GPL-2.0-or-later (voir [plugin-wpf-lab/LICENSE](plugin-wpf-lab/LICENSE))
- **Reste du repo** (docs, scripts, captures) : MIT (héritage S4)

---

## Liens

- **Article WPFormation** : <https://wpformation.com/creer-page-wordpress-claude-code/>
- **Site de démo** : <https://test.wpformation.com>
- **Plugin** : [plugin-wpf-lab/](plugin-wpf-lab/)
- **Matrice de scoring 7 méthodes** : [docs-s6/matrice-finale-7-methodes-2026-05-21-S6.md](docs-s6/matrice-finale-7-methodes-2026-05-21-S6.md)
- **Note pour le rédacteur** : [docs-s6/note-pour-redacteur-wpformation-S6-FINALE.md](docs-s6/note-pour-redacteur-wpformation-S6-FINALE.md)
- **Recap session S6** : [docs-s6/session-6-recap-2026-05-21.md](docs-s6/session-6-recap-2026-05-21.md)
- **Source officielle WordPress 7.0** : [dev note PHP-only block registration](https://make.wordpress.org/core/2026/03/03/php-only-block-registration/) (Miguel Fonseca, 3 mars 2026, ticket Trac [#64639](https://core.trac.wordpress.org/ticket/64639))

---

## Crédits

- **Conception, benchmark, design Direction B** : Fabrice Ducarme — [WPFormation](https://wpformation.com)
- **Co-développement** : Claude Code (Anthropic Opus 4.7)
- **Pattern PHP-only block registration (`autoRegister`)** : [Miguel Fonseca](https://profiles.wordpress.org/mfonseca/), dev note officielle [du 3 mars 2026](https://make.wordpress.org/core/2026/03/03/php-only-block-registration/), implémentation par [@priethor](https://profiles.wordpress.org/priethor/), ticket Trac [#64639](https://core.trac.wordpress.org/ticket/64639). Disponible depuis WordPress 7.0.
