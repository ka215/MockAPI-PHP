# Changelog

## [1.3.1] - 2025-07-17
### Added
- Added the ability to define CORS headers in the .env environment variable.
  ```
  CORS_ALLOW_ORIGIN=example.com
  CORS_ALLOW_METHODS="GET, POST, DELETE, PATCH, PUT, OPTIONS"
  CORS_ALLOW_HEADERS="Origin, X-Requested-With, Content-Type, Accept, x-api-key, x-csrf-token"
  CORS_ALLOW_CREDENTIALS=true
  ```
- The authorization process can be extended by providing a hook file `/hooks/authorization.php`.
- Added polyfills and helper methods for use with custom Hooks.

## [1.2.0] - 2025-04-01
### Added
- Added feature of OpenAPI 3.0 schema auto-generation.
- Added unit tests for the OpenAPI 3.0 schema auto-generation feature.

## [1.1.0] - 2025-03-17
### Added
- Implemented dynamic routing (support for `GET users/:group/:limit` format)
- Added sample responses
- Updated README (translated to English, and added examples for dynamic routing)
- Added changelog (this `CHANGELOG.md` file)

### Fixed
- Fixed error when timezone is not specified
- Fixed issues in unit tests

## [1.0.0] - 2025-03-14
### Initial Release
- Released the initial version
- Implemented basic mock API server functionality

---

#### 🔗 GitHub Releases
[1.3.1]: https://github.com/ka215/MockAPI-PHP/releases/tag/v1.3.1
[1.2.0]: https://github.com/ka215/MockAPI-PHP/releases/tag/v1.2.0
[1.1.0]: https://github.com/ka215/MockAPI-PHP/releases/tag/v1.1.0  
[1.0.0]: https://github.com/ka215/MockAPI-PHP/releases/tag/v1.0.0  
