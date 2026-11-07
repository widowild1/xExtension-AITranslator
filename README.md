================================================================================

                        xExtension-AITranslator

================================================================================



Extension de traduction neuronale et d'assistance éditoriale IA pour FreshRSS.



Conçue pour un environnement auto-hébergé haute performance, elle prend en 

charge aussi bien les instances locales (LibreTranslate) que les modèles de 

langage distants (Google Gemini, Mistral AI, OpenAI, DeepL).



--------------------------------------------------------------------------------

1. FONCTIONNALITÉS CLÉS

--------------------------------------------------------------------------------



* Multi-Moteurs & Haute Disponibilité : 

  Moteur principal localisé (LibreTranslate sans dépendance externe ni quota) 

  avec bascule automatique de secours (fallback) vers Gemini ou Mistral en 

  cas de coupure.



* Pré-traduction en arrière-plan (Option A) : 

  Intercepte le hook d'ingestion de FreshRSS (entry_before_insert) pour 

  pré-traduire les flux étrangers dès le passage du cron (actualize_script.php). 

  Affichage instantané sans attente (0 seconde).



* Portabilité complète (Option B) : 

  Exportation et importation en un clic de la configuration, des règles 

  granulaires par flux (parmi des centaines de flux) et du glossaire technique 

  au format JSON standard.



* Traduction sélective contextuelle (Option C) : 

  Surlignez une phrase ou une expression à la souris dans un article pour faire 

  apparaître une pastille et son infobulle de traduction dédiée.



* Classifieur linguistique contextuel (TagClassifier) :

  - Détection lexicale ciblée et analyse croisée avec les tags FreshRSS.

  - Neutralisation des faux positifs sur les releases multimédias.

  - Priorité stricte aux marqueurs grammaticaux anglophones pour contourner 

    les tags IA générés en français.



* Rattrapage d'archives en ligne de commande (batch_translate_history.php) :

  - Pagination par curseur et verrou exclusif anti-concurrence (flock).

  - Gestion de reprise intelligente sur limitation de débit (HTTP 429) avec 

    backoff exponentiel.



* Préservation stricte du code source : 

  Isolation des balises <pre>, <code> et des chemins d'accès par balisage 

  DOM neutre pour éviter toute altération par le traducteur.



* Synthèse TL;DR & Vue bilingue : 

  Génération de résumés structurés en 3 à 5 points clés et affichage 

  côte à côte Original / Traduit.



* Cache intelligent SQLite : 

  Base data/translator.db avec hachage canonique (SHA-256) évitant toute 

  requête redondante.



--------------------------------------------------------------------------------

2. PRÉREQUIS SYSTÈME & DÉPENDANCES PHP

--------------------------------------------------------------------------------



Sur l'hôte ou le conteneur hébergeant FreshRSS (Debian/Ubuntu) :



  apt update && apt install -y \

    php-curl \

    php-sqlite3 \

    php-mbstring \

    php-xml \

    php-pgsql



Détail des paquets :

  - php-curl    : Communication HTTP avec l'API LibreTranslate ou distantes.

  - php-sqlite3 : Gestion de la base de données locale d'extension translator.db.

  - php-mbstring: Traitement sécurisé des encodages et chaînes UTF-8.

  - php-xml     : Manipulation et assainissement des fragments DOM HTML.

  - php-pgsql   : Pilote de base de données principal (ou php-mysql).



--------------------------------------------------------------------------------

3. TRAITEMENT PAR LOT DE L'HISTORIQUE (CLI)

--------------------------------------------------------------------------------



Un script CLI dédié permet de traduire rétroactivement les anciens articles

sans saturer les ressources du serveur :



  # Simulation à blanc (inventaire et vérification du classement)

  php batch_translate_history.php --limit=50 --dry-run



  # Traduire les 100 derniers articles étrangers non traités

  php batch_translate_history.php --limit=100



  # Traduire un flux RSS précis (ex: flux #12)

  php batch_translate_history.php --feed=12



  # Lancer la traduction de l'intégralité des flux en arrière-plan

  nohup php batch_translate_history.php --all > data/batch_history.log 2>&1 &



--------------------------------------------------------------------------------

4. BANC D'ESSAI AUTOMATISÉ EN LIGNE DE COMMANDE (CLI)

--------------------------------------------------------------------------------



Un script complet de validation fonctionnelle est intégré à la racine du dépôt :



  php /opt/freshrss/extensions/xExtension-AITranslator/test_suite.php



Éléments vérifiés par le banc d'essai :

  1. Syntaxe de l'ensemble des fichiers PHP du dépôt (php -l).

  2. Chargement de la classe d'extension au sein du framework Minz de FreshRSS.

  3. Intégrité structurelle de translator.db (cache, tags, feed_rules...).

  4. Communication et traduction réelle via LibreTranslate (localhost:5000).

  5. Intégrité des blocs de code <pre><code> après traduction.

  6. Module de traduction sélective par snippet.

  7. Hook entry_before_display (garantie de retour d'objet sans masquage).

  8. Injection de la barre d'outils et des boutons contextuels.

  9. Hook d'ingestion en tâche de fond entry_before_insert.

  10. Validité des structures JSON d'export/import et enregistrement horodaté 

      dans ActivityLogger.



--------------------------------------------------------------------------------

5. RACCOURCIS CLAVIER (LECTEUR DE FLUX)

--------------------------------------------------------------------------------



  T         : Traduire l'article ouvert immédiatement.

  Shift + T : Générer une synthèse exécutive structurée (TL;DR).

  B         : Basculer en vue bilingue côte à côte (Split View).

  Échap     : Fermer l'infobulle de traduction de sélection active.



--------------------------------------------------------------------------------

6. INTÉGRATION CONTINUE (FORGEJO ACTIONS)

--------------------------------------------------------------------------------



Le fichier .forgejo/workflows/ci.yml automatise la validation du code à 

chaque événement "git push" ou Pull Request sur la branche "main" de 

votre forge Git.



--------------------------------------------------------------------------------

7. CONFIDENTIALITÉ & GESTION DES DONNÉES

--------------------------------------------------------------------------------



Le fichier .gitignore isole strictement les données dynamiques et locales :

  - Répertoire data/ (translator.db, activity.log, batch_translation.lock).

  - Clés d'API et jetons d'accès (stockés dans la configuration FreshRSS).

  - URLs et hôtes locaux (anonymisés dans les commits publics).
