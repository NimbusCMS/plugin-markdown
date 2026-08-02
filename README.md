# Markdown for NimbusCMS

A Markdown field type for [NimbusCMS](https://github.com/NimbusCMS/nimbus) — and
the official **reference plugin** for the Nimbus plugin contract.

It is deliberately the smallest useful plugin: one field type, one registry, no
routes, no migrations, no admin navigation, no JavaScript. That is the point.
It exists to prove the plugin contract works end to end, so what it exercises is
the contract rather than its own complexity.

## Install

```bash
composer require nimbuscms/markdown
```

> **Not on Packagist yet.** Neither this plugin nor core is published, so that
> command does not work outside the project today. Until core tags `0.1.0` and
> both are published, this package requires core through a VCS repository at
> `dev-main` — see `composer.json`. That means every build here tracks core's
> `main` and cannot pin a version, so a breaking change to core breaks this
> plugin's CI the moment it merges. The path out is tracked under
> [Release & packaging](https://github.com/NimbusCMS/nimbus/blob/main/ROADMAP.md)
> in the core roadmap.

That is the whole installation. Nimbus discovers plugins through Composer's
`installed.json` — there is no upload step and no in-admin installer, because
downloading and executing arbitrary code from the web is a feature that needs
signing, compatibility and rollback policies designed first.

"Markdown" then appears in the field-type picker when you add a field to a
collection.

## Disable without uninstalling

```php
// config/plugins.php
return [
    'nimbuscms.markdown' => false,
];
```

Existing content is safe. Entries with a Markdown field stay in the database
untouched; the admin shows the stored source read-only, names the missing
provider, and refuses saves until the plugin is back. Re-enable it and editing
resumes exactly where it left off.

## What it stores

The author's Markdown source, exactly as typed. Only line endings are
normalised (CRLF → LF).

Nothing is trimmed or tidied, because in Markdown that changes meaning:
trailing double-spaces are a hard line break, and blank lines separate
paragraphs.

### It does not render HTML

Deliberately, in this first iteration. Rendering means either taking on a
Markdown dependency — which needs auditing, since rendering untrusted input to
HTML is an XSS surface — or hand-rolling a parser, which is worse.

Storing source is also the reversible choice. A later version can render at
read time without migrating anything, whereas storing generated HTML would be a
one-way door.

`toApi()` returns the source, and consumers render it. For a headless CMS that
is the right default: the client already knows whether it wants HTML, an AST or
plain text.

## Field options

| Option | Type | Default | Meaning |
|--------|------|---------|---------|
| `rows` | int | `12` | Height of the editing textarea (minimum 4) |
| `max_length` | int | unset | Maximum characters; unset means no limit |
| `placeholder` | string | unset | Placeholder text |
| `help` | string | unset | Help text under the field |

`required` is handled by Nimbus core, not here.

## Development

```bash
composer install
composer check     # PHPStan level 6 + PHPUnit
```

CI runs against PHP 8.2 and 8.3 — the oldest version the manifest allows, and
the current one.

## How it works

```php
final class MarkdownPlugin implements Plugin
{
    public const ID = 'nimbuscms.markdown';

    public function register(PluginContext $context): void
    {
        $context->fieldTypes()->register(new MarkdownFieldType(), self::ID);
    }
}
```

One method. `PluginContext` exposes exactly one capability today — the
application's field-type registry — and that is all this plugin needs. See
[ADR 0001](https://github.com/NimbusCMS/nimbus/blob/main/docs/adr/0001-plugin-contract.md)
for why the surface is that small.

Field-type keys are unique and first registration wins: if another provider
already owns `markdown`, this plugin fails loudly at boot rather than silently
replacing it.

## License

[MIT](LICENSE)

## Changelog

See [CHANGELOG.md](CHANGELOG.md). Compatibility with core is documented in the core [COMPATIBILITY](https://github.com/NimbusCMS/nimbus/blob/main/docs/COMPATIBILITY.md) policy.
