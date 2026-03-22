# 📋 COMMANDES À EXÉCUTER — Dans l'ordre exact

> **ISSAT Sousse — FIA3-GL — Mini Projet EventReservation**

---

## ÉTAPE 1 — Créer le projet Symfony

```bash
composer create-project symfony/skeleton:"^7.0" event-reservation
cd event-reservation
```

---

## ÉTAPE 2 — Installer toutes les dépendances

```bash
# Composants Symfony essentiels
composer require symfony/security-bundle
composer require symfony/orm-pack
composer require symfony/maker-bundle --dev
composer require symfony/twig-bundle
composer require symfony/form
composer require symfony/validator
composer require twig/extra-bundle
composer require symfony/asset
composer require symfony/string

# JWT
composer require lexik/jwt-authentication-bundle
composer require gesdinet/jwt-refresh-token-bundle

# WebAuthn / Passkeys
composer require web-auth/webauthn-lib
composer require web-auth/symfony-bundle

# Utilitaires
composer require symfony/uid symfony/http-client

# Dev / Tests
composer require doctrine/doctrine-fixtures-bundle --dev
composer require symfony/test-pack --dev
```

---

## ÉTAPE 3 — Générer les clés JWT (ouvrir Git Bash)

```bash
mkdir -p config/jwt

# Générer la clé privée RSA 4096 bits (entrez une passphrase quand demandé)
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096

# Extraire la clé publique
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout

# Sécuriser les permissions
chmod 600 config/jwt/private.pem config/jwt/public.pem

# Vérifier (vous devez voir les deux fichiers)
ls config/jwt
```

---

## ÉTAPE 4 — Configurer .env.local

Créer le fichier `.env.local` à la racine :

```bash
# Copier .env
cp .env .env.local
```

Puis éditer `.env.local` :

```
APP_SECRET=un_secret_aleatoire_de_32_caracteres
DATABASE_URL="postgresql://app_user:app_password@127.0.0.1:5432/event_reservation?serverVersion=14&charset=utf8"
JWT_PASSPHRASE=la_passphrase_saisie_a_letape_3
JWT_TOKEN_TTL=3600
APP_DOMAIN=localhost
WEBAUTHN_RP_NAME="EventReservation App"
```

---

## ÉTAPE 5 — Copier les fichiers du projet

Copier tous les fichiers fournis dans le ZIP vers votre projet :
- `src/` → entités, contrôleurs, services, repositories
- `config/packages/` → security.yaml, lexik_jwt_authentication.yaml, etc.
- `templates/` → tous les fichiers Twig
- `public/js/auth.js`
- `docker-compose.yml`, `Dockerfile`, `docker/`

---

## ÉTAPE 6 — Base de données

```bash
# Créer la base de données
php bin/console doctrine:database:create

# Générer et exécuter les migrations
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# OU directement (dev uniquement)
php bin/console doctrine:schema:update --force

# Charger les données de démo
php bin/console doctrine:fixtures:load
# → Admin : username=admin / password=admin1234
# → 5 événements créés
```

---

## ÉTAPE 7 — Lancer le serveur de développement

```bash
# Option A : Symfony CLI (recommandée)
symfony server:start

# Option B : PHP built-in
php -S 127.0.0.1:8000 -t public/

# Accès :
# Site public  → http://localhost:8000
# Admin        → http://localhost:8000/admin
```

---

## ÉTAPE 8 — Lancer avec Docker

```bash
# Construire et démarrer tous les containers
docker compose up -d --build

# Vérifier que tout tourne
docker compose ps

# Générer les clés JWT dans le container
docker compose exec php bash -c \
  "openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:change_me_in_prod"
docker compose exec php bash -c \
  "openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:change_me_in_prod"

# Migrations dans Docker
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

# Accès → http://localhost
```

---

## ÉTAPE 9 — Tests

```bash
# Créer la base de test
php bin/console doctrine:database:create --env=test
php bin/console doctrine:schema:create --env=test

# Lancer tous les tests
php bin/phpunit

# Tests avec filtre
php bin/phpunit --filter EventControllerTest

# Avec couverture de code HTML
php bin/phpunit --coverage-html var/coverage
```

---

## ÉTAPE 10 — GitHub

```bash
# Initialiser Git
git init
git add .
git commit -m "feat: initialisation projet EventReservation — FIA3-GL"
git branch -M main

# Connecter au dépôt GitHub
git remote add origin https://github.com/VOTRE_USER/MiniProjet2A-EventReservation-NomEquipe.git
git push -u origin main

# Créer la branche dev
git checkout -b dev
git push -u origin dev

# Créer une branche feature
git checkout -b feature/entities
# ... travailler ...
git add src/Entity/
git commit -m "feat(entities): ajouter Event, Reservation, User, Admin, WebauthnCredential"
git push origin feature/entities
```

---

## Commandes Symfony utiles

```bash
# Vider le cache
php bin/console cache:clear

# Valider les entités Doctrine
php bin/console doctrine:schema:validate

# Voir toutes les routes
php bin/console debug:router

# Voir la configuration de sécurité
php bin/console debug:firewall

# Créer une entité avec le wizard
php bin/console make:entity NomEntite

# Créer un contrôleur
php bin/console make:controller NomController

# Hacher un mot de passe manuellement
php bin/console security:hash-password
```
