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
 * exercises is the contract itself rather than its own complexity.
 */
final class MarkdownPlugin implements Plugin
{
    /** Matches extra.nimbus.id in composer.json. */
    public const ID = 'nimbuscms.markdown';

    public function register(PluginContext $context): void
    {
        $context->fieldTypes()->register(new MarkdownFieldType());
    }
}
