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

The tag workflow calls the reusable PHP, JavaScript, documentation and contract
workflows before publishing npm. The contract gate generates frontend URLs,
checks PHP normalization and resource freshness, and runs the packed consumer.
These workflow dependencies do not establish branch-protection required checks;
verify repository rules separately. npm provenance uses GitHub Actions OIDC and the
repository `NPM_TOKEN` secret.

## Required PR check and Dependabot

`pr-checks.yml` runs on every pull request without path filtering. Its final
`Required CI` job succeeds only when PHP, JavaScript, contracts/consumer and docs
all succeed; a failed, cancelled or skipped suite fails that gate. Configure an
active branch rule for `master` requiring this exact check after its first run.
Do not require individual path-filtered push workflows instead.

In repository Settings → General → Pull Requests, enable **Allow auto-merge**
and **Allow merge commits** (the bot uses `--merge`). The Dependabot workflow
only enables auto-merge for minor/patch updates on a protected target branch.
An unprotected target produces a notice and leaves the PR open. It does not
bypass checks, approve its own PR, or directly merge an unprotected branch.
Repository settings require a maintainer with sufficient permissions.

Major TypeScript updates are temporarily excluded from Dependabot while the
current `vue-tsc` uses TypeScript 5's compiler entry points. Existing major PRs
still need a maintainer decision; changing Dependabot configuration does not
make their incompatible code pass. DTS v5 requires the explicit development
dependency `@vue/language-core`; verify packed consumer declarations when
upgrading it.

Packagist reads the Git tag from GitHub. The initial package registration must
point to `https://github.com/thienbd203/inertia-table`.

Do not regenerate application table classes during an upgrade. Generated
classes belong to the application and may contain local changes.
