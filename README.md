# Dokploy · Projets

Gestion des projets Dokploy : vue d’ensemble, maintenance du site et environnements de staging. Les dépendances et les autres indicateurs de santé pourront être ajoutés à la vue projet ultérieurement.

## Projets et maintenance

Un projet s’ouvre sur une **vue d’ensemble**. La navigation sépare **Vue d’ensemble**, **Stagings** et **Réglages**, avec le même titre et les mêmes boutons dans chaque onglet. Les stagings sont disponibles pour tous les projets, sans interrupteur d’activation. La configuration GitHub / Docker Compose peut être renseignée plus tard ; elle doit être complète pour créer un staging.

1. Dans **Connexions Dokploy**, donnez un nom explicite à chaque couple instance/token (par exemple « Production · équipe »). Le sélecteur affiche le nom, l’URL et l’identifiant de connexion, sans exposer le token. Les anciennes connexions restent sélectionnables grâce à leur identifiant.
2. Dans **Projet → Réglages → Projet**, choisissez cette connexion et, si nécessaire, l’ID du serveur distant. Un serveur vide désigne le serveur local Dokploy.
3. Dans **Réglages → Maintenance**, renseignez **Fichier YAML de maintenance** (`maintenance_yml_file`) : par exemple `veille-maintenance.yml`, relatif à `/etc/dokploy/traefik/dynamic`, ou un chemin absolu sur le serveur.
4. Le statut est chargé à l’ouverture de la fiche et conservé en cache pendant **3 minutes**, y compris entre les changements d’onglet. Un seul bouton propose **Mettre en maintenance** ou **Remettre en ligne** selon cet état. Le bouton **Créer un staging** ouvre une modale au même endroit dans les trois onglets. Dans la vue d’ensemble, l’icône à deux flèches à droite de **Maintenance du site** force une lecture sans cache. Les actions explicites de maintenance restent accessibles dans le menu de la liste des projets.

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
