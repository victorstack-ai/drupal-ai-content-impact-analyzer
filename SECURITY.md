# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

**Do not report security vulnerabilities through public GitHub issues.**

For Drupal modules, security issues should be reported following the
[Drupal Security Team procedures](https://www.drupal.org/node/101494).

### How to Report

1. Visit https://www.drupal.org/node/101494 for the official process.
2. Alternatively, email security@drupal.org with details.
3. Include steps to reproduce, potential impact, and suggested fix if possible.

### What to Expect

- Acknowledgement within 48 hours.
- Assessment within 7 business days.
- Coordinated disclosure following Drupal Security Team guidelines.

## Threat Model

This module processes user-configurable settings through Drupal's Form API
and configuration system. Key security considerations:

- All user input is validated through Drupal's Form API.
- Configuration is stored using Drupal's configuration management system.
- Output is escaped using Drupal's rendering pipeline.
- Access is controlled through Drupal's permission system.
