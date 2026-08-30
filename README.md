# Elite Smiles CRM

PHP application for Elite Smiles lead, communications, patient-experience, and marketing operations.

## Safety boundary

This repository contains application code only. Do not commit patient information, credentials, production exports, uploads, logs, `.env`, or files from the local `.secrets` directory.

## Validation

Pull requests to `main` run PHP syntax checks, the automated test suite, the production CSS build check, and a blocking Semgrep scan. GitHub Actions are pinned to immutable commits.

## Delivery

Production deployment is performed only by the protected GitHub Actions workflow after validation. Manual deployment requires the `main` branch and the explicit deploy input. FTPS certificate verification is strict, and deployment is successful only when the authenticated health endpoint returns an `ok` JSON response.

See [SECURITY.md](SECURITY.md) for private vulnerability reporting.
