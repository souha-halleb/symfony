# Ce dossier contiendra les clés JWT (private.pem et public.pem).
# Ces fichiers sont EXCLUS du dépôt Git (.gitignore).
# Génération des clés (Git Bash / Linux) :
#
#   mkdir -p config/jwt
#   openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096
#   openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
#   chmod 600 config/jwt/private.pem config/jwt/public.pem
