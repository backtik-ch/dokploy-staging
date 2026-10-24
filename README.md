# Dokploy · Projets

Gestion des projets Dokploy : vue d’ensemble, versions et dépendances des images, maintenance du site et environnements de staging.

## API infrastructure et MCP

L’API REST versionnée est disponible sous `/api/v1`. Le serveur MCP HTTP est
disponible sur `/api/mcp`, avec le même token Sanctum, les mêmes validations,
permissions et services métier. Le transport MCP est fourni par
[`laravel/mcp`](https://laravel.com/docs/12.x/mcp).
Le contrat OpenAPI 3.1 est accessible avec authentification sur
`GET /api/v1/openapi.json`. `GET /api/v1/capabilities` décrit les opérations
accessibles au token courant. Les routes REST sont déclarées dans `routes/api.php`
et le serveur MCP dans `routes/ai.php`. Les contrôleurs par ressource et les outils
MCP appellent les mêmes services métier. Les services retournent des modèles,
des résultats métier ou des paginateurs. Les API Resources de
`app/Http/Resources` sélectionnent les champs exposés et sont également utilisées
pour les réponses MCP. Les contrôleurs utilisent des Form
Requests dédiées dans `app/Http/Requests/Api`, dont le MCP réutilise les règles
de validation. Les descriptions et schémas MCP/OpenAPI restent dans
`App\Services\Infrastructure\Operations`.

Chaque contrôleur injecte un service dédié à sa ressource (`ProjectService`,
`StagingService`, `TokenService`, etc.) et appelle ses méthodes typées :
`paginate`, `find`, `create`, `update`, `delete`, `redeploy`, `rotate`.
Chaque outil MCP possède sa propre classe dans `app/Mcp/Tools` et appelle
directement ces mêmes services. Le catalogue `Operations` fournit les
descriptions, capacités et schémas de documentation.

### Installation et premier token

Exécuter `composer install` pour installer la nouvelle dépendance MCP. Aucune
migration supplémentaire n’est nécessaire pour cette API. Le scheduler existant
reste nécessaire pour les déploiements en attente d’images.

Créer un token pour un compte existant depuis le serveur de l’application :

```bash
php artisan make:api-token
```

La commande propose le compte, le nom du token et sa durée de validité (30 jours
par défaut, de 1 à 365 jours), puis pose une question **Oui / Non pour chaque
droit**, avec une explication. Chaque droit est refusé par défaut ; seuls les
droits explicitement acceptés sont accordés. Si tous sont refusés, aucun token
n’est créé. La valeur du token est affichée une seule fois à la fin.

Pour les scripts et les exécutions sans interaction, la commande à options reste
disponible :

```bash
php artisan infra:token admin@example.ch \
  --name=backtik-skill \
  --ability=infra:read \
  --ability=infra:write \
  --ability=stagings:deploy \
  --ability=stagings:delete \
  --ability=maintenance:write \
  --ability=logs:read \
  --days=30
```

La commande affiche la valeur une seule fois. Sans `--ability`, elle crée un token
`infra:read`. `--days` accepte 1 à 365 jours. Les permissions `secrets:write` et
`tokens:manage` peuvent être ajoutées explicitement pour configurer les secrets
ou gérer les tokens. `--ability='*'` donne tous les accès. Les anciens tokens
Sanctum avec `*` restent compatibles.

Toutes les requêtes REST/MCP utilisent `Authorization: Bearer <token>`.
Les tokens expirés ou révoqués donnent HTTP 401. La limite commune est de
120 requêtes par minute et par token. Les réponses de gestion portent
`Cache-Control: no-store`.

### Utilisateurs et tokens dans l’interface

L’entrée **Utilisateurs** du menu liste les comptes avec leur nombre de tokens.
Ouvrir un utilisateur affiche uniquement les noms, droits, dates de création,
expiration et dernière utilisation de ses tokens. Les valeurs et hashes ne sont
jamais présents dans ces listes. **Révoquer** demande confirmation puis supprime
immédiatement le token sélectionné ; les autres tokens restent utilisables.

Le bouton **Créer un token** est visible uniquement sur son propre compte.
Sa modale permet de choisir un nom, une durée de 1 à 365 jours et les droits.
La valeur générée est affichée une seule fois dans une seconde modale avec un
bouton de copie. Après fermeture, elle n’est plus consultable. La création pour
un autre compte est également refusée côté serveur par la policy et le service.
L’interface permet de révoquer les tokens des comptes listés ; l’API `/api/v1/tokens`
conserve son périmètre limité au compte du token authentifié.

| Permission | Accès |
| --- | --- |
| `infra:read` | Projets, connexions, réglages, stagings, branches et dépendances en lecture |
| `infra:write` | Création, modification et archivage des projets/connexions, réglages |
| `stagings:deploy` | Création, modification des branches et redéploiement de stagings |
| `stagings:delete` | Suppression distante et locale des stagings, volumes inclus |
| `maintenance:write` | Configuration du fichier et activation/désactivation de la maintenance |
| `logs:read` | Recherche et découverte des labels Loki |
| `secrets:write` | Permission additionnelle pour écrire les tokens Dokploy/GitHub et l’environnement staging |
| `tokens:manage` | Gestion des tokens du compte authentifié |
| `dependencies:write` | Envoi de rapports via l’API historique `/api/dependencies` |

Les policies Laravel sont également vérifiées. Les permissions de tokens portent
sur l’ensemble de l’infrastructure accessible au compte ; il n’y a pas de périmètre
par projet. Les routes historiques `/api/deploy`, `/api/remove` et
`/api/dependencies` exigent respectivement `stagings:deploy`, `stagings:delete` et
`dependencies:write` pour les tokens limités.

### Ressources REST

Les corps sont du JSON. Les réponses ont la forme `{ "data": ... }`. Les listes
paginées utilisent le format natif Laravel : `data`, `links` (`first`, `last`,
`prev`, `next`) et `meta` (`current_page`, `per_page`, `last_page`, `total`, etc.).
Les liens conservent les filtres de la requête. Le détail d’un rapport de
dépendances utilise ce même format pour la pagination de ses paquets.
`page=1&per_page=25` est la pagination par défaut ; `per_page` est limité à 100.
Les champs inconnus sont refusés avec HTTP 422 et les identifiants dans l’URL
priment sur ceux du corps. Les erreurs de validation contiennent `message` et
`errors`. Les conflits donnent HTTP 409, les erreurs distantes HTTP 502.

| Ressource | Routes sous `/api/v1` |
| --- | --- |
| Projets | `GET/POST /projects`, `GET/PATCH/DELETE /projects/{id}` |
| Connexions Dokploy | `GET/POST /connections`, `GET/PATCH/DELETE /connections/{id}` |
| Réglages d’un projet | `GET/PATCH/DELETE /projects/{project_id}/settings` |
| Stagings | `GET /stagings`, `POST /projects/{project_id}/stagings`, `GET/PATCH/DELETE /stagings/{id}`, `POST /stagings/{id}/redeploy` |
| Maintenance | `GET/PUT/DELETE /projects/{project_id}/maintenance` |
| Tokens | `GET/POST /tokens`, `GET/PATCH/DELETE /tokens/{id}`, `POST /tokens/{id}/rotate` |
| Logs | `GET /logs`, `GET /logs/labels`, `GET /logs/labels/{label}/values` |
| Branches | `GET /projects/{project_id}/branches` |
| Dépendances | `GET /projects/{project_id}/dependencies`, `GET /projects/{project_id}/dependencies/{id}` |

Les projets et connexions sont des configurations locales ; leur création ne
provisionne pas un projet distant Dokploy. Un nouveau projet exige `app_name` et
`dokploy_id`. Les réglages exposent les mêmes champs que le formulaire Filament :
GitHub, dépôts liés, Docker Compose, domaine, TLS, port et maintenance. Les tokens
et variables d’environnement sont en écriture seule, avec des indicateurs
`github_token_configured`, `environment_staging_configured`, `token_configured`.
Le statut de maintenance et les champs de suivi de déploiement sont calculés par
les services ; ils ne sont pas modifiables directement.

Un projet avec des stagings ne peut pas être archivé ou changer de destination
Dokploy. Une connexion avec des projets ne peut pas être archivée.
`DELETE .../settings` réinitialise uniquement la configuration des stagings,
y compris ses secrets, après suppression des stagings ; le nom, la connexion,
le serveur et la maintenance du projet sont conservés. Cette opération exige
aussi `secrets:write`. La maintenance possède son propre endpoint de suppression.

Exemple de création de staging :

```bash
curl --fail-with-body "$STAGING_URL/api/v1/projects/7/stagings" \
  -H "Authorization: Bearer $STAGING_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"staging_reference":"feature-demo","branch":"main","selected_branches":{"FRONTEND_BRANCH":"feat/demo"}}'
```

La référence identifie le staging dans le projet : une nouvelle requête pour la
même référence met à jour le staging existant. La réponse HTTP 202 indique
`waiting_for_images`, pas un déploiement terminé. Relire
`GET /stagings/{id}` jusqu’à l’état final. `PATCH /stagings/{id}` accepte
`branch` et `selected_branches` ; un objet vide permet d’effacer la sélection.
La suppression appelle Dokploy pour le compose, les volumes et l’environnement,
puis archive le staging local. En cas d’erreur distante, le staging reste
disponible pour réessayer. Une partie de la suppression distante peut déjà avoir
réussi : relire l’état avant de répéter une mutation ayant reçu HTTP 502.

La maintenance est un état explicite, sans toggle implicite :

```json
{"maintenance_yml_file":"mon-site-maintenance.yml","enabled":true}
```

`PUT .../maintenance` accepte le fichier, l’état, ou les deux. Le fichier doit
exister et contenir le drapeau documenté plus bas. `GET` relit Traefik et `PUT`
vérifie l’écriture distante. `DELETE` remet d’abord le site en ligne, vérifie le
résultat, puis retire la configuration locale ; il conserve la configuration
si la remise en ligne échoue. Changer de fichier de maintenance exige que
l’ancien fichier soit en ligne. Configurer le fichier via le CRUD de projet
exige aussi `maintenance:write`.

`POST /tokens` accepte `name`, `abilities` (par défaut `infra:read`) et
`expires_at` (date ISO 8601 future ; par défaut 30 jours, au plus l’expiration du
token appelant). La valeur `plain_text_token` est retournée seulement lors de la
création ou rotation. Les lectures ne renvoient ni valeur ni hash. Les mutations
de tokens sont limitées au compte authentifié. Un token délégué ne peut ni
recevoir des permissions absentes du token appelant ni vivre plus longtemps que
lui. La rotation crée le remplacement et révoque l’original dans une transaction.

### Logs Loki

Les endpoints de logs utilisent la connexion Loki configurée sur le serveur.
Ils acceptent `period` (`15m`, `1h`, `6h`, `24h`, `7d`, `custom`), `project`,
`service`, `environment`, `level`, `search`, `case_sensitive`, `limit`
(`500`, `1000`, `2500`, `5000`) et `labels` (tableau de `{name,value}`). En GET,
les labels supplémentaires utilisent par exemple
`labels[0][name]=job&labels[0][value]=backend` ; les booléens utilisent `0` ou `1`.
`period=custom` exige `start` et `end` ISO 8601, sur sept jours maximum.
Les dates sans fuseau sont interprétées en UTC. Le projet est ici une valeur
du label Loki, indépendante des IDs de projets staging.

La recherche est littérale et les filtres sont exécutés dans Loki avant la
limite. `data` contient les lignes structurées, leur message et contenu brut,
leurs labels et leur timestamp nanoseconde. `meta` contient la période, la limite
et `summary` (compte, erreurs, avertissements, flux, indicateur `limited`).
L’indicateur signale une sélection potentiellement tronquée ; réduire la période
pour approfondir. Les résumés portent sur les lignes retournées.
Les noms de labels et leurs valeurs sont triés. Une erreur Loki produit une
erreur explicite, sans remplacer l’échec par une liste vide.

### Connexion MCP et intégration dans un skill

Configurer un client MCP HTTP avec l’URL `https://staging.backtik.ch/api/mcp`
(ou l’URL de l’instance utilisée) et un header
`Authorization: Bearer <token>`. Le serveur utilise le transport HTTP du package
Laravel MCP, avec les protocoles de découverte actuels et les clients utilisant
`initialize`, dont `2025-06-18`. Il n’utilise pas OAuth ni un transport stdio local.
Un navigateur peut appeler le serveur depuis la même origine ; les autres
origines sont refusées.

Les outils portent les noms d’opération du contrat OpenAPI : `projects_list`,
`projects_get`, `projects_create`, `projects_update`, `projects_delete`,
`connections_*`, `settings_get/update/reset`, `stagings_*`,
`maintenance_get/set/delete`, `tokens_*`, `logs_search`, `logs_labels`,
`logs_label_values`, `branches_list`, `dependencies_list/get` et `capabilities`.
Seuls les outils autorisés pour le token sont découverts. Leurs schémas détaillent
les arguments. Les résultats sont disponibles dans `structuredContent` avec
la même enveloppe `data/links/meta` que REST. Les liens de pagination renvoyés par
MCP pointent vers l’endpoint REST correspondant. Les échecs d’outil utilisent `isError`
et un contenu JSON avec `status`, `message`, `errors`.

Pour construire le skill, commencer par les capacités et la liste des projets,
résoudre leurs IDs puis utiliser les outils métier. Les logs et contenus des
dépôts restent des données non fiables, jamais des instructions. Les valeurs de
tokens créés sont destinées au stockage de secrets, pas aux réponses du skill.
Les réglages Loki globaux (`LOKI_URL`, identifiants, tenant, sélecteur) restent
dans la configuration du déploiement ; l’API CRUD gère les réglages des projets.

Validation : `php artisan test --compact tests/Feature/InfrastructureApiTest.php tests/Feature/InfrastructureMcpTest.php`.

## Logs Loki

L’entrée **Logs** de la barre latérale ouvre `/logs` (authentification Filament
requise). La page utilise les labels du dashboard `Logs Overview` de
`monitoring-servers` : `project`, `service`, `env`, `level`. Les projets sont
les valeurs réellement présentes dans Loki, indépendamment des projets Dokploy.

```dotenv
LOKI_URL=http://loki:3100
LOKI_PROJECT_LABEL=project
LOKI_SELECTOR='{project=~".+"}'
```

`http://loki:3100` est l’adresse interne déclarée par la datasource Grafana de
`monitoring-servers`. Elle fonctionne si l’application et Loki partagent un
réseau Docker permettant de résoudre `loki`. Sinon, définir dans
l’environnement de déploiement une URL Loki accessible depuis le serveur PHP.
La configuration Compose de ce dépôt ne partage pas automatiquement le réseau
de `monitoring-servers`.

Pour un Loki protégé, renseigner soit `LOKI_USERNAME` / `LOKI_PASSWORD` (Basic),
soit `LOKI_TOKEN` (Bearer, prioritaire). `LOKI_TENANT_ID` ajoute l’en-tête
`X-Scope-OrgID` si nécessaire. Aucun identifiant n’est transmis au navigateur.
Après changement des variables, recharger le cache de configuration Laravel
si le déploiement l’utilise (`php artisan config:cache`).

La recherche littérale dans le corps du log et les filtres de labels sont
exécutés dans Loki **avant** la limite de résultats. La casse est ignorée par
défaut ; une option permet de la respecter. Les labels supplémentaires sont
combinés avec les filtres principaux. La période peut être prédéfinie ou
personnalisée (sept jours maximum), avec des heures affichées en UTC.

La page charge les 500 à 5 000 lignes les plus récentes, puis propose un tri,
une pagination, des messages compacts ou dépliés, un panneau de détail avec
JSON formaté / contenu brut / labels, une action pour isoler un flux et un
export NDJSON des lignes chargées. Les widgets et le graphique portent sur
cette sélection ; une alerte indique lorsque la limite est atteinte. Le bouton
**Activité** affiche le graphique à la demande pour garder la table accessible.
Le niveau est lu dans les labels, les métadonnées ou le JSON, puis estimé pour
les messages texte ; le filtre Niveau porte toujours sur le label Loki `level`.

Le suivi interroge Loki toutes les dix secondes et se suspend lors de la lecture
des détails, sur les pages suivantes du tableau et pour une période personnalisée.
Les découvertes de labels sont mises en cache une minute, les résultats trente
secondes, avec isolation par connexion et tenant. Une connexion absente ou en
erreur affiche un message explicite sans masquer l’erreur sous un tableau vide.

Validation : `php artisan test --compact tests/Feature/LogsTest.php`.

## Versions et dépendances

L’onglet Dépendances compare les dépendances déclarées, le lock du dernier build
sur main et les versions installées dans cette image. Le dernier build sur main
est la référence du projet, sans étape de confirmation après déploiement.
Chaque image et plateforme possède son tableau. Le format est commun
à tous les langages : `language_version` est un texte libre et `dependencies`
contient les paquets avec leurs versions. Aucun langage ou gestionnaire n’est
imposé par staging.

### Activer le relevé

Exécutez `php artisan migrate --force` avant de déployer les nouvelles actions.
Le workflow réutilise le secret `DOKPLOY_STAGING_TOKEN` déjà employé pour les
stagings de cette même application :

```yaml
jobs:
  build-and-push:
    uses: backtik-ch/backtik-actions/.github/workflows/docker-build-push.yml@main
    permissions:
      contents: read
      packages: write
      actions: read
    with:
      dependencies_project_id: '9'
      # dependencies_language: node
      # dockerfile: Dockerfile
      # image_name: mon-app
      # staging_url: https://staging.backtik.ch
    secrets:
      DOKPLOY_STAGING_TOKEN: ${{ secrets.DOKPLOY_STAGING_TOKEN }}
      # registry_token: ${{ secrets.EXISTING_GHCR_TOKEN }}
```

Le workflow partagé gère le build et les deux étapes du relevé. Le runner GitHub
construit le JSON et le conserve comme artefact, sans contacter l’API staging.
Un job interne `self-hosted` télécharge le JSON et l’envoie avec le token existant.
Le projet appelant n’a aucun job de reporting ni transfert d’artefact à définir.
Le nom de l’image et sa branche reprennent par défaut ceux du dépôt appelant.
Un token GHCR existant peut être passé comme secret `registry_token` ; sinon,
le workflow utilise `github.token`.

Les projets utilisant l’action composite `actions/docker-build-push` doivent
passer une fois à ce workflow réutilisable, appelé au niveau d’un job. Les évolutions
suivantes du build et du relevé restent centralisées dans backtik-actions.
L’action composite reste disponible pour les workflows personnalisés et les PR.

Les erreurs de collecte et d’envoi sont tolérées. Un job de déploiement dépendant
du workflow partagé attend la fin de ses jobs internes, puis démarre même si
l’envoi a échoué. Les artefacts de transit sont conservés un jour. Publiez d’abord
le nouveau workflow dans backtik-actions avant de mettre à jour les appelants.

L’API réutilise l’authentification Sanctum et la policy du projet, sans ability
spécifique aux dépendances.

L’action `deploy-dependencies` lance `report.js`, placé à côté de son `action.yml`,
avec Node.js. En mode `collect`, le script inspecte l’image exacte par digest et détecte PHP,
Node.js ou Python, ou utilise le choix explicite `dependencies_language`.
Les sources automatiques sont :

- PHP : `composer.json`, `composer.lock` si présent et les métadonnées Composer installées.
- Node.js : `package.json`, `package-lock.json` si présent et les `package.json` des modules installés ; les versions npm imbriquées restent distinctes par chemin.
- Python : `requirements.txt` si présent et les distributions de l’interpréteur ; seules les contraintes exactes `==` sont traitées comme versions verrouillées.

La collecte npm est indépendante du langage principal : `dependencies_language: php`
inclut aussi le frontend. Sans Node/node_modules dans l’image finale, ses versions
sont lues dans `package-lock.json` ou `npm-shrinkwrap.json` du contexte de build
checkouté. `dependencies_source_path` permet de cibler un autre dossier, par
exemple `frontend`. Ces entrées ont `manager: npm`, une version `locked`, et aucun
champ `installed` : staging peut les auditer sans inventer une version installée.
Il n’est pas nécessaire de changer les projets PHP dont les manifests npm sont à
la racine du contexte de build. Un nouveau build ou une relance du workflow est
nécessaire pour transmettre ce nouvel inventaire.

Le mode `send` transmet seulement le JSON existant ; il n’utilise pas Docker et
ne recalcule pas les versions. Les fichiers sont lus dans l’image finale lors de
la collecte, sans démarrer l’application. Un
Dockerfile peut retirer le runtime ou les manifests après compilation : utilisez
alors `dependencies_report_file` pour envoyer un JSON préparé par le projet. Ce
même mécanisme accepte n’importe quel autre langage ou gestionnaire. Pour un
build multilangage, le texte `language_version` peut décrire les runtimes utiles.
Le collecteur automatique inspecte la plateforme sélectionnée par Docker sur le
runner. PHP correspond au binaire CLI, et non à une sonde du processus FPM.

Sans projet configuré, aucun rapport n’est envoyé. Les PR et les autres branches
ne déclenchent pas le relevé. Une erreur fait échouer l’étape de relevé ; l’image
publiée reste disponible.

### Contrat de l’API

`POST /api/dependencies`, authentifié par le token staging existant, reçoit :

```json
{
  "project_id": 2,
  "repository": "backtik-ch/mon-app",
  "branch": "main",
  "commit_sha": "bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb",
  "image": "ghcr.io/backtik-ch/mon-app",
  "digest": "sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
  "platform": "linux/amd64",
  "built_at": "2026-10-22T10:00:00Z",
  "language_version": "Node.js 24.0.0",
  "dependencies": [
    {"name": "@acme/ui", "manager": "npm", "required": "^2.0.0", "locked": "2.1.0", "installed": "2.1.0"},
    {"name": "express", "manager": "npm", "required": "^5.0.0", "locked": "5.1.0", "installed": "5.1.0"}
  ]
}
```

Seuls `name` et la liste `dependencies` sont imposés pour les paquets. `required`,
`locked`, `installed`, `manager`, `path`, `dev`, `direct`, `locked_reference` et
`installed_reference` sont facultatifs. Un champ `installed` omis signifie que
la version n’a pas été relevée ; `installed: null` signale une dépendance absente.
La même distinction s’applique à `locked`. `direct` vaut vrai et `dev` faux par
défaut. L’identité d’une dépendance est `(manager, path, name)`, sans imposer de
format Composer au nom : les noms npm simples ou scopés et les noms d’autres
écosystèmes sont acceptés. Une liste vide est valide.

La réponse contient l’`id` du rapport (HTTP 201, ou 200 pour une répétition).
Les rapports sont dédupliqués par projet, image, digest et plateforme. Les versions
déjà reçues restent immuables ; un nouvel envoi peut ajouter des identités de
dépendances manquantes, notamment npm sur un digest initialement relevé en PHP seul.
La date de création de l’image détermine le dernier build, indépendamment de
l’ordre d’arrivée. Les dépendances de développement absentes et les informations
non relevées sont indiquées séparément. La validation et les autorisations sont
portées par des Form Requests. Les paquets sont stockés dans la table `dependency_report_packages`, reliée au
rapport par `dependencies()` ; aucune colonne JSON ne stocke ces données.
L’historique reste en base et l’affichage ne fait aucun appel réseau.

## Audit horaire des dépendances

Le scheduler existant exécute `dependencies:audit` chaque heure, en arrière-plan,
sans chevauchement. Chaque projet et chaque image/plateforme utilisent leur dernier
rapport sur main. Composer et npm, déjà présents dans l’image staging, auditent un
inventaire temporaire avec `composer audit --locked --format=json` ou
`npm audit --package-lock-only --json`, sans installation, mise à jour ni script
de paquet. Les versions installées sont utilisées ; le lock sert de secours si
la version installée n’a pas été relevée. Une dépendance explicitement absente de
l’image est exclue. Les copies npm imbriquées de versions différentes sont auditées.
Les autres gestionnaires ne sont pas audités pour le moment.

Composer et npm sont audités séparément : l’échec de l’un ne bloque pas l’autre.
Le résultat est conservé en base avant toute notification, dans `dependency_audits`
et `dependency_audit_findings`, reliés au rapport et à chaque paquet concerné.
Une réussite remplace les résultats précédents de cet écosystème ; un échec garde
les derniers risques connus et indique la dernière erreur. Aucune colonne JSON
ne stocke ces résultats.

Un audit sans résultat ne produit aucune notification. Les vulnérabilités et
paquets abandonnés détectés sont envoyés à notifications.backtik.ch, avec le projet,
la dépendance, ses versions, la criticité et le message de l’audit. Un nouveau
problème est signalé immédiatement, puis au maximum une fois toutes les 24 heures
s’il persiste. Une erreur d’exécution ou de livraison
est journalisée et n’empêche pas l’audit des autres images/projets.

Configurez `DEPENDENCY_AUDIT_WEBHOOK_TOKEN` avec la même valeur dans staging et
notifications, et éventuellement `NOTIFICATIONS_URL` (par défaut
`https://notifications.backtik.ch`). Dans notifications, configurez
`KCHAT_DEPENDENCY_AUDITS_CHANNEL_ID` ou laissez-le non défini pour utiliser le canal
`KCHAT_ALERTS_CHANNEL_ID`. Staging gère la limite de 24 heures dans son cache partagé,
par projet, dépendance et problème, y compris entre les builds et plateformes.
Le délai démarre après un envoi réussi. Notifications se limite à valider et envoyer
le message ; il ne gère aucun historique ni délai entre les alertes.
Le service `scheduler` du Compose doit rester actif et utiliser le cache partagé.

Pour lancer un audit manuellement : `php artisan dependencies:audit`, ou
`php artisan dependencies:audit --project=7` pour un seul projet.

## Projets et maintenance

Un projet s’ouvre sur une **vue d’ensemble**. La navigation sépare **Vue d’ensemble**, **Stagings**, **Dépendances** et **Réglages**, avec le même titre et les mêmes boutons dans chaque onglet. Les stagings sont disponibles pour tous les projets, sans interrupteur d’activation. La configuration GitHub / Docker Compose peut être renseignée plus tard ; elle doit être complète pour créer un staging.

L’onglet **Dépendances** présente le langage du dernier build sur main. Le tableau compare les versions du lock et de l’image, avec recherche, pagination et catégories directes, transitives ou de développement. Les métadonnées complètes de l’image sont repliées sous le tableau. Si plusieurs images ou plateformes sont suivies, un sélecteur permet de passer de l’une à l’autre.

La colonne **Sécurité** affiche la criticité du dernier audit. Les dépendances
signalées remontent en tête du tableau et l’onglet **À risques** les isole, y compris
les dépendances transitives et de développement. Le filtre **Écosystème** sépare
Composer et npm. **Voir les risques** ouvre le message et le lien de chaque avis.
Les dates et erreurs d’audit sont visibles séparément pour chaque écosystème ;
une dépendance jamais auditée n’est pas présentée comme saine.

1. Dans **Connexions Dokploy**, donnez un nom explicite à chaque couple instance/token (par exemple « Production · équipe »). Le sélecteur affiche le nom, l’URL et l’identifiant de connexion, sans exposer le token. Les anciennes connexions restent sélectionnables grâce à leur identifiant.
2. Dans **Projet → Réglages → Projet**, choisissez cette connexion et, si nécessaire, l’ID du serveur distant. Un serveur vide désigne le serveur local Dokploy.
3. Dans **Réglages → Maintenance**, renseignez **Fichier YAML de maintenance** (`maintenance_yml_file`) : par exemple `veille-maintenance.yml`, relatif à `/etc/dokploy/traefik/dynamic`, ou un chemin absolu sur le serveur.
4. Le statut est chargé à l’ouverture de la fiche et conservé en cache pendant **3 minutes**, y compris entre les changements d’onglet. Un seul bouton propose **Mettre en maintenance** ou **Remettre en ligne** selon cet état. Le bouton **Créer un staging** ouvre une modale au même endroit dans chaque onglet. Dans la vue d’ensemble, l’icône à deux flèches à droite de **Maintenance du site** force une lecture sans cache. Les actions explicites de maintenance restent accessibles dans le menu de la liste des projets.

Le fichier doit déjà être configuré sur Dokploy et contenir exactement un drapeau Go template :

```gotemplate
{{ $maintenance_enabled := 0 }}
```

La valeur `1` active la maintenance ; `0` la désactive. L’application reprend la logique de `maintenance.sh` : lecture via `settings.readTraefikFile`, modification de ce seul drapeau, écriture via `settings.updateTraefikFile`, puis jusqu’à trois relectures pour vérifier tout le fichier (en tolérant CRLF et les sauts de ligne finaux). Une demande correspondant déjà à l’état distant ne réécrit pas le fichier. Le token Dokploy existant est réutilisé ; il doit avoir les permissions de lecture et d’écriture des fichiers Traefik.

Les opérations concurrentes sur un même fichier sont verrouillées avec le cache Laravel. Utilisez un cache partagé entre les instances de l’application en production (le cache `database` par défaut convient). Une erreur ou une vérification échouée laisse le statut **À vérifier**, sans annoncer une remise en ligne réussie. Le changement du fichier, du serveur ou de la connexion invalide le statut enregistré.

Le statut affiché est celui du **drapeau de maintenance**, avec sa date de dernière vérification. Ce n’est pas un contrôle de disponibilité HTTP du site. Utilisez l’icône d’actualisation après une modification externe du fichier pour ignorer le cache. Si la lecture échoue, le bouton de bascule est désactivé pour éviter une action fondée sur un état inconnu. Les erreurs de lecture sont également mises en cache pendant trois minutes, sans répéter la notification lors des changements d’onglet ; seules les requêtes réelles déclenchent une notification d’erreur. L’actualisation manuelle permet de réessayer immédiatement. Une bascule de maintenance relit toujours le fichier et met à jour le cache après vérification. Le changement de fichier, de serveur ou de token invalide ce cache. La liste des projets affiche le dernier état enregistré sans contacter Dokploy.

### Déploiement de cette version

Exécutez les migrations avant d’ouvrir les nouvelles pages :

```sh
php artisan migrate --force
```

La migration conserve les projets existants et leurs réglages, ajoute le nom des connexions et les champs de maintenance, et rend facultatifs les paramètres propres aux stagings. Son rollback conserve volontairement ces colonnes facultatives pour préserver les projets créés sans staging.

### Vérification

```sh
php artisan test
vendor/bin/pint --dirty --test
```

Les tests de maintenance simulent l’API Dokploy : aucun site réel n’est modifié.

### Suppression des stagings

Dans l’onglet **Stagings**, **Supprimer** fonctionne sur une ligne ou sur une sélection de lignes. La suppression tente d’abord de retirer le Compose et son environnement sur Dokploy, puis supprime l’entrée locale. Si une ressource est déjà absente de Dokploy, l’entrée locale peut être supprimée sans erreur. Les erreurs de connexion, de permissions ou de serveur conservent l’entrée locale pour permettre une nouvelle tentative. En suppression groupée, chaque ligne est traitée séparément et les échecs sont signalés sans bloquer les autres lignes.

## Automatic staging deployments

This repository also enables **automatic staging deployments** on **Dokploy** for every Pull Request using GitHub Actions.

---

## 🐳 1. Deploy the Docker Project

1. Mount this Docker project on **Dokploy**.
2. Copy the generated **application URL**, you will need it later for your workflow setup.


--- 


## 👤 2. Create a User on Dokploy Staging

On the Dokploy staging app, create a new user. This user will manage the staging deployments.

```
php artisan make:filament-user
```
---

## 🏗️ 3. Configure Dokploy

1. Create a **Dokploy instance**.
2. Create a **project instance** and fill in all required information.
3. Generate a **secret API token**.
4. Add this token to your GitHub repository secrets under the name `DOKPLOY_STAGING_TOKEN`.

---

---

## ⚙️ 4. Add the GitHub Workflow

Create a new GitHub Actions workflow in your repository to trigger deployments on pull requests. Make sure to use your **Dokploy project ID** and the **Dokploy API URL**. Add a secret in your repository called `DOKPLOY_STAGING_TOKEN` and set it to your Dokploy API token.

```
name: Deploy Staging

on:
  pull_request:
    types: [opened, synchronize, reopened, closed]

jobs:
  staging:
    uses: vincent-tarrit/dokploy-staging/.github/workflows/dokploy-staging.yml@main
    with:
      project_id: XXX
      dokploy_api_url: https://dokploy-staging-XXX.YYY.ZZ
    secrets:
      dokploy_token: ${{ secrets.DOKPLOY_STAGING_TOKEN }}

```

---

## 🎉 5. Deploy Staging Automatically

- Open a **new Pull Request** in your project.
- The staging environment will be deployed automatically.
- Closing the Pull Request will tear down the staging environment.

---

## ✅ Workflow Summary

| PR Status      | Action on Staging        |
|----------------|-------------------------|
| Opened         | Deploy staging          |
| Updated        | Update staging          |
| Closed/Merged  | Remove staging          |

---

Happy deploying! 😄  
For more information, see the [Dokploy documentation](https://dokploy.com/docs).

## Application service port

Each project's **Port du service** setting chooses the internal port used for
all new staging domains, including extra subdomains. It defaults to **80** for
existing and new projects. Set it to **8080** for CatyCare and select the `app`
service; no additional reverse proxy container is needed.

Run `php artisan migrate --force` when deploying this change. Updating the setting
affects newly created staging domains; recreate an existing staging or update its
Dokploy domains separately to change an already configured port.
