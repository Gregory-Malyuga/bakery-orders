# Security Policy

## Supported Versions

Bakery Orders is maintained as a single, continuously deployed application rather than a versioned library.

| Version / branch | Security updates |
| --- | --- |
| Latest commit on `main` | Supported |
| Older commits, branches, and deployments | Not supported |

Security fixes are applied to the current `main` branch. There are no separately maintained release lines.

## Reporting a Vulnerability

Please **do not** disclose potential vulnerabilities, exploit details, credentials, or personal data in public GitHub issues, pull requests, or discussions.

To report a vulnerability privately:

1. Visit the repository's [Security Advisories](https://github.com/Gregory-Malyuga/bakery-orders/security/advisories) page and use **Report a vulnerability** if private vulnerability reporting is enabled.
2. If that option is unavailable, contact the repository maintainer through a private channel before sharing sensitive details. Do not post a public report containing exploit instructions or secrets.

Where possible, include:
- A summary of the issue and its potential impact.
- Affected components and the commit or deployment version.
- Minimal steps to reproduce, with sensitive data redacted.
- Any suggested mitigation.

Reports will be reviewed and prioritized according to impact and exploitability. Fixes may be made on `main` before details are publicly disclosed. No guaranteed response or resolution timeline is currently offered.

## Security Updates

Dependencies and application images are checked as part of CI. A passing security scan does not guarantee the absence of vulnerabilities.
