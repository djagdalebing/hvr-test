# Her Vision Network — Frontend (reconstructed)

Angular 11 app reconstructed from the production source maps shipped with the
deployed bundle in `public_html/public/client/*.js.map`. The TypeScript /
HTML / SCSS sources under `src/` were extracted from `sourcesContent` in
those maps. Build config files (`package.json`, `angular.json`, `tsconfig.*`,
`polyfills.ts`, `index.html`, `environments/environment.ts`) were
reconstructed by hand and may need adjustment.

## Setup

```bash
# Use Node 14 or 16 (Angular 11 requires older Node)
nvm use 14
npm install
npm run build         # production build
npm start             # dev server on http://localhost:4200
```

## Output

A successful production build writes the new bundle to `dist/client/`. To
deploy: copy `dist/client/*` over the live `public_html/public/client/`.

## Likely follow-ups when first attempting `npm install` / `ng build`

- Some dep versions in `package.json` are best guesses — adjust if
  `npm install` reports peer-dep conflicts.
- A few imports may reference modules that weren't extracted (e.g.
  generated assets, JSON files); add stubs as needed.
- The Vebto framework code under `src/common/` is third-party — treat
  it as read-only and only modify `src/app/` for HVN-specific changes.
