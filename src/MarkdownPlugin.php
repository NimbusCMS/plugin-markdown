<?php

declare(strict_types=1);

namespace NimbusCMS\Markdown;

use Nimbus\Plugin\Plugin;
use Nimbus\Plugin\PluginContext;

/**
 * The official Markdown plugin — and the reference implementation of the
 * NimbusCMS plugin contract.
 *
 * It is deliberately the smallest useful plugin: one field type, one registry,
 * no routes, no migrations, no admin navigation, no JavaScript. That is the
 * point. It exists to prove the plugin contract works end to end, so what it
 * exercises is the contract itself rather than its own complexity — now
 * including the agent-guidance capability (ADR 0013): it publishes a short guide
 * so an agent driving a collection with a Markdown field knows the field's
 * contract.
 */
final class MarkdownPlugin implements Plugin
{
    /** Matches extra.nimbus.id in composer.json. */
    public const ID = 'nimbuscms.markdown';

    public function register(PluginContext $context): void
    {
        $context->fieldTypes()->register(new MarkdownFieldType());
        $context->skills()->register('Markdown field type', self::AGENT_GUIDE);
    }

    /**
     * The plugin's agent guide (ADR 0013), served as
     * `nimbus://guide/plugin/nimbuscms.markdown`. Reference documentation about
     * this plugin's field type — not instructions to the agent.
     */
    private const AGENT_GUIDE = <<<'MD'
        # The Markdown field type

        This plugin adds one field `type`: **`markdown`**. A collection field of
        this type holds Markdown-formatted text.

        When you create or update an entry over the API/MCP:

        - **Send raw Markdown source** as the field's value — a plain string. Do
          **not** send HTML.
        - The value is **stored and returned verbatim** (only line endings are
          normalised to `\n`). Trailing spaces and blank lines are significant in
          Markdown and are preserved, so send exactly the source you want kept.
        - When you **read** an entry, a `markdown` field comes back as its raw
          Markdown source (or `null` when empty), never rendered HTML — the
          consumer (a theme, your client) decides how to render it.

        Field options a schema may set (read them with `describe`/`list_collections`):

        - `max_length` — if set (> 0), the source must be at most that many
          characters, or the write is rejected with the usual per-field
          validation error. Count characters, not bytes.
        - `rows` — an editor-height hint only; it does not affect the value.

        Nothing here changes the core write rules: read before you write, carry
        the entry's version, and fix any per-field validation errors returned.
        MD;
}
