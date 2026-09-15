<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI-assisted product description generator.
 *
 * TRUST BOUNDARY: All output from the Gemini API is classified as
 * UNTRUSTED EXTERNAL CONTENT and must pass through the sanitization
 * boundary in sanitizeHtmlOutput() before being returned to callers.
 *
 * LLM integration pattern: Adapter/Overlay — adds AI capability to the
 * existing product editing flow without a dedicated orchestration layer.
 * Fallback: static template when API unavailable, times out, or returns
 * an unexpected structure.
 *
 * TOKEN BUDGET: max_tokens capped at 500 (≈ 150 Vietnamese words × 3 tokens/word
 * + HTML overhead). Prevents runaway billing on adversarial product names.
 */
class AiCopywriterService
{
    /**
     * Maximum output tokens to request from the API.
     * 150 Vietnamese words × ~3 tokens/word + HTML tag overhead.
     */
    private const MAX_OUTPUT_TOKENS = 500;

    /**
     * Allowed HTML tags in sanitized output.
     * Limited to basic formatting — no script, iframe, style, object, etc.
     */
    private const ALLOWED_HTML_TAGS = '<p><strong><ul><ol><li><em><br><h3><h4>';

    /**
     * Generate a product description using AI with a static fallback.
     *
     * @return string Sanitized HTML safe for persistence and rendering.
     */
    public function generateProductDescription(string $productName): string
    {
        try {
            $apiKey = config('services.gemini.api_key');

            if (! $apiKey) {
                return $this->getStaticFallback($productName);
            }

            $prompt = "Viết một đoạn mô tả sản phẩm hấp dẫn, chuyên nghiệp bằng tiếng Việt cho sản phẩm có tên: '{$productName}'. "
                    .'Yêu cầu: Viết theo phong cách bán hàng trên thương mại điện tử (có sử dụng emoji hợp lý), '
                    .'tôn lên điểm nổi bật của sản phẩm, tối đa 150 chữ, định dạng HTML cơ bản (dùng <p>, <strong>, <ul>, <li> nếu cần). '
                    .'Không đưa ra những lời khuyên y tế, pháp lý hay thông tin sai lệch.';

            $response = Http::timeout(3)->withHeaders([
                'Content-Type' => 'application/json',
                // P1-02: Key passed as header, not URL query param.
                // URL query params are logged by Laravel HTTP client and web server access logs.
                // The x-goog-api-key header is accepted by all Gemini API endpoints.
                'x-goog-api-key' => $apiKey,
            ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent', [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    // TRUST BOUNDARY CONTROL: cap output tokens to prevent runaway billing
                    // and oversized responses that could stress downstream systems.
                    'maxOutputTokens' => self::MAX_OUTPUT_TOKENS,
                ],
            ]);

            if ($response->successful() && isset($response->json()['candidates'][0]['content']['parts'][0]['text'])) {
                $rawContent = $response->json()['candidates'][0]['content']['parts'][0]['text'];

                // Clean up any markdown code block wrappers if they exist
                $rawContent = preg_replace('/```html\n?/', '', $rawContent);
                $rawContent = preg_replace('/```\n?/', '', $rawContent);

                // TRUST BOUNDARY ENFORCEMENT: sanitize LLM output through an
                // allowlist of safe HTML tags. This prevents XSS injection via
                // AI-generated content (script, iframe, onerror handlers, etc.).
                return $this->sanitizeHtmlOutput(trim($rawContent));
            }

            Log::warning('AI Copywriter API failed or returned unexpected structure.', [
                'response_status' => $response->status(),
                'product_name' => $productName,
            ]);

            return $this->getStaticFallback($productName);

        } catch (\Exception $e) {
            Log::warning('AI Copywriter timeout or exception: '.$e->getMessage(), [
                'product_name' => $productName,
            ]);

            return $this->getStaticFallback($productName);
        }
    }

    /**
     * Sanitize AI-generated HTML through an allowlist of safe tags.
     *
     * TRUST BOUNDARY: LLM output is classified as untrusted external content.
     * Only whitelisted structural tags are permitted; all other tags are stripped.
     *
     * IMPORTANT: strip_tags() in PHP strips disallowed TAGS but does NOT strip
     * attributes from allowed tags. A second regex pass is required to strip all
     * attributes (onclick, onerror, href, style, data-*, etc.) from allowed tags.
     * Without this pass, <p onclick="xss()"> survives strip_tags() intact.
     *
     * @param  string  $html  Raw HTML from LLM API
     * @return string Sanitized HTML safe for persistence and rendering
     */
    public function sanitizeHtmlOutput(string $html): string
    {
        // Step 1: Strip disallowed tags (but NOT their attributes).
        $stripped = strip_tags($html, self::ALLOWED_HTML_TAGS);

        // Step 2: Strip ALL attributes from the surviving allowed tags.
        // This removes onclick, onerror, style, href, data-*, and any other
        // attribute that could be used for XSS or content injection.
        // Pattern: match any <tag ...attributes...> and keep only <tag>.
        $clean = preg_replace('/<(\w+)(\s[^>]*)?>/', '<$1>', $stripped);

        return $clean ?? $stripped;
    }

    /**
     * Fallback template when AI is unavailable or times out.
     */
    private function getStaticFallback(string $productName): string
    {
        // Escape the product name in the fallback template — it comes from user input.
        $escapedName = htmlspecialchars($productName, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<p>🌟 <strong>{$escapedName}</strong> - Sự lựa chọn hoàn hảo dành cho bạn!</p>
<ul>
    <li>✨ Thiết kế tinh tế, chất lượng vượt trội.</li>
    <li>🔥 Sản phẩm đang được rất nhiều khách hàng yêu thích và tin dùng.</li>
    <li>💯 Đảm bảo hài lòng 100% khi nhận hàng.</li>
</ul>
<p>👉 <em>Nhanh tay đặt hàng ngay hôm nay để nhận được ưu đãi tốt nhất từ Shop nhé!</em></p>
HTML;
    }
}
