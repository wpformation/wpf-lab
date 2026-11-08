#!/bin/sh
# Monte le laboratoire : WordPress 7.1.1, trois comptes de test, trois images et la page balisée.
# Usage : sh setup.sh   (port par défaut 8088, autre port : WPF_LAB_PORT=8089 sh setup.sh)
set -e
cd "$(dirname "$0")"
PORT="${WPF_LAB_PORT:-8088}"

# Sous Git Bash (Windows), MSYS_NO_PATHCONV évite que /seed/... devienne C:/Program Files/Git/seed/...
wp() { MSYS_NO_PATHCONV=1 docker compose run --rm -T cli "$@" < /dev/null; }

[ -f seed/bol-paysage.jpg ] || python make_images.py seed

docker compose up -d db wordpress
printf 'Attente de WordPress'
until curl -s -o /dev/null --max-time 5 "http://localhost:$PORT/wp-login.php"; do printf '.'; sleep 2; done
echo

wp core install --url="http://localhost:$PORT" --title="Lab édition au clic" \
  --admin_user=admin --admin_password=lab-admin-local --admin_email=admin@example.test --skip-email
wp rewrite structure '/%postname%/'
wp user create cliente cliente@example.test --role=editor --user_pass=lab-cliente-local
wp user create abonne abonne@example.test --role=subscriber --user_pass=lab-abonne-local
# Cas le plus strict : une éditrice sans unfiltered_html, comme un client sur un multisite.
wp cap remove editor unfiltered_html

HERO_ID=$(wp media import /seed/bol-paysage.jpg --porcelain | tr -d '\r')
wp media import /seed/bol-portrait.jpg /seed/bol-carre.jpg
HERO_SRC=$(wp post get "$HERO_ID" --field=guid | tr -d '\r' | sed "s|http://localhost:$PORT||")

sed "s|__HERO_SRC__|$HERO_SRC|" seed/page-html.html > seed/page-html.generated.html
PAGE_ID=$(wp post create /seed/page-html.generated.html --post_type=page --post_status=publish \
  --post_title="Atelier Lumen (HTML balisé)" --post_name=atelier-html --user=admin --porcelain | tr -d '\r')
wp plugin activate wpf-clic

echo "Prêt : http://localhost:$PORT/atelier-html/ (page $PAGE_ID)"
echo "Connexion : cliente / lab-cliente-local, puis bouton « Modifier la page »."
