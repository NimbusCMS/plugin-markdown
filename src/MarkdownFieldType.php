<?php

declare(strict_types=1);

namespace NimbusCMS\Markdown;

use Nimbus\Content\Field;
use Nimbus\Content\FieldTypes\BaseType;

/**
 * A Markdown field.
 *
 * Stores the author's Markdown source, exactly as typed, and nothing else.
 *
 * It does not render HTML. That is a deliberate first-iteration decision, not
 * an omission: rendering means either a Markdown dependency (which needs
 * auditing, because rendering untrusted input to HTML is an XSS surface) or a
 * hand-rolled parser (which is worse). Storing source is also the reversible
 * choice — a later version can render at read time without migrating anything,
 * whereas storing generated HTML would be a one-way door.
 *
 * `toApi()` therefore returns the source, and consumers render it. That suits a
 * headless CMS: the client already knows whether it wants HTML, an AST, or
 * plain text.
 */
final class MarkdownFieldType extends BaseType
{
    public function type(): string
    {
        return 'markdown';
    }

    public function label(): string
    {
        return 'Markdown';
    }

    public function renderInput(Field $field, mixed $value): string
    {
        return sprintf(
            '<textarea id="%s" name="%s" rows="%d" class="nb-markdown"%s%s>%s</textarea>',
            $this->inputId($field),
            $this->inputName($field),
            max(4, (int) $field->option('rows', 12)),
            $this->placeholder($field),
            $this->required($field),
            // Escaped: Markdown source can contain raw HTML, and the admin is
            // not the place to find out.
            $this->e($this->stringify($value)),
        );
    }

    public function renderCell(Field $field, mixed $value): string
    {
        $text = trim(preg_replace('/[#*_`>\[\]!\-]+/', ' ', $this->stringify($value)) ?? '');
        $text = (string) preg_replace('/\s+/', ' ', $text);

        return $this->e(mb_strimwidth($text, 0, 80, '…'));
    }

    /**
     * Line endings only. Trimming, collapsing blank lines or "tidying" the
     * source would silently rewrite what the author typed — and in Markdown,
     * trailing spaces and blank lines are significant.
     */
    public function normalize(mixed $input): mixed
    {
        if ($input === null || !is_scalar($input)) {
            return null;
        }
        $text = str_replace(["\r\n", "\r"], "\n", (string) $input);

        return $text === '' ? null : $text;
    }

    public function validate(Field $field, mixed $value): ?string
    {
        $max = (int) $field->option('max_length', 0);
        if ($max > 0 && mb_strlen($this->stringify($value)) > $max) {
            return sprintf('%s must be %d characters or fewer.', $field->label, $max);
        }
        return null;
    }

    /** The API contract is the Markdown source; consumers decide how to render it. */
    public function toApi(Field $field, mixed $value): mixed
    {
        return $this->stringify($value) === '' ? null : $this->stringify($value);
    }

    private function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
