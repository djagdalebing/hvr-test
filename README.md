# Her Vision Network

Laravel + Angular streaming platform deployed on Hostinger.

```
.
├── app/                  Laravel backend code
├── routes/
├── public/
├── public/client/        Compiled Angular SPA bundle (built by CI)
├── resources/
├── common/               Vebto framework
├── frontend-source/      Angular source — built into public/client/ by CI
└── .github/workflows/    GitHub Actions CI/CD
```

## Local development

### Backend

```bash
/opt/homebrew/opt/php@7.4/bin/php artisan serve
```

PHP 7.4 + MariaDB. See the bottom of this README for first-time setup.

### Frontend

```bash
cd frontend-source
nvm use 16
npm install --legacy-peer-deps
npx ng build       # one-shot build
# OR
npm start          # dev server with HMR
```

Output goes to `frontend-source/dist/client/`. To test locally, copy that
into `public/client/` and update the script tag hashes in
`resources/views/app.blade.php` (the `angular-scripts` section).

CI does this automatically on every push.

## Deployment (CI/CD)

Pushes are deployed automatically by `.github/workflows/deploy.yml`:

| Branch / event              | Target                |
|----------------------------|------------------------|
| push to `dummy`            | dummy site (testing)   |
| push to `main` or `master` | production             |
| manual `workflow_dispatch` | choose target          |

Each deploy runs:
1. `npm install` + `ng build --configuration production`
2. `composer install --no-dev --optimize-autoloader`
3. `rsync` everything to Hostinger over SSH (excluding dev folders, .env, etc.)
4. `php artisan view:clear` etc. on the server

### Required GitHub secrets

In repo Settings → Secrets and variables → Actions, set:

| Secret                  | Description                                                     |
|-------------------------|-----------------------------------------------------------------|
| `HOSTINGER_HOST`        | SSH host (IP or hostname from hPanel → Advanced → SSH Access)   |
| `HOSTINGER_PORT`        | SSH port (Hostinger usually uses 65002)                         |
| `HOSTINGER_USER`        | SSH user (e.g. `u181171629`)                                    |
| `HOSTINGER_SSH_KEY`     | Private SSH key (matching public key uploaded to Hostinger)     |
| `HOSTINGER_DUMMY_PATH`  | Full path to dummy site's `public_html`                         |
| `HOSTINGER_PROD_PATH`   | Full path to prod site's `public_html`                          |

Example values (update for your setup):

```
HOSTINGER_HOST          = 145.223.X.Y         # see hPanel SSH Access
HOSTINGER_PORT          = 65002
HOSTINGER_USER          = u181171629
HOSTINGER_DUMMY_PATH    = /home/u181171629/domains/aqua-narwhal-640720.hostingersite.com/public_html
HOSTINGER_PROD_PATH     = /home/u181171629/domains/hervisionnetwork.com/public_html
```

### One-time SSH setup

On your Mac:
```bash
ssh-keygen -t ed25519 -C "github-deploy" -f ~/.ssh/hvn_deploy -N ""
cat ~/.ssh/hvn_deploy.pub          # → paste into Hostinger SSH keys
cat ~/.ssh/hvn_deploy              # → paste as HOSTINGER_SSH_KEY secret
```

In hPanel: **Advanced → SSH Access → Manage SSH keys** → paste the public key.

## What gets deployed (and what doesn't)

The CI rsync excludes:
- `.git/`, `.github/` — repo metadata
- `frontend-source/` — source, not needed at runtime
- `node_modules/` — pulled fresh by CI for builds
- `.env*` — server has its own
- `storage/logs/*.log`, `bootstrap/cache/*.php`, etc. — runtime artifacts

Server's `.env` is **never** overwritten — keep DB credentials there safe.

## Daily workflow

```bash
git checkout dummy
# edit code...
git commit -am "feat: my change"
git push origin dummy        # → auto-deploys to dummy in ~3 min

# Test on dummy site, when happy:
git checkout main
git merge dummy
git push origin main         # → auto-deploys to prod
```

## First-time backend setup (local)

1. Install PHP 7.4 and MariaDB:
   ```bash
   brew tap shivammathur/php && brew install shivammathur/php/php@7.4 mariadb
   brew services start mariadb
   ```
2. Create local DB matching `.env`:
   ```bash
   /opt/homebrew/opt/mariadb/bin/mariadb -e "CREATE DATABASE hvn; \
     CREATE USER 'hvn'@'localhost' IDENTIFIED BY 'pass'; \
     GRANT ALL ON hvn.* TO 'hvn'@'localhost';"
   ```
3. Import production dump (if you have one) via phpMyAdmin/CLI.
4. Set up `.env` from `.env.deploy` template, fill in DB creds + APP_URL.
5. `php artisan key:generate` (or use the existing prod APP_KEY).
6. `php artisan storage:link`.
7. Run.

---

Heads up: the Angular source under `frontend-source/` was reconstructed
from the production source maps. Some interfaces/components are stubbed
for build compatibility — see `frontend-source/README.md` for caveats.
