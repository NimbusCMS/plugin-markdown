<?php

declare(strict_types=1);

namespace NimbusCMS\Markdown\Tests;

use Nimbus\Content\Field;
use Nimbus\Content\FieldTypeRegistry;
use Nimbus\Content\UnknownFieldType;
use Nimbus\Plugin\PluginDiagnostic;
use Nimbus\Plugin\PluginLoader;
use NimbusCMS\Markdown\MarkdownPlugin;
use PHPUnit\Framework\TestCase;

/**
 * Proves the *package boundary*, not the field implementation.
 *
 * MarkdownFieldTypeTest checks that the field behaves correctly when you hand
 * it a value. This checks something different and easy to get wrong: that a
 * real Composer installation of this package is discovered by Nimbus's own
 * loader, using this package's real manifest, and registers without anyone
 * editing core.
 *
 * Everything here is the genuine article — the installed `composer.json`, the
 * real `PluginLoader`, the real `FieldTypeRegistry`. Only the path to
 * `installed.json` is synthesised, because Composer writes that file about the
 * *root* project and this package is the root when its own tests run.
 */
final class PackageIntegrationTest extends TestCase
{
    private string $installedJson;

    protected function setUp(): void
    {
        $this->installedJson = tempnam(sys_get_temp_dir(), 'nb-installed-') ?: '';
    }

    protected function tearDown(): void
    {
        @unlink($this->installedJson);
    }

    /** @return array<string,mixed> this package's actual composer manifest */
    private function manifest(): array
    {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../composer.json'), true);
        self::assertIsArray($manifest);

        return $manifest;
    }

    /**
     * An installed.json describing this package exactly as Composer would,
     * straight from the real manifest.
     */
    private function installedAs(): string
    {
        $manifest = $this->manifest();
        file_put_contents($this->installedJson, json_encode([
            'packages' => [[
                'name'  => $manifest['name'],
                'type'  => $manifest['type'],
                'extra' => $manifest['extra'],
            ]],
        ], JSON_THROW_ON_ERROR));

        return $this->installedJson;
    }

    // ------------------------------------------------------- the manifest

    public function test_the_package_declares_nimbus_as_a_runtime_dependency(): void
    {
        $manifest = $this->manifest();

        // The plugin's production classes implement Nimbus interfaces, so core
        // is a runtime requirement. In require-dev, Composer would happily
        // install this package without a compatible Nimbus present.
        self::assertArrayHasKey('nimbuscms/nimbus', $manifest['require']);
        self::assertArrayNotHasKey('nimbuscms/nimbus', $manifest['require-dev'] ?? []);
    }

    public function test_the_package_is_typed_as_a_nimbus_plugin(): void
    {
        self::assertSame('nimbuscms-plugin', $this->manifest()['type']);
    }

    // -------------------------------------------------- discovery to registry

    public function test_composer_discovery_registers_the_field_type(): void
    {
        $registry    = new FieldTypeRegistry();
        $loader      = new PluginLoader($this->installedAs());
        $diagnostics = $loader->load($registry);

        self::assertSame([], $diagnostics, 'a correctly installed package must load cleanly');
        self::assertSame(
            [MarkdownPlugin::ID => $this->manifest()['name']],
            $loader->registered(),
        );

        // Registered into the *shared* registry, under this plugin's id.
        self::assertTrue($registry->has('markdown'));
        self::assertSame(MarkdownPlugin::ID, $registry->providerOf('markdown'));
        self::assertArrayHasKey('markdown', $registry->choices());
    }

    public function test_core_field_types_are_untouched_by_installation(): void
    {
        $registry = new FieldTypeRegistry();
        (new PluginLoader($this->installedAs()))->load($registry);

        foreach (['text', 'textarea', 'number', 'boolean', 'relation'] as $core) {
            self::assertSame('core', $registry->providerOf($core));
        }
    }

    // ---------------------------------------------------------- disabling

    public function test_disabling_the_package_leaves_the_type_unregistered(): void
    {
        $registry    = new FieldTypeRegistry();
        $loader      = new PluginLoader($this->installedAs(), [MarkdownPlugin::ID => false]);
        $diagnostics = $loader->load($registry);

        self::assertSame([], $loader->registered());
        self::assertFalse($registry->has('markdown'));
        self::assertCount(1, $diagnostics);
        self::assertSame(PluginDiagnostic::DISABLED, $diagnostics[0]->reason);
        self::assertFalse($diagnostics[0]->isFailure(), 'disabled is a choice, not a fault');
    }

    public function test_with_the_package_disabled_writes_are_blocked_and_content_is_kept(): void
    {
        $registry = new FieldTypeRegistry();
        (new PluginLoader($this->installedAs(), [MarkdownPlugin::ID => false]))->load($registry);

        $field  = new Field('body', 'Body', 'markdown');
        $stored = "# Still here\n\nWith **bold** text.";

        // Write paths refuse the type outright...
        try {
            $registry->get('markdown');
            self::fail('write paths must not resolve an unavailable type');
        } catch (UnknownFieldType $e) {
            self::assertSame('markdown', $e->type);
        }

        // ...while the admin degrades: the source is shown, never rewritten,
        // and saving is refused until the package is back.
        $fallback = $registry->forDisplay('markdown');
        self::assertSame($stored, $fallback->normalize($stored), 'stored source must survive byte for byte');
        self::assertNotNull($fallback->validate($field, $stored), 'saving must be blocked');
        self::assertStringContainsString('Still here', $fallback->renderInput($field, $stored));
        self::assertStringContainsString('markdown', $fallback->renderInput($field, $stored));
    }

    public function test_re_enabling_restores_the_field_type(): void
    {
        $path = $this->installedAs();

        $disabled = new FieldTypeRegistry();
        (new PluginLoader($path, [MarkdownPlugin::ID => false]))->load($disabled);
        self::assertFalse($disabled->has('markdown'));

        $enabled = new FieldTypeRegistry();
        (new PluginLoader($path, [MarkdownPlugin::ID => true]))->load($enabled);

        self::assertTrue($enabled->has('markdown'), 'flipping the switch back is all it takes');
        self::assertSame('markdown', $enabled->get('markdown')->type());
    }

    // ----------------------------------------------------------- conflicts

    public function test_a_second_package_cannot_take_this_plugins_id(): void
    {
        $manifest = $this->manifest();
        file_put_contents($this->installedJson, json_encode(['packages' => [
            ['name' => $manifest['name'], 'type' => $manifest['type'], 'extra' => $manifest['extra']],
            ['name' => 'squatter/markdown', 'type' => 'nimbuscms-plugin', 'extra' => $manifest['extra']],
        ]], JSON_THROW_ON_ERROR));

        $registry    = new FieldTypeRegistry();
        $loader      = new PluginLoader($this->installedJson);
        $diagnostics = $loader->load($registry);

        self::assertSame([MarkdownPlugin::ID => $manifest['name']], $loader->registered());
        self::assertCount(1, $diagnostics);
        self::assertSame(PluginDiagnostic::DUPLICATE_ID, $diagnostics[0]->reason);
        self::assertSame('squatter/markdown', $diagnostics[0]->package);
    }
}
