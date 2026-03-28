# EventReservation

> Application Web de Gestion de Réservations d'Événements  

## Description

Application web complète permettant :
- Aux **utilisateurs** : consulter des événements et réserver en ligne avec authentification Passkeys
- Aux **administrateurs** : gérer les événements et réservations via une interface sécurisée
- **Sécurité renforcée** : JWT (stateless API) + Passkeys/WebAuthn (FIDO2)


## Technologies utilisées

| Couche | Technologie |
|--------|-------------|
| Backend | PHP 8.2 / Symfony 7 |
| Authentification | JWT (LexikJWTBundle) + Passkeys (WebAuthn/FIDO2) |
| Base de données | PostgreSQL 14 (Doctrine ORM) |
| Frontend | Twig + Bootstrap 5.3 |
| Déploiement | Docker Compose (PHP-FPM + Nginx + PostgreSQL) |
| Tests | PHPUnit 10 |


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
# Application sur : http://localhost



##  Branches Git

| Branche | Rôle |
|---------|------|
| `main` | Code stable et fonctionnel |
| `dev` | Intégration et tests |
| `feature/entities` | Entités Doctrine |
| `feature/jwt-passkeys` | Authentification JWT + WebAuthn |
| `feature/admin-crud` | Interface admin CRUD événements |
| `feature/reservation-form` | Formulaire de réservation |
| `feature/docker` | Configuration Docker |


## Structure du projet

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





##  Ressources

- [WebAuthn Level 2 — W3C](https://www.w3.org/TR/webauthn-2/)
- [JWT RFC 7519](https://datatracker.ietf.org/doc/html/rfc7519)
- [Symfony Security](https://symfony.com/doc/current/security.html)
- [LexikJWTBundle](https://github.com/lexik/LexikJWTAuthenticationBundle)
