# WPF Édition au clic (prototype de laboratoire)

Avec cette extension WordPress de 637 lignes, un client modifie depuis son site, sans constructeur de pages, les textes, les liens et les images d'une page écrite en HTML. Chaque enregistrement crée une révision WordPress native.

Ce dossier accompagne l'article [Elementor, Figma, plugins premium, CMS : de quoi ai-je encore besoin avec l'IA ?](https://wpformation.com/elementor-figma-cms-ia/). Le code a été généré par Claude Code le 18 octobre 2026, en 8 min 37 s, puis testé dans le laboratoire Docker fourni ici.

> Prototype de laboratoire : il n'est pas publié sur WordPress.org et n'a pas de tests unitaires PHP. Ne l'installez pas sur un site en production sans l'avoir relu.

## Le principe

On marque chaque élément modifiable avec deux attributs :

```html
<h1 data-wpf-edit="text" data-wpf-id="hero.title">Des pièces tournées à la main</h1>
<div data-wpf-edit="rich" data-wpf-id="hero.text"><p>Bols et tasses en <strong>grès</strong>.</p></div>
<a class="cta" href="#cartes" data-wpf-edit="link" data-wpf-id="hero.cta">Voir la collection</a>
<img data-wpf-edit="image" data-wpf-id="hero.image" src="/wp-content/uploads/bol.jpg" alt="Bol en grès">
```

Quatre types existent : `text`, `rich` (gras, italique, lien), `link` (texte et adresse) et `image` (choisie dans la médiathèque). Le balisage peut vivre dans un bloc HTML personnalisé, avec n'importe quel thème : l'essai a été fait sur Twenty Twenty-Five.

Une personne connectée qui a le droit `edit_post` sur la page voit un bouton « Modifier la page ». Elle clique sur un élément, le modifie sur place, puis enregistre. Les visiteurs ne reçoivent ni le JavaScript, ni la feuille de style, ni les attributs `data-wpf-*`, retirés à l'affichage.

## Structure

```text
edition-au-clic/
├── plugin-wpf-clic/        L'extension (GPL-2.0-or-later)
│   ├── wpf-clic.php        Chargement, droits, bouton, retrait des marqueurs pour les visiteurs
│   ├── includes/
│   │   ├── class-wpf-clic-markup.php   Lecture et réécriture du balisage (WP_HTML_Tag_Processor)
│   │   └── class-wpf-clic-rest.php     Route d'enregistrement, contrôles, révision
│   └── assets/             wpf-clic.js (220 lignes) et wpf-clic.css (35 lignes)
├── lab/                    Laboratoire Docker : WordPress 7.1.1, PHP 8.4, MariaDB 11.4
│   ├── docker-compose.yml
│   ├── setup.sh            Installation, comptes de test, images, page de démonstration
│   ├── make_images.py      Génère les trois images de test (Pillow)
│   └── seed/page-html.html Page « Atelier Lumen », marque fictive
└── tests/test_wpf_clic.py  Tests fonctionnels et de sécurité (Playwright)
```

## Monter le laboratoire

Il faut Docker, Python 3 et Pillow (`pip install pillow`).

```sh
sh lab/setup.sh                      # port 8088
WPF_LAB_PORT=8089 sh lab/setup.sh    # autre port
```

Le script installe WordPress, crée trois comptes et active l'extension. Il importe aussi trois images et publie la page `/atelier-html/`. Connectez-vous ensuite avec `cliente` / `lab-cliente-local` (rôle Éditeur) et cliquez sur « Modifier la page ». Les mots de passe ne servent qu'à ce laboratoire local, exposé sur `127.0.0.1` seulement.

Le rôle Éditeur y perd volontairement le droit `unfiltered_html`, comme un client sur un multisite. C'est le cas le plus strict : WordPress seul retire alors la balise `<style>` de la page dès qu'elle l'enregistre.

Pour tout arrêter : `docker compose -f lab/docker-compose.yml down -v`.

## Lancer les tests

```sh
pip install playwright
python -m playwright install chromium
python tests/test_wpf_clic.py captures/                                    # laboratoire sur 8088
WPF_LAB_URL=http://localhost:8089 python tests/test_wpf_clic.py captures/  # autre port
```

Le script affiche les mesures en JSON et enregistre six captures dans `captures/`.

## Mesures du 18 octobre 2026

Relevées sur un laboratoire neuf monté par `setup.sh`, avec une éditrice sans `unfiltered_html`.

| Mesure | Résultat |
|---|---|
| Visiteur anonyme : fichiers de l'extension chargés | 0 |
| Visiteur anonyme : attributs `data-wpf-*` dans le HTML servi | 0 |
| Visiteur anonyme : envoi sur la route d'enregistrement | 401 |
| Abonné avec un nonce REST valide | 403, aucun bouton affiché |
| Éditrice connectée : JavaScript et CSS ajoutés | 6 837 et 1 891 octets bruts |
| Modification du titre | 0,2 s, conservée au rechargement |
| Texte riche avec le bouton Gras | « Séries courtes, **grès local** » conservé |
| Lien, texte et adresse | « Réserver un cours » vers `/cours/` conservé |
| Image depuis la médiathèque | remplacée, cadre de même largeur et même hauteur |
| Injection `<script>`, `onerror`, `javascript:` | enregistrée sans script, sans gestionnaire d'événement et sans le protocole |
| Page modifiée entre-temps par quelqu'un d'autre | 409 |
| Marqueur `data-wpf-id` glissé dans une valeur | 400 |
| Balises déséquilibrées | rééquilibrées avant l'enregistrement |
| Champ inconnu | 404 |
| Feuille `<style>` de la page après les enregistrements | conservée |
| Extension désactivée | les textes saisis restent affichés : ils sont dans le contenu de la page |

## Ce que fait la sécurité

- Le droit vérifié est `edit_post` sur la page visée, pas un droit global.
- Chaque envoi porte le nonce REST `wp_rest` et l'empreinte du contenu lu. Si la page a changé entre-temps, l'envoi est refusé (409), et si quelqu'un d'autre a la page ouverte dans l'éditeur, il l'est aussi (423).
- Seul le fragment modifié est assaini : texte échappé, `wp_kses` avec une courte liste de balises, adresses limitées à http, https, mailto et tel. Le type de champ est lu dans le contenu enregistré, jamais dans la requête.
- Le filtrage de WordPress n'est suspendu que le temps de `wp_update_post`, pour que la feuille de style de la page survive. Le reste de la page n'est jamais repris de la requête.

## Limites

- Pas d'ajout de section, de duplication de carte ni de déplacement d'élément : la structure reste celle du HTML livré.
- Pas de multilingue, pas de validation avant publication. Chaque enregistrement est en ligne tout de suite.
- Le balisage se pose à la main, ou par l'agent qui génère le HTML. Un élément mal balisé renvoie une erreur 422.
- Le texte riche passe par `document.execCommand`, une fonction ancienne que les navigateurs prennent encore en charge.
- Testé sur WordPress 7.1.1 et PHP 8.4 seulement.
- En headless, le front (Next.js par exemple) ne charge ni ce JavaScript ni la session WordPress. Il faudrait un mode d'aperçu dédié, et il n'existe pas.

## Et Pencilino ?

[Pencilino by Rino](https://github.com/rinothecoder/pencilino) (v2.0.4, GPL-2.0-or-later), l'extension de Rino de Boer, a été testé dans le même laboratoire. L'approche diffère : Pencilino demande un thème construit pour lui, avec ses fonctions `pencil_text()`, `pencil_image()` et les autres. Il range toutes les valeurs dans une seule option, `pencil_values`, et tient son propre historique. Ici, on balise un contenu HTML existant et on s'appuie sur les révisions de WordPress.

| | Pencilino v2.0.4 | WPF Édition au clic v0.1.0 |
|---|---|---|
| Thème | à construire pour l'extension | n'importe lequel |
| Lignes de code | 2 619 PHP et 1 169 JS | 637 au total |
| Stockage | une option unique, réécrite à chaque enregistrement | le contenu de la page |
| Historique | table dédiée, sans restauration | révisions natives de WordPress |
| Droit vérifié | `edit_pages`, global | `edit_post` sur la page visée |
| Extension désactivée | le thème réaffiche ses textes par défaut | les textes saisis restent |
| Fonctions en plus | commentaires du client, rôle Clients, interface soignée | aucune |

Pencilino est plus complet côté client. Aucune faille exploitable par un visiteur anonyme n'y a été trouvée.

## Licence et crédits

- Extension : GPL-2.0-or-later, voir [plugin-wpf-clic/LICENSE](plugin-wpf-clic/LICENSE).
- Laboratoire, tests et documentation : MIT, comme le reste du dépôt.
- Conception et cahier des charges : Fabrice Ducarme, [WPFormation](https://wpformation.com).
- Code et tests : générés par Claude Code (Anthropic, Opus 5.5).
