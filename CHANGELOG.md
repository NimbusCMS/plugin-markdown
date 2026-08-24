# Changelog

Notable changes to the official NimbusCMS Markdown plugin. Follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
[Semantic Versioning](https://semver.org).

## [Unreleased]

### Added

- **Agent guide** — the plugin now publishes a short guide for AI agents via the
  new `skills()` capability (NimbusCMS ADR 0013), served over MCP as
  `nimbus://guide/plugin/nimbuscms.markdown`. It tells an agent the `markdown`
  field's contract (send/receive raw Markdown source, whitespace is preserved,
  `max_length`), so enabling this plugin teaches agents how to drive its field.

### Changed

- Bumped the `nimbuscms/nimbus` dev dependency to pick up the `skills()`
  capability, and adapted a test to core's structured `FieldError` validation
  results.

## [0.1.0-alpha.1] — 2026-08-02

The first tagged release, coordinated with `nimbuscms/nimbus` 0.1.0-alpha.1.

### Added

- A `markdown` field type: an admin textarea that stores Markdown **source**,
  normalizes line endings only, enforces an optional maximum length, escapes
  stored markup, and returns source from `toApi()`.
- Registers through the public `PluginContext`, requiring no changes to Nimbus
  core.
- Degrades safely when disabled — the stored source is preserved, shown
  read-only, and saves are blocked until the plugin is re-enabled.

### Compatibility

- Requires `nimbuscms/nimbus` at runtime. During the pre-release period this is
  pinned to `dev-main`; it moves to `^0.1` once core is published to Packagist
  (tracked under Release & packaging in the core roadmap).
- Tested against PHP 8.2 and 8.3.

### Not included (by design)

Renders no HTML, ships no JavaScript editor, and adds no routes, migrations,
admin navigation or permissions. Those wait for capabilities core does not yet
expose. This plugin exists to exercise field-type registration and nothing more.

[Unreleased]: https://github.com/NimbusCMS/plugin-markdown/compare/v0.1.0-alpha.1...HEAD
[0.1.0-alpha.1]: https://github.com/NimbusCMS/plugin-markdown/releases/tag/v0.1.0-alpha.1
