<?php

use App\Models\Post;

describe('Post::calculateReadingTime Adversarial & Empirical Stress Tests', function () {

    // =========================================================================
    // Category 1: Boundary and Null/Empty/Whitespace Inputs
    // =========================================================================
    test('Category 1: Boundary inputs return minimum 1 minute', function () {
        // Null and empty strings
        expect(Post::calculateReadingTime(null))->toBe(1);
        expect(Post::calculateReadingTime(''))->toBe(1);

        // Standard whitespace sequences
        expect(Post::calculateReadingTime('   '))->toBe(1);
        expect(Post::calculateReadingTime("\t\r\n   \n\r\t"))->toBe(1);

        // Zero-width and control characters (U+200B, U+200C, U+200D, U+FEFF, Null bytes)
        $zeroWidthOnly = "\u{200B}\u{200C}\u{200D}\u{FEFF}\x00\x01\x1F";
        expect(Post::calculateReadingTime($zeroWidthOnly))->toBe(1);

        // Whitespace surrounding empty or self-closing tags
        $emptyTags = '   <p></p>  <div> </div> <br> <hr/> <span></span>   ';
        expect(Post::calculateReadingTime($emptyTags))->toBe(1);

        // Only HTML comments
        $onlyComments = "<!-- comment 1 --><!-- comment 2 --><!-- multi\nline\ncomment -->";
        expect(Post::calculateReadingTime($onlyComments))->toBe(1);

        // Only script and style blocks
        $onlyCode = '<script>var a = 1; var b = 2; function test() { return 42; }</script><style>body { color: red; margin: 0; }</style>';
        expect(Post::calculateReadingTime($onlyCode))->toBe(1);

        // Only HTML entities of whitespace
        $onlyEntities = '&nbsp;&nbsp;&nbsp;&#160;&#xA0;&ensp;&emsp;&thinsp;';
        expect(Post::calculateReadingTime($onlyEntities))->toBe(1);
    });

    // =========================================================================
    // Category 2: Tag-Boundary Tokenization (No Inter-tag Spaces)
    // =========================================================================
    test('Category 2: Preserves word boundaries for adjacent HTML tags without inter-tag whitespace', function () {
        // Adjacent block elements
        $adjacentBlocks = '<p>Hello</p><p>World</p>';
        expect(Post::calculateReadingTime($adjacentBlocks))->toBe(1);

        // Adjacent inline elements
        $adjacentInline = '<b>Alpha</b><i>Beta</i><u>Gamma</u>';
        expect(Post::calculateReadingTime($adjacentInline))->toBe(1);

        // Deeply nested mixed tags
        $nestedTags = '<div><section><h1>Header</h1><p>Body <span>text</span></p></section></div>';
        expect(Post::calculateReadingTime($nestedTags))->toBe(1);

        // Minified table structure
        $minifiedTable = '<table><tr><th>Col1</th><th>Col2</th></tr><tr><td>Val1</td><td>Val2</td></tr></table>';
        expect(Post::calculateReadingTime($minifiedTable))->toBe(1);

        // Self-closing tags between words
        $selfClosing = 'First<br/>Second<hr/>Third<img src="x.jpg"/>Fourth';
        expect(Post::calculateReadingTime($selfClosing))->toBe(1);

        // 450 words in minified <p> tags without inter-tag spaces -> 450 / 200 = 2.25 -> 3 minutes
        $minified450 = implode('', array_fill(0, 450, '<p>furniture</p>'));
        expect(Post::calculateReadingTime($minified450))->toBe(3);

        // 2,250 words in minified <div><p>...</p></div> -> 2,250 / 200 = 11.25 -> 12 minutes
        $minified2250 = '<div>'.implode('</div><div>', array_fill(0, 450, '<p>ghế gỗ sồi cao cấp</p>')).'</div>';
        expect(Post::calculateReadingTime($minified2250))->toBe(12);
    });

    // =========================================================================
    // Category 3: Script, Style, and Comment Stripping with Malicious & Complex Payloads
    // =========================================================================
    test('Category 3: Completely strips script, style, and comments without counting inner text', function () {
        // Case-insensitive script tag with 500 fake words inside
        $fakeScriptWords = implode(' ', array_fill(0, 500, 'fakeWordInsideScript'));
        $mixedCaseScript = '<SCRIPT type="text/javascript" id="test">var x = "'.$fakeScriptWords.'";</SCRIPT><p>Visible Article Content</p>';
        // Real words: "Visible", "Article", "Content" (3 words) -> 1 min
        expect(Post::calculateReadingTime($mixedCaseScript))->toBe(1);

        // Style block with CSS rules and generated content text
        $fakeStyleWords = implode(' ', array_fill(0, 400, 'color-blue-style-token'));
        $complexStyle = '<STYLE>@media screen and (min-width: 768px) { body::before { content: "'.$fakeStyleWords.'"; } }</STYLE><p>Modern Scandinavian Chair</p>';
        // Real words: 3 words -> 1 min
        expect(Post::calculateReadingTime($complexStyle))->toBe(1);

        // HTML comments with 1000 draft words
        $fakeCommentWords = implode(' ', array_fill(0, 1000, 'draftNotesNotForPublishing'));
        $commentPayload = '<!-- <div><p>'.$fakeCommentWords.'</p></div> --><p>Clean Published Paragraph</p>';
        // Real words: 3 words -> 1 min
        expect(Post::calculateReadingTime($commentPayload))->toBe(1);

        // Scripts containing HTML strings and angle brackets
        $scriptWithAngleBrackets = '<script>if (1 < 2 && 3 > 0) { let html = "<p>not a real paragraph</p>"; }</script><p>Actual post body</p>';
        // Real words: 3 words -> 1 min
        expect(Post::calculateReadingTime($scriptWithAngleBrackets))->toBe(1);

        // Multiple interleaved scripts, styles, and comments
        $interleaved = '<script>console.log(1);</script><p>WordA</p><style>.x{}</style><!-- c1 --><p>WordB</p><script>console.log(2);</script><p>WordC</p>';
        // Real words: 3 words -> 1 min
        expect(Post::calculateReadingTime($interleaved))->toBe(1);
    });

    // =========================================================================
    // Category 4: HTML Entities, Special Characters, and Unicode Spaces
    // =========================================================================
    test('Category 4: Accurately decodes HTML entities and normalizes all Unicode whitespace categories', function () {
        // Named entities
        $namedEntities = '<p>&quot;Sober&quot; &amp; &apos;Furniture&apos; &copy; 2026</p>';
        expect(Post::calculateReadingTime($namedEntities))->toBe(1);

        // Multiple non-breaking spaces between words
        $nbspSequence = '<p>Sản&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;phẩm&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;mới</p>';
        expect(Post::calculateReadingTime($nbspSequence))->toBe(1);

        // Numeric and hex entities (NBSP, em dash, quotes)
        $numericEntities = '<p>Bàn&#160;ăn&#x00A0;gỗ&#8212;sồi&#8220;Bắc&#8221;Âu</p>';
        expect(Post::calculateReadingTime($numericEntities))->toBe(1);

        // Full Unicode space spectrum: U+2000 through U+200A, U+202F, U+205F, U+3000
        $unicodeSpaces = "Word1\u{2000}Word2\u{2001}Word3\u{2002}Word4\u{2003}Word5\u{2004}Word6\u{2005}Word7\u{2006}Word8\u{2007}Word9\u{2008}Word10\u{2009}Word11\u{200A}Word12\u{202F}Word13\u{205F}Word14\u{3000}Word15";
        expect(Post::calculateReadingTime($unicodeSpaces))->toBe(1);

        // Soft hyphens (U+00AD) and zero-width spaces inside words
        $softHyphens = "<p>Nội\u{00AD}thất&shy;phòng\u{00AD}khách</p>";
        expect(Post::calculateReadingTime($softHyphens))->toBe(1);
    });

    // =========================================================================
    // Category 5: Multi-language & Diacritic Stress-Testing
    // =========================================================================
    test('Category 5: Correctly tokenizes multi-byte UTF-8, Vietnamese diacritics, CJK, Cyrillic, and Emoji', function () {
        // Vietnamese standard paragraph (20 words)
        $vnParagraph = 'Không gian sống Scandinavian luôn đề cao tính tối giản, công năng tiện ích cùng vẻ đẹp ấm áp từ chất liệu gỗ tự nhiên.';
        expect(Post::calculateReadingTime($vnParagraph))->toBe(1);

        // 400 Vietnamese words -> 400 / 200 = 2 minutes
        $vn400 = implode(' ', array_fill(0, 400, 'bàn-ghế-gỗ'));
        expect(Post::calculateReadingTime($vn400))->toBe(2);

        // CJK / Japanese text with spacing
        $cjkText = '北欧 家具 ミニマリズム デザイン 空間 設計 職人 技';
        expect(Post::calculateReadingTime($cjkText))->toBe(1);

        // Cyrillic
        $cyrillic = 'Скандинавский дизайн интерьера и мебели для современного дома';
        expect(Post::calculateReadingTime($cyrillic))->toBe(1);

        // Emoji words
        $emojiText = 'Nội thất 🛋️ bàn ghế 🪑 phòng ngủ 🛏️ ánh sáng 💡 tối giản ✨';
        expect(Post::calculateReadingTime($emojiText))->toBe(1);
    });

    // =========================================================================
    // Category 6: Exact Ceil() Mathematical Boundary Truth Table
    // =========================================================================
    test('Category 6: Mathematical ceil() boundary truth table verification', function () {
        $generateWords = fn (int $count) => implode(' ', array_fill(0, $count, 'word'));

        // 1 word -> 1 min
        expect(Post::calculateReadingTime($generateWords(1)))->toBe(1);

        // 199 words -> ceil(199/200) = 1 min
        expect(Post::calculateReadingTime($generateWords(199)))->toBe(1);

        // 200 words -> ceil(200/200) = 1 min
        expect(Post::calculateReadingTime($generateWords(200)))->toBe(1);

        // 201 words -> ceil(201/200) = 2 mins
        expect(Post::calculateReadingTime($generateWords(201)))->toBe(2);

        // 399 words -> ceil(399/200) = 2 mins
        expect(Post::calculateReadingTime($generateWords(399)))->toBe(2);

        // 400 words -> ceil(400/200) = 2 mins
        expect(Post::calculateReadingTime($generateWords(400)))->toBe(2);

        // 401 words -> ceil(401/200) = 3 mins
        expect(Post::calculateReadingTime($generateWords(401)))->toBe(3);

        // 600 words -> ceil(600/200) = 3 mins
        expect(Post::calculateReadingTime($generateWords(600)))->toBe(3);

        // 601 words -> ceil(601/200) = 4 mins
        expect(Post::calculateReadingTime($generateWords(601)))->toBe(4);

        // 1000 words -> ceil(1000/200) = 5 mins
        expect(Post::calculateReadingTime($generateWords(1000)))->toBe(5);

        // 2000 words -> ceil(2000/200) = 10 mins
        expect(Post::calculateReadingTime($generateWords(2000)))->toBe(10);

        // 10000 words -> ceil(10000/200) = 50 mins
        expect(Post::calculateReadingTime($generateWords(10000)))->toBe(50);
    });

    // =========================================================================
    // Category 7: High Volume, Memory Safety & ReDoS Catastrophic Backtracking Stress-Testing
    // =========================================================================
    test('Category 7: Extreme payload size, high volume, and ReDoS resistance', function () {
        // 1. 50,000 words large article (~400 KB)
        $largeText = '<article>'.implode(' ', array_fill(0, 50000, 'scandinavian-furniture-item')).'</article>';
        $startTime = microtime(true);
        $result = Post::calculateReadingTime($largeText);
        $duration = microtime(true) - $startTime;

        expect($result)->toBe(250); // 50000 / 200 = 250
        expect($duration)->toBeLessThan(0.5); // Under 500ms

        // 2. 5,000 nested HTML tags
        $nestedHtml = str_repeat('<div><section><p><span>', 1000).'Deeply Nested Content'.str_repeat('</span></p></section></div>', 1000);
        $startNested = microtime(true);
        $nestedResult = Post::calculateReadingTime($nestedHtml);
        $nestedDuration = microtime(true) - $startNested;

        expect($nestedResult)->toBe(1); // 3 words
        expect($nestedDuration)->toBeLessThan(0.2);

        // 3. 1,000 script and comment blocks
        $manyScripts = str_repeat('<script>var a = 1;</script><!-- comment --><style>.c{}</style>', 1000).'<p>Single paragraph with ten distinct vocabulary words for testing</p>';
        $startScripts = microtime(true);
        $scriptResult = Post::calculateReadingTime($manyScripts);
        $scriptDuration = microtime(true) - $startScripts;

        expect($scriptResult)->toBe(1); // 10 words
        expect($scriptDuration)->toBeLessThan(0.2);

        // 4. ReDoS attack vector: unclosed script tags & repeated malformed angle brackets
        $malformedTags = str_repeat('<script a="1" ', 2000).'word '.str_repeat('<<<<>>>>', 1000);
        $startReDoS = microtime(true);
        $redoResult = Post::calculateReadingTime($malformedTags);
        $redoDuration = microtime(true) - $startReDoS;

        expect($redoResult)->toBeGreaterThanOrEqual(1);
        expect($redoDuration)->toBeLessThan(0.2); // Proves no catastrophic regex backtracking
    });
});
