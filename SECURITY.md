# Security policy

## Supported versions

Security fixes are provided for the latest released minor version while the
package is below `1.0`. After `1.0`, the current major version and the immediately
previous major version will receive security fixes when practical.

## Reporting a vulnerability

Do not open a public issue for a suspected vulnerability. Use GitHub's private
security advisory flow for this repository:

<https://github.com/thienbd203/inertia-table/security/advisories/new>

Include the affected Composer and npm package versions, Laravel/Inertia versions,
reproduction steps, impact and any suggested mitigation. You should receive an
acknowledgement within seven days. Please allow time for a coordinated fix and
release before publishing details.

## Security boundary

The package treats table definitions on the server as the authority. Applications
must still authorize their table queries, actions, Saved Views and exports, and
must not expose sensitive columns merely by hiding them in the browser.
