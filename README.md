EventReservation
> Application Web de Gestion de Réservations d'Événements
---
Description
Application web complète développée avec Symfony 7 permettant :
Aux utilisateurs : consulter des événements et effectuer des réservations en ligne
Aux administrateurs : gérer les événements et réservations via une interface sécurisée
Sécurité renforcée : authentification JWT stateless + envoi d'emails transactionnels via Gmail SMTP
---
Technologies utilisées
Couche	Technologie
Backend	PHP 8.2 / Symfony 7
Authentification	JWT (LexikJWTBundle)
Emails	Gmail SMTP (`symfony/google-mailer`)
Base de données	PostgreSQL 15 (Doctrine ORM)
Frontend	Twig + Bootstrap 5.3
Déploiement	Docker Compose (PHP-FPM + Nginx + PostgreSQL)
Tests	PHPUnit 10
---
Authentification JWT
L'application utilise JSON Web Tokens (JWT) via le bundle `lexik/jwt-authentication-bundle` pour sécuriser les routes API de façon stateless.
Fonctionnement
L'utilisateur s'authentifie via `POST /api/auth/login` avec ses identifiants
Le serveur génère un token JWT signé avec une clé RSA privée
Le client inclut ce token dans chaque requête : `Authorization: Bearer <token>`
Le serveur valide le token avec la clé publique RSA
Configuration
Les clés RSA sont stockées dans `config/jwt/` (exclues du dépôt Git) :
```bash
# Générer les clés RSA (à faire une seule fois)
php bin/console lexik:jwt:generate-keypair
```
Variables d'environnement requises :
```env
JWT_SECRET_KEY=/var/www/config/jwt/private.pem
JWT_PUBLIC_KEY=/var/www/config/jwt/public.pem
JWT_PASSPHRASE=votre_passphrase
JWT_TOKEN_TTL=3600
```
Endpoints API
Méthode	Route	Description
`POST`	`/api/auth/login`	Obtenir un token JWT
`GET`	`/api/auth/me`	Infos utilisateur connecté (JWT requis)
`POST`	`/api/auth/refresh`	Rafraîchir le token
---
Envoi d'emails via Gmail
L'application utilise Gmail SMTP via le composant `symfony/google-mailer` pour envoyer des emails transactionnels (confirmation de réservation, notifications, etc.).
Configuration
```env
MAILER_DSN=gmail://votre.email@gmail.com:app_password@default
MAILER_FROM_EMAIL=noreply@eventreservation.com
MAILER_FROM_NAME=EventReservation
```

Installation du bridge Gmail
```bash
composer require symfony/google-mailer
```
Emails envoyés
Événement	Destinataire	Contenu
Nouvelle réservation	Utilisateur	Confirmation avec détails
Annulation	Utilisateur	Notification d'annulation
Nouveau compte	Utilisateur	Email de bienvenue
---
Installation et démarrage
Prérequis
Docker Desktop
Docker Compose
Lancer l'application


Accès
URL	Description
http://localhost:8080	Site public
http://localhost:8080/admin	Interface admin
http://localhost:8080/api/auth/login	Login API (JWT)
http://localhost:8080/api/auth/me	Profil API (JWT requis)
http://localhost:8081	pgAdmin (BDD)
---
Structure du projet
```
event-reservation/
├── config/
│   ├── jwt/                    # Clés RSA 
│   └── packages/               # security, jwt, mailer…
├── src/
│   ├── Controller/             # HomeController, EventController,
│   │                           # ReservationController, AuthApiController…
│   ├── Entity/                 # Event, Reservation, User, Admin
│   ├── Repository/             # Repositories Doctrine
│   ├── Service/                # MailService, ReservationService…
│   └── DataFixtures/           # Données de démo
├── templates/                  # Twig (base, event, reservation, admin, auth)
├── docker/                     # Nginx conf + PHP ini
├── docker-compose.yml
├── Dockerfile
└── README.md
```
---
Branches Git
Branche	Rôle
`main`	Code stable et fonctionnel
`dev`	Intégration et tests
`feature/entities`	Entités Doctrine
`feature/jwt-auth`	Authentification JWT
`feature/gmail-mailer`	Intégration Gmail SMTP
`feature/admin-crud`	Interface admin CRUD événements
`feature/reservation-form`	Formulaire de réservation
`feature/docker`	Configuration Docker
---
Variables d'environnement
Copier `.env` en `.env.local` et remplir les valeurs :
```env
APP_ENV=dev
APP_SECRET=your_32_char_secret_key

DATABASE_URL=postgresql://app_user:app_password@db:5432/event_reservation

JWT_SECRET_KEY=/var/www/config/jwt/private.pem
JWT_PUBLIC_KEY=/var/www/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase
JWT_TOKEN_TTL=3600

MAILER_DSN=gmail://your.email@gmail.com:your_app_password@default
MAILER_FROM_EMAIL=noreply@eventreservation.com
MAILER_FROM_NAME=EventReservation
```
## Capture d'écran
passkey.png
