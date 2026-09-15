<?php

namespace App\Services;

use Illuminate\Support\Str;

class TocService
{
    /**
     * Extract Table of Contents and inject anchor IDs into HTML headings (h2 and h3).
     *
     * @return array{toc: array<int, array{id: string, title: string, level: int}>, html: string}
     */
    public function generate(?string $html): array
    {
        if (empty($html) || ! is_string($html)) {
            return [
                'toc' => [],
                'html' => $html ?? '',
            ];
        }

        $toc = [];
        $usedSlugs = [];

        // Match <h2> and <h3> tags along with optional attributes and inner content
        $pattern = '/<(h[23])(\b[^>]*)>(.*?)<\/\1>/si';

        $modifiedHtml = preg_replace_callback($pattern, function ($matches) use (&$toc, &$usedSlugs) {
            $tag = strtolower($matches[1]);
            $attrs = $matches[2];
            $innerHtml = $matches[3];

            $level = (int) substr($tag, 1);
            $title = trim(strip_tags($innerHtml));

            if ($title === '') {
                return $matches[0];
            }

            $baseSlug = Str::slug($title);
            if ($baseSlug === '') {
                $baseSlug = 'heading';
            }

            // Ensure unique anchor ID within document
            $slug = $baseSlug;
            $counter = 1;
            while (isset($usedSlugs[$slug])) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }
            $usedSlugs[$slug] = true;

            $toc[] = [
                'id' => $slug,
                'title' => $title,
                'level' => $level,
            ];

            // If an id attribute already exists, replace it with the clean slug; otherwise prepend it
            if (preg_match('/\bid=["\'][^"\']*["\']/i', $attrs)) {
                $attrs = preg_replace('/\bid=["\'][^"\']*["\']/i', 'id="'.$slug.'"', $attrs);
            } else {
                $attrs = ' id="'.$slug.'"'.$attrs;
            }

            return "<{$tag}{$attrs}>{$innerHtml}</{$tag}>";
        }, $html);

        return [
            'toc' => $toc,
            'html' => $modifiedHtml ?? $html,
        ];
    }
}
