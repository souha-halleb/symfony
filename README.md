# MiniProjet2A-EventReservation

> Application Web de Gestion de Réservations d'Événements  
> **ISSAT Sousse — Département Informatique — FIA3-GL 2025/2026**

---

## 🎯 Description

Application web complète permettant :
- Aux **utilisateurs** : consulter des événements et réserver en ligne avec authentification Passkeys
- Aux **administrateurs** : gérer les événements et réservations via une interface sécurisée
- **Sécurité renforcée** : JWT (stateless API) + Passkeys/WebAuthn (FIDO2)

---

## 🛠️ Technologies utilisées

| Couche | Technologie |
|--------|-------------|
| Backend | PHP 8.2 / Symfony 7 |
| Authentification | JWT (LexikJWTBundle) + Passkeys (WebAuthn/FIDO2) |
| Base de données | PostgreSQL 14 (Doctrine ORM) |
| Frontend | Twig + Bootstrap 5.3 |
| Déploiement | Docker Compose (PHP-FPM + Nginx + PostgreSQL) |
| Tests | PHPUnit 10 |

---

## 📋 Prérequis

- PHP 8.2+ avec extensions : `openssl`, `pdo_pgsql`, `zip`
- Composer 2.x
- PostgreSQL 14+ **OU** Docker Desktop
- Git Bash (pour les commandes OpenSSL sur Windows)

---

## 🚀 Installation (sans Docker)

```bash
# 1. Cloner le dépôt
git clone https://github.com/VOTRE_USER/MiniProjet2A-EventReservation.git
cd MiniProjet2A-EventReservation

# 2. Installer les dépendances PHP
composer install

# 3. Copier et configurer l'environnement
cp .env .env.local
# Éditer .env.local : renseigner DATABASE_URL et JWT_PASSPHRASE

# 4. Générer les clés JWT (dans Git Bash)
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
chmod 600 config/jwt/private.pem config/jwt/public.pem

# 5. Créer la base de données et les tables
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
# OU pour le dev :
php bin/console doctrine:schema:update --force

# 6. Charger les données de démo
php bin/console doctrine:fixtures:load

# 7. Lancer le serveur
symfony server:start
# Ou : php -S 127.0.0.1:8000 -t public/
```

### Accès
| URL | Description |
|-----|-------------|
| http://localhost:8000 | Site public |
| http://localhost:8000/admin | Interface admin |
| http://localhost:8000/admin/login | Connexion admin |
| http://localhost:8000/api/auth/me | API (JWT requis) |

### Identifiants par défaut (fixtures)
```
Admin  → username: admin   /  password: admin1234
```

---

## 🐳 Installation avec Docker

```bash
# 1. Construire et démarrer
docker compose up -d --build

# 2. Vérifier les containers
docker compose ps

# 3. Générer les clés JWT dans le container
docker compose exec php mkdir -p config/jwt
docker compose exec php bash -c "openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:change_me_in_prod"
docker compose exec php bash -c "openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout -passin pass:change_me_in_prod"

# 4. Migrations et fixtures
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

# Application sur : http://localhost
```

---

## 🧪 Tests

```bash
# Créer la base de test
php bin/console doctrine:database:create --env=test
php bin/console doctrine:schema:create --env=test

# Lancer les tests
php bin/phpunit

# Tests spécifiques
php bin/phpunit --filter EventControllerTest

# Avec couverture de code
php bin/phpunit --coverage-html var/coverage
```

---

## 🌿 Branches Git

| Branche | Rôle |
|---------|------|
| `main` | Code stable et fonctionnel |
| `dev` | Intégration et tests |
| `feature/entities` | Entités Doctrine |
| `feature/jwt-passkeys` | Authentification JWT + WebAuthn |
| `feature/admin-crud` | Interface admin CRUD événements |
| `feature/reservation-form` | Formulaire de réservation |
| `feature/docker` | Configuration Docker |
| `feature/tests` | Tests PHPUnit |

---

## 📁 Structure du projet

```
event-reservation/
├── config/
│   ├── jwt/                    # Clés RSA (exclues du Git)
│   └── packages/               # security, jwt, webauthn…
├── src/
│   ├── Controller/             # HomeController, EventController,
│   │                           # ReservationController, AuthApiController…
│   ├── Entity/                 # Event, Reservation, User, Admin,
│   │                           # WebauthnCredential
│   ├── Repository/             # Repositories Doctrine
│   ├── Service/                # PasskeyAuthService
│   └── DataFixtures/           # Données de démo
├── templates/                  # Twig (base, event, reservation, admin, auth)
├── public/js/auth.js           # WebAuthn frontend
├── docker/                     # Nginx conf + PHP ini
├── docker-compose.yml
├── Dockerfile
└── README.md
```

---

## 🔐 API Endpoints

| Route | Méthode | Auth | Description |
|-------|---------|------|-------------|
| `/api/auth/register/options` | POST | Public | Challenge d'enregistrement Passkey |
| `/api/auth/register/verify`  | POST | Public | Vérification + génération JWT |
| `/api/auth/login/options`    | POST | Public | Challenge de connexion Passkey |
| `/api/auth/login/verify`     | POST | Public | Connexion + génération JWT |
| `/api/token/refresh`         | POST | Refresh token | Renouveler le JWT |
| `/api/auth/me`               | GET  | Bearer JWT | Profil utilisateur |

---

## 👤 Membres de l'équipe

| Nom | Rôle |
|-----|------|
| **[Prénom NOM]** | Développeur Full-stack — FIA3-GL |

---

## 📚 Ressources

- [WebAuthn Level 2 — W3C](https://www.w3.org/TR/webauthn-2/)
- [JWT RFC 7519](https://datatracker.ietf.org/doc/html/rfc7519)
- [Symfony Security](https://symfony.com/doc/current/security.html)
- [LexikJWTBundle](https://github.com/lexik/LexikJWTAuthenticationBundle)
