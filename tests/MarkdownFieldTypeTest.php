<?php

declare(strict_types=1);

namespace NimbusCMS\Markdown\Tests;

use Nimbus\Content\Collection;
use Nimbus\Content\Field;
use Nimbus\Content\FieldTypeRegistry;
use Nimbus\Content\UnknownFieldType;
use Nimbus\Content\Validator;
use Nimbus\Plugin\PluginContext;
use NimbusCMS\Markdown\MarkdownFieldType;
use NimbusCMS\Markdown\MarkdownPlugin;
use PHPUnit\Framework\TestCase;

final class MarkdownFieldTypeTest extends TestCase
{
    private MarkdownFieldType $type;

    protected function setUp(): void
    {
        $this->type = new MarkdownFieldType();
    }

    /** @param array<string,mixed> $options */
    private function field(array $options = [], bool $required = false): Field
    {
        return new Field('body', 'Body', 'markdown', $required, $options);
    }

    // ------------------------------------------------------- registration

    public function test_the_plugin_registers_its_field_type(): void
    {
        $registry = new FieldTypeRegistry();
        (new MarkdownPlugin())->register(new PluginContext($registry, MarkdownPlugin::ID));

        self::assertTrue($registry->has('markdown'));
        self::assertSame('markdown', $registry->get('markdown')->type());
        self::assertSame(MarkdownPlugin::ID, $registry->providerOf('markdown'));
    }

    public function test_the_type_appears_in_the_field_picker(): void
    {
        $registry = new FieldTypeRegistry();
        (new MarkdownPlugin())->register(new PluginContext($registry, MarkdownPlugin::ID));

        self::assertArrayHasKey('markdown', $registry->choices());
        self::assertSame('Markdown', $registry->choices()['markdown']);
    }

    public function test_the_plugin_id_matches_the_composer_manifest(): void
    {
        $manifest = json_decode((string) file_get_contents(__DIR__ . '/../composer.json'), true);

        self::assertSame(MarkdownPlugin::ID, $manifest['extra']['nimbus']['id']);
        self::assertSame(MarkdownPlugin::class, $manifest['extra']['nimbus']['plugin']);
        self::assertSame('nimbuscms-plugin', $manifest['type']);
    }

    public function test_registering_twice_is_rejected_by_core(): void
    {
        $registry = new FieldTypeRegistry();
        (new MarkdownPlugin())->register(new PluginContext($registry, MarkdownPlugin::ID));

        $this->expectException(\Nimbus\Content\DuplicateFieldType::class);
        (new MarkdownPlugin())->register(new PluginContext($registry, MarkdownPlugin::ID));
    }

    // -------------------------------------------------------- normalization

    public function test_source_is_preserved_exactly(): void
    {
        $source = "# Title\n\nSome *emphasis* and a [link](https://example.com).\n\n    indented code\n";

        self::assertSame($source, $this->type->normalize($source));
    }

    public function test_line_endings_are_normalized_and_nothing_else(): void
    {
        self::assertSame("a\nb\nc", $this->type->normalize("a\r\nb\rc"));
        // Trailing spaces are a hard line break in Markdown — they must survive.
        self::assertSame("line  \nnext", $this->type->normalize("line  \r\nnext"));
        // Blank lines separate paragraphs; collapsing them would change meaning.
        self::assertSame("one\n\n\ntwo", $this->type->normalize("one\n\n\ntwo"));
    }

    public function test_blank_becomes_null(): void
    {
        self::assertNull($this->type->normalize(''));
        self::assertNull($this->type->normalize(null));
        self::assertNull($this->type->normalize([]));
    }

    public function test_whitespace_only_input_is_kept_not_trimmed(): void
    {
        // Deliberate: trimming is a rewrite, and the field is not required.
        self::assertSame("  \n  ", $this->type->normalize("  \r\n  "));
    }

    // ----------------------------------------------------------- validation

    public function test_valid_by_default(): void
    {
        self::assertNull($this->type->validate($this->field(), str_repeat('x', 100_000)));
    }

    public function test_max_length_is_enforced_when_configured(): void
    {
        $field = $this->field(['max_length' => 10]);

        self::assertNull($this->type->validate($field, '0123456789'));
        self::assertNotNull($this->type->validate($field, '0123456789x'));
        self::assertStringContainsString('10 characters or fewer', (string) $this->type->validate($field, '0123456789x'));
    }

