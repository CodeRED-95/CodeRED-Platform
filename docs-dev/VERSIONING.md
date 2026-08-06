# Sistema de versionado automático

CodeRED Platform usa **versionado semántico** con actualización automática basada en tipos de commits.

## Versionado Semántico

Versiones en formato `MAJOR.MINOR.PATCH`:

- **MAJOR** (x.0.0) — Cambios incompatibles, breaking changes
- **MINOR** (0.x.0) — Nuevas características (feat:)
- **PATCH** (0.0.x) — Bug fixes (fix:)

Ejemplos:
- `2.0.0` → `3.0.0` = breaking change
- `2.1.0` → `2.2.0` = nueva feature
- `2.2.0` → `2.2.1` = solo bug fix

## Cómo funciona

### 1. Usar Conventional Commits

Escribe commits con formato:

```bash
# Nueva característica → minor bump
git commit -m "feat: agregar importación RUC v3.0"

# Bug fix → patch bump
git commit -m "fix: corregir error en validación de RUC"

# Breaking change → major bump
git commit -m "feat: reescribir API de tokens

BREAKING CHANGE: endpoint anterior removido"

# Documentación (no bumpa versión)
git commit -m "docs: actualizar README"
```

### 2. Hook detecta tipo automáticamente

Al hacer commit, el git hook `.git/hooks/prepare-commit-msg` detecta:

```
✓ feat:       → MINOR
✓ fix:        → PATCH
✓ BREAKING:   → MAJOR
✓ docs:       → Sin cambio
```

### 3. Script artisan bumpa versión

El hook sugiere comando a ejecutar:

```bash
# Para nueva feature
php artisan app:bump-version minor --reason="RUC v3.0 release"

# Para fix
php artisan app:bump-version patch --reason="Corregir validación"

# Para breaking change
php artisan app:bump-version major --reason="Reescritura de API"
```

### 4. Archivos se actualizan automáticamente

El comando actualiza:
- `composer.json` → versión en `extra.version`
- `config/version.php` → versión en config
- `config/app.php` → versión fallback
- `CHANGELOG.md` → nueva entrada con fecha

## Setup

### Instalar hooks (primera vez)

```bash
chmod +x bin/setup-git-hooks.sh
./bin/setup-git-hooks.sh enable
```

Output:
```
✓ Hook instalado: .git/hooks/prepare-commit-msg
✓ Versionado automático habilitado
```

### Desinstalar hooks (opcional)

```bash
./bin/setup-git-hooks.sh disable
```

### Ver estado

```bash
./bin/setup-git-hooks.sh status
```

## Workflow completo

```bash
# 1. Hacer cambios
vim app/Modules/Ruc/Services/MyService.php

# 2. Staged cambios
git add app/Modules/Ruc/Services/MyService.php

# 3. Commit con tipo (feat, fix, etc)
git commit -m "feat: agregar streaming de archivos RUC"

# 4. Hook detecta automáticamente 'feat' y sugiere:
# ℹ️  Tipo de cambio detectado: feat
# 💡 Sugerencia: considere bumpar MINOR version
# Comando: php artisan app:bump-version minor

# 5. Ejecutar comando sugerido
php artisan app:bump-version minor --reason="Streaming support"

# 6. Verificar cambios
git status
# M composer.json
# M config/version.php
# M CHANGELOG.md

# 7. Staged cambios de versión
git add composer.json config/version.php CHANGELOG.md

# 8. Amend al commit anterior
git commit --amend --no-edit

# 9. Push con tags
git push origin main
git tag v2.2.0
git push origin v2.2.0
```

## Archivos actualizados por `app:bump-version`

### composer.json
```json
{
    "extra": {
        "version": "2.2.0"
    }
}
```

### config/version.php
```php
'current' => env('APP_VERSION', '2.2.0'),
```

### CHANGELOG.md
```markdown
## [2.2.0] - 2026-08-06

### ℹ️ Nota
- Streaming support for unlimited file sizes
```

## Convenciones de commits

### Tipos válidos

| Tipo | Bump | Uso |
|------|------|-----|
| `feat` | MINOR | Nueva característica |
| `fix` | PATCH | Bug fix |
| `docs` | — | Cambios en documentación |
| `style` | — | Formato, espacios, semicolons |
| `refactor` | — | Refactoring sin cambio funcional |
| `perf` | PATCH | Mejora de rendimiento |
| `test` | — | Tests nuevos o modificados |
| `chore` | — | Dependencias, build, deployment |

### Con BREAKING CHANGE

```bash
git commit -m "feat: reescribir módulo RUC

BREAKING CHANGE: tabla ruc_staging eliminada"
```

Resultado: **MAJOR bump**

### Sin especificar tipo

```bash
git commit -m "update dependencies"
```

Resultado: **Sin bump** (requiere especificar tipo)

## Preguntas frecuentes

### P: ¿Qué pasa si hago commit sin tipo?
R: El hook no sugiere bump. Debes especificar tipo (feat:, fix:, etc.)

### P: ¿Puedo bumpar versión manualmente?
R: Sí, usar comando directo:
```bash
php artisan app:bump-version minor
# Pregunta confirmación
# Actualiza archivos
# Sugiere próximos pasos
```

### P: ¿Qué pasa si hago push sin actualizar versión?
R: Nada automático. La versión se queda igual. Debes actualizar manualmente si es necesario.

### P: ¿Cómo veo la versión actual?
R: Varios métodos:
```bash
# En PHP
php artisan app:version

# En web
curl https://platform.codered.host/api/v1/version

# En composer.json
grep version composer.json

# En config
php artisan config:get version.current
```

### P: ¿Los tags son obligatorios?
R: Recomendado pero no forzado. Para release oficial:
```bash
git tag -a v2.2.0 -m "Release 2.2.0"
git push origin v2.2.0
```

## Integración con CI/CD

Si tienes CI/CD, puedes automatizar:

```yaml
# .github/workflows/release.yml
name: Release
on:
  push:
    branches: [main]

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Detectar cambios de versión
        run: |
          CURRENT=$(grep -oP '"version":\s*"\K[^"]+' composer.json)
          echo "VERSION=$CURRENT" >> $GITHUB_ENV
      
      - name: Crear release
        if: env.VERSION != steps.previous.outputs.version
        run: |
          gh release create v${{ env.VERSION }} \
            --title "Release ${{ env.VERSION }}" \
            --generate-notes
```

## Mejores prácticas

1. **Usa tipos correctos** — feat/fix/docs en cada commit
2. **Commits atómicos** — Un cambio por commit
3. **Mensajes descriptivos** — "feat: add X" no "update code"
4. **Amend para versión** — `git commit --amend` después de bump
5. **Push con tags** — `git push origin main --tags`
6. **Changelog actualizado** — Revisa antes de release
7. **Documentación sincronizada** — Docs también en repo

## Véase también

- [CHANGELOG.md](../CHANGELOG.md) — Historial de versiones
- [CONTRIBUTING.md](./CONTRIBUTING.md) — Guía de contribución
- [Semantic Versioning](https://semver.org) — Especificación oficial
- [Conventional Commits](https://www.conventionalcommits.org) — Formato de commits
