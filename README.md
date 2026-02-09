# 🚀 Dokploy Staging Setup

This repository enables **automatic staging deployments** on **Dokploy** for every Pull Request using GitHub Actions.

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
