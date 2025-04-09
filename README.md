# DomainFlow System Events

[![Tests](https://github.com/domainflow/system-events/actions/workflows/tests.yml/badge.svg)](https://github.com/domainflow/system-events/actions/workflows/tests.yml)
![Packagist Version](https://img.shields.io/packagist/v/domainflow/system-events)
![PHP Version](https://img.shields.io/packagist/php-v/domainflow/system-events)
![License](https://img.shields.io/github/license/domainflow/system-events)
![PHPStan](https://img.shields.io/badge/PHPStan-Level%209-brightgreen.svg)

The **DomainFlow System Events** package captures and processes application lifecycle events using an extensible event-driven architecture. It includes a file-system logger with customizable log templates.


---

## ✨ Core Functionality

- **File-based Logging:** Capture and persist events to disk with a customizable template format.
- **Listener Architecture:** Uses attribute-driven event listeners to automatically register catch-all handlers.
- **Post-Boot Replay:** Replays pre-registered events that occurred before the service provider was loaded.
- **Environment Customization:** Configure log paths and templates via environment variables.

---

## ⚙️ Requirements

- **PHP 8.3+**
- Requires `domainflow/core`

---

## 📦 Installation

Use Composer to install the package:

```sh
composer require domainflow/system-events
```

---

More details and usage examples can be found in our [documentation](https://www.domainflow.dev/docs/system-events)

---

## 📄 License

The **DomainFlow System Events** package is open-sourced software licensed under the [MIT license](https://opensource.org/license/MIT).
