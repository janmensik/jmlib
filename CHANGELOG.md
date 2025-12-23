# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-12-23

### Added
- Initial release of jmlib, a PHP library with helper functions for input and output transformations
- `JmLib` class with utility methods:
  - `utf2ascii()`: Convert UTF-8 strings to ASCII (removes diacritics)
  - `createPassword()`: Generate simple password strings
  - `text2seolink()`: Convert strings to SEO-friendly URLs
  - `parseFloat()`: Parse floats from strings with comma/decimal handling
  - `parseDate()`: Parse dates from various string formats to timestamps
  - `stripos()` and `strripos()`: Case-insensitive string position functions
  - `rmdirr()`: Recursively delete files and directories
  - `getip()`: Get client IP address
  - `getUrl()`: Reconstruct current page URL
  - `pagination()`: Generate pagination links structure
  - `getInterval()`: Get time intervals based on text names (today, yesterday, last week, etc.)
  - `countDays()`: Count days between timestamps
- `AppData` class for application data management
- `Database` class for database operations
- `Modul` class for module handling
- `UrlParameters` class for URL parameter processing
- PSR-4 autoloading support
- Composer package configuration
- Pest testing framework integration
- MIT license

### Dependencies
- PHP >= 8.0
- PestPHP/Pest ^4.1