# Security policy

This repository must never contain patient information, credentials, production exports, or incident details.

Report suspected vulnerabilities through GitHub's private vulnerability reporting on the Security tab. Do not open a public issue and do not test against production. Include the affected path, impact, and minimal reproduction steps using synthetic data only.

Supported security fixes target the current `main` branch. Production changes must pass the required validation and security checks and deploy through the protected GitHub Actions workflow.