    public function test_max_length_counts_characters_not_bytes(): void
    {
        $field = $this->field(['max_length' => 3]);

        self::assertNull($this->type->validate($field, 'né√'), 'three characters, more than three bytes');
    }

    public function test_required_empty_is_handled_by_core_not_here(): void
    {
        $registry = new FieldTypeRegistry();
        (new MarkdownPlugin())->register(new PluginContext($registry, MarkdownPlugin::ID));

        $collection = new Collection(1, 'posts', 'Posts', '#', '', [$this->field(required: true)], ['kind' => 'collection']);
        $errors     = (new Validator($registry))->validate($collection, ['body' => $this->type->normalize('')]);

        self::assertArrayHasKey('body', $errors);
        self::assertStringContainsString('required', $errors['body']);
    }

    public function test_a_valid_required_value_passes_through_core_validation(): void
    {
        $registry = new FieldTypeRegistry();
        (new MarkdownPlugin())->register(new PluginContext($registry, MarkdownPlugin::ID));

        $collection = new Collection(1, 'posts', 'Posts', '#', '', [$this->field(required: true)], ['kind' => 'collection']);
        $errors     = (new Validator($registry))->validate($collection, ['body' => $this->type->normalize('# Hello')]);

        self::assertSame([], $errors);
    }

    // -------------------------------------------------------------- render

    public function test_input_renders_a_textarea_with_the_core_field_name(): void
    {
        $html = $this->type->renderInput($this->field(), '# Hello');

        self::assertStringContainsString('<textarea', $html);
        self::assertStringContainsString('name="f[body]"', $html);
        self::assertStringContainsString('# Hello', $html);
    }

    public function test_input_escapes_stored_markup(): void
    {
        $html = $this->type->renderInput($this->field(), '</textarea><script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('</textarea><', $html);
    }

    public function test_required_and_rows_options_reach_the_markup(): void
    {
        $html = $this->type->renderInput($this->field(['rows' => 30]), '');

        self::assertStringContainsString('rows="30"', $html);
        self::assertStringContainsString('rows="12"', $this->type->renderInput($this->field(), ''));
        self::assertStringContainsString(' required', $this->type->renderInput($this->field(required: true), ''));
    }

    public function test_cell_shows_readable_text_not_syntax(): void
    {
        $cell = $this->type->renderCell($this->field(), "# A Heading\n\nWith **bold** text.");

        self::assertStringNotContainsString('#', $cell);
        self::assertStringNotContainsString('**', $cell);
        self::assertStringContainsString('A Heading', $cell);
    }

    public function test_cell_escapes_html(): void
    {
        self::assertStringNotContainsString('<script>', $this->type->renderCell($this->field(), '<script>alert(1)</script>'));
    }

    // ----------------------------------------------------------------- api

    public function test_api_returns_the_source(): void
    {
        $source = "# Title\n\nBody.";

        self::assertSame($source, $this->type->toApi($this->field(), $source));
        self::assertNull($this->type->toApi($this->field(), ''));
        self::assertNull($this->type->toApi($this->field(), null));
    }

    // ------------------------------------------------------ missing provider

    public function test_without_the_plugin_core_refuses_the_type_strictly(): void
    {
        // What core does when this plugin is uninstalled or disabled: the type
        // is unknown to write paths...
        $registry = new FieldTypeRegistry();

        $this->expectException(UnknownFieldType::class);
        $registry->get('markdown');
    }

    public function test_without_the_plugin_admin_display_degrades_safely(): void
    {
        $registry = new FieldTypeRegistry();
        $stored   = '# Still here';

        // ...while display keeps the content visible and read-only.
        $fallback = $registry->forDisplay('markdown');

        self::assertSame($stored, $fallback->normalize($stored), 'stored source must not be rewritten');
        self::assertNotNull($fallback->validate($this->field(), $stored), 'saving must be blocked');
        self::assertStringContainsString($stored, $fallback->renderInput($this->field(), $stored));
        self::assertStringContainsString('markdown', $fallback->renderInput($this->field(), $stored));
    }
}
