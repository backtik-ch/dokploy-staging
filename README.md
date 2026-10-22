# Dokploy · Projets

Gestion des projets Dokploy : vue d’ensemble, versions et dépendances des images, maintenance du site et environnements de staging.

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
Les rapports restent immuables, dédupliqués par projet, image, digest et plateforme.
La date de création de l’image détermine le dernier build, indépendamment de
l’ordre d’arrivée. Les dépendances de développement absentes et les informations
non relevées sont indiquées séparément. La validation et les autorisations sont
portées par des Form Requests. Les paquets sont stockés dans la table `dependency_report_packages`, reliée au
rapport par `dependencies()` ; aucune colonne JSON ne stocke ces données.
L’historique reste en base et l’affichage ne fait aucun appel réseau.

## Projets et maintenance

Un projet s’ouvre sur une **vue d’ensemble**. La navigation sépare **Vue d’ensemble**, **Stagings**, **Dépendances** et **Réglages**, avec le même titre et les mêmes boutons dans chaque onglet. Les stagings sont disponibles pour tous les projets, sans interrupteur d’activation. La configuration GitHub / Docker Compose peut être renseignée plus tard ; elle doit être complète pour créer un staging.

L’onglet **Dépendances** présente le langage du dernier build sur main. Le tableau compare les versions du lock et de l’image, avec recherche, pagination et catégories directes, transitives ou de développement. Les métadonnées complètes de l’image sont repliées sous le tableau. Si plusieurs images ou plateformes sont suivies, un sélecteur permet de passer de l’une à l’autre.

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
