<?php

namespace Tests\Unit\Services;

use App\Services\AiCopywriterService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

/**
 * Unit tests for AiCopywriterService.
 *
 * Trust boundary: LLM output is untrusted external content.
 * Tests verify: fallback paths, max_tokens enforcement, XSS sanitization.
 */
beforeEach(function () {
    $this->service = new AiCopywriterService;
});

// ── Fallback path tests ──────────────────────────────────────────────────────

it('returns static fallback when api key is not configured', function () {
    Config::set('services.gemini.api_key', null);

    $result = $this->service->generateProductDescription('Test Product');

    expect($result)->toContain('Test Product')
        ->and($result)->toContain('<p>')
        ->and($result)->toContain('<ul>');
});

it('returns static fallback on http timeout exception', function () {
    Config::set('services.gemini.api_key', 'fake-key');

    Http::fake(fn () => throw new ConnectionException('timeout'));

    $result = $this->service->generateProductDescription('Test Product');

    expect($result)->toContain('Test Product');
});

it('returns static fallback on non-200 api response', function () {
    Config::set('services.gemini.api_key', 'fake-key');

    Http::fake([
        '*' => Http::response(['error' => ['message' => 'Quota exceeded']], 429),
    ]);

    $result = $this->service->generateProductDescription('Sản phẩm A');

    expect($result)->toContain('Sản phẩm A');
});

it('returns static fallback when candidates array is empty', function () {
    Config::set('services.gemini.api_key', 'fake-key');

    Http::fake([
        '*' => Http::response(['candidates' => []], 200),
    ]);

    $result = $this->service->generateProductDescription('Sản phẩm B');

    expect($result)->toContain('Sản phẩm B');
});

it('returns static fallback when response structure is malformed', function () {
    Config::set('services.gemini.api_key', 'fake-key');

    Http::fake([
        '*' => Http::response(['unexpected' => 'structure'], 200),
    ]);

    $result = $this->service->generateProductDescription('Sản phẩm C');

    expect($result)->toContain('Sản phẩm C');
});

// ── Successful path tests ────────────────────────────────────────────────────

it('returns sanitized content from api on success', function () {
    Config::set('services.gemini.api_key', 'fake-key');

    $apiHtml = '<p>Sản phẩm <strong>tốt</strong>. <em>Mua ngay!</em></p>';

    Http::fake([
        '*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => $apiHtml]]],
            ]],
        ], 200),
    ]);

    $result = $this->service->generateProductDescription('Sản phẩm Test');

    expect($result)->toContain('<p>')
        ->and($result)->toContain('<strong>')
        ->and($result)->toContain('<em>');
});

it('strips markdown code block wrappers from api response', function () {
    Config::set('services.gemini.api_key', 'fake-key');

    $rawWithMarkdown = "```html\n<p>Good content</p>\n```";

    Http::fake([
        '*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => $rawWithMarkdown]]],
            ]],
        ], 200),
    ]);

    $result = $this->service->generateProductDescription('Product');

    expect($result)->toContain('<p>Good content</p>')
        ->and($result)->not->toContain('```');
});

// ── TRUST BOUNDARY: XSS sanitization tests ──────────────────────────────────

it('strips script tags from llm output to prevent xss', function () {
    $malicious = '<p>Good content</p><script>alert("xss")</script>';

    $result = $this->service->sanitizeHtmlOutput($malicious);

    expect($result)->toContain('<p>Good content</p>')
        ->and($result)->not->toContain('<script>');
});

it('strips onclick and event handler attributes from llm output', function () {
    $malicious = '<p onclick="alert(1)">Click me</p>';

    $result = $this->service->sanitizeHtmlOutput($malicious);

    // strip_tags alone does NOT remove attributes from allowed tags.
    // sanitizeHtmlOutput() adds a second regex pass that strips all attributes,
    // so <p onclick="alert(1)"> becomes <p>.
    expect($result)->not->toContain('onclick');
});

it('strips iframe tags from llm output', function () {
    $malicious = '<p>Product</p><iframe src="https://evil.example.com"></iframe>';

    $result = $this->service->sanitizeHtmlOutput($malicious);

    expect($result)->not->toContain('<iframe>')
        ->and($result)->toContain('<p>Product</p>');
});

it('strips style tags from llm output', function () {
    $malicious = '<p>Content</p><style>body{display:none}</style>';

    $result = $this->service->sanitizeHtmlOutput($malicious);

    expect($result)->not->toContain('<style>')
        ->and($result)->toContain('<p>Content</p>');
});

it('allows safe structural tags through sanitization', function () {
    $safe = '<p><strong>Title</strong></p><ul><li><em>Point 1</em></li></ul>';

    $result = $this->service->sanitizeHtmlOutput($safe);

    expect($result)->toContain('<p>')
        ->and($result)->toContain('<strong>')
        ->and($result)->toContain('<ul>')
        ->and($result)->toContain('<li>')
        ->and($result)->toContain('<em>');
});

// ── max_tokens enforcement ───────────────────────────────────────────────────

it('sends maxOutputTokens in api request', function () {
    Config::set('services.gemini.api_key', 'fake-key');

    $requestBody = null;

    Http::fake(function ($request) use (&$requestBody) {
        $requestBody = $request->data();

        return Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => '<p>ok</p>']]],
            ]],
        ], 200);
    });

    $this->service->generateProductDescription('Product');

    expect($requestBody)->toHaveKey('generationConfig.maxOutputTokens')
        ->and($requestBody['generationConfig']['maxOutputTokens'])->toBe(500);
});

// ── Fallback template safety ─────────────────────────────────────────────────

it('escapes html special chars in product name within fallback', function () {
    Config::set('services.gemini.api_key', null);

    $result = $this->service->generateProductDescription('<script>alert(1)</script>');

    expect($result)->not->toContain('<script>')
        ->and($result)->toContain('&lt;script&gt;');
});
