# Releasing

The Laravel package is distributed through Packagist and the Vue renderer
through npm. A Git tag named `vX.Y.Z` starts the release workflow, and the tag
must match `package.json`.

## Prepare a release

1. Update the package version and changelog or upgrade notes.
2. Run the PHP, Vue, and documentation checks.
3. Commit the release changes.
4. Create and push the matching tag.

For example:

```bash
npm version 0.8.0 --no-git-tag-version
git add package.json package-lock.json
git commit -m "chore: release v0.8.0"
git tag v0.8.0
git push origin master v0.8.0
```

## Release gates

The tag workflow calls the reusable PHP, JavaScript, and documentation
workflows before publishing npm. npm provenance uses GitHub Actions OIDC and the
repository `NPM_TOKEN` secret.

Packagist reads the Git tag from GitHub. The initial package registration must
point to `https://github.com/thienbd203/inertia-table`.

Do not regenerate application table classes during an upgrade. Generated
classes belong to the application and may contain local changes.
