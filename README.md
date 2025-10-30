# WPMoo CLI

[![CI](https://github.com/wpmoo-org/wpmoo-cli/actions/workflows/ci.yml/badge.svg)](https://github.com/wpmoo-org/wpmoo-cli/actions/workflows/ci.yml)
![PHP >=7.4](https://img.shields.io/badge/PHP-%3E%3D7.4-8892BF)
[![License: GPL-3.0-or-later](https://img.shields.io/badge/License-GPLv3%2B-green.svg)](https://www.gnu.org/licenses/gpl-3.0.en.html)

Modular command-line tools for the WPMoo framework. Ships commands like `info`, `check` (QA), `build`, `dist`, `deploy`, `version`, `release`, and `rename`.

## Installation

Add as a dev dependency in your project:

```
composer require --dev wpmoo/wpmoo-cli
```

Run the CLI:

```
php vendor/bin/moo --help
```

## Commands (quick peek)
- `info` — Show environment and project info
- `check` — Run local QA (lint/PHPCS/PHPStan where available)
- `build` / `dist` — Build assets / create distributable zip
- `deploy` — Build + package with deploy-oriented pruning
- `version` — Bump versions across plugin header, readme, package.json
- `release` — Safe release flow helpers
- `rename` — Starter plugin rename helper

For details, see the docs under Tools > CLI.
