# Security Policy

## Supported versions

Security fixes are released for the latest `2.x` version. Earlier release lines no longer receive updates.

| Version | Supported |
|---------|-----------|
| 2.x     | Yes       |
| 1.x     | No        |
| 0.x     | No        |

## Reporting a vulnerability

Report vulnerabilities privately through [GitHub Security Advisories](https://github.com/drevops/behat-screenshot/security/advisories/new). Do not open a public issue for a security report.

Include the affected version, the steps to reproduce, and the impact you expect. A proof of concept helps but is not required.

You can expect an initial response within 7 days. Once a report is confirmed, a fix and an advisory are published together, crediting you unless you ask otherwise.

## Scope

This package is a development dependency that captures screenshots during Behat test runs. It reads the page content exposed by the Mink driver, writes files into a configured directory, and deletes files from that directory when `purge` is enabled. It executes no external commands.

In scope: token expansion producing unsafe file paths, writes or deletions escaping the configured directory, and captured content reaching unintended locations.
