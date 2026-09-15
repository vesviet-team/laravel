<?php

use App\Services\TocService;

beforeEach(function () {
    $this->tocService = new TocService;
});

test('extracts h2 and h3 headings and generates correct slug ids and hierarchy', function () {
    $html = <<<'HTML'
    <h2>Giới thiệu chung</h2>
    <p>Nội dung giới thiệu...</p>
    <h3>Chi tiết phong cách Bắc Âu</h3>
    <p>Chi tiết phong cách...</p>
    <h2>Kết luận</h2>
    HTML;

    $result = $this->tocService->generate($html);

    expect($result['toc'])->toHaveCount(3);
    expect($result['toc'][0])->toBe([
        'id' => 'gioi-thieu-chung',
        'title' => 'Giới thiệu chung',
        'level' => 2,
    ]);
    expect($result['toc'][1])->toBe([
        'id' => 'chi-tiet-phong-cach-bac-au',
        'title' => 'Chi tiết phong cách Bắc Âu',
        'level' => 3,
    ]);
    expect($result['toc'][2])->toBe([
        'id' => 'ket-luan',
        'title' => 'Kết luận',
        'level' => 2,
    ]);

    expect($result['html'])->toContain('id="gioi-thieu-chung"');
    expect($result['html'])->toContain('id="chi-tiet-phong-cach-bac-au"');
    expect($result['html'])->toContain('id="ket-luan"');
});

test('handles Vietnamese diacritics and special characters in headings', function () {
    $html = '<h2>Đặc tính & Cách bảo quản Gỗ Sồi Bắc Âu (2026)</h2>';

    $result = $this->tocService->generate($html);

    expect($result['toc'])->toHaveCount(1);
    expect($result['toc'][0]['id'])->toBe('dac-tinh-cach-bao-quan-go-soi-bac-au-2026');
    expect($result['toc'][0]['title'])->toBe('Đặc tính & Cách bảo quản Gỗ Sồi Bắc Âu (2026)');
    expect($result['toc'][0]['level'])->toBe(2);
    expect($result['html'])->toContain('<h2 id="dac-tinh-cach-bao-quan-go-soi-bac-au-2026">');
});

test('injects anchor ids and preserves existing classes and attributes', function () {
    $html = '<h2 class="text-2xl font-bold" data-section="intro">Phòng Khách</h2>';

    $result = $this->tocService->generate($html);

    expect($result['toc'][0]['id'])->toBe('phong-khach');
    expect($result['html'])->toContain('id="phong-khach"');
    expect($result['html'])->toContain('class="text-2xl font-bold"');
    expect($result['html'])->toContain('data-section="intro"');
});

test('replaces existing id attributes in headings with clean slugs', function () {
    $html = '<h2 id="old-raw-id" class="heading">Phòng Ngủ</h2>';

    $result = $this->tocService->generate($html);

    expect($result['toc'][0]['id'])->toBe('phong-ngu');
    expect($result['html'])->toContain('id="phong-ngu"');
    expect($result['html'])->not->toContain('id="old-raw-id"');
});

test('resolves duplicate heading titles with unique incremental suffixes', function () {
    $html = <<<'HTML'
    <h2>Không Gian Sống</h2>
    <p>Phần 1</p>
    <h2>Không Gian Sống</h2>
    <p>Phần 2</p>
    <h2>Không Gian Sống</h2>
    HTML;

    $result = $this->tocService->generate($html);

    expect($result['toc'])->toHaveCount(3);
    expect($result['toc'][0]['id'])->toBe('khong-gian-song');
    expect($result['toc'][1]['id'])->toBe('khong-gian-song-1');
    expect($result['toc'][2]['id'])->toBe('khong-gian-song-2');

    expect($result['html'])->toContain('id="khong-gian-song"');
    expect($result['html'])->toContain('id="khong-gian-song-1"');
    expect($result['html'])->toContain('id="khong-gian-song-2"');
});

test('handles empty, null, or blank strings gracefully without error', function () {
    expect($this->tocService->generate(''))->toBe(['toc' => [], 'html' => '']);
    expect($this->tocService->generate(null))->toBe(['toc' => [], 'html' => '']);
    expect($this->tocService->generate('   '))->toBe(['toc' => [], 'html' => '   ']);
});

test('handles html with no headings gracefully', function () {
    $html = '<p>Chỉ có nội dung văn bản thông thường không có thẻ h2 hoặc h3.</p>';

    $result = $this->tocService->generate($html);

    expect($result['toc'])->toBeEmpty();
    expect($result['html'])->toBe($html);
});

test('ignores h1, h4, h5, h6 headings and only processes h2 and h3', function () {
    $html = <<<'HTML'
    <h1>Tiêu Đề Bài Viết H1</h1>
    <h2>Tiêu Đề H2 Hợp Lệ</h2>
    <h4>Tiêu Đề Con H4</h4>
    <h5>Tiêu Đề Con H5</h5>
    <h6>Tiêu Đề Con H6</h6>
    <h3>Tiêu Đề H3 Hợp Lệ</h3>
    HTML;

    $result = $this->tocService->generate($html);

    expect($result['toc'])->toHaveCount(2);
    expect($result['toc'][0]['title'])->toBe('Tiêu Đề H2 Hợp Lệ');
    expect($result['toc'][1]['title'])->toBe('Tiêu Đề H3 Hợp Lệ');
    expect($result['html'])->not->toContain('id="tieu-de-bai-viet-h1"');
    expect($result['html'])->not->toContain('id="tieu-de-con-h4"');
});

test('strips nested html tags from heading title while keeping text', function () {
    $html = '<h2><strong>1.</strong> <em>Nghệ thuật</em> bài trí ánh sáng</h2>';

    $result = $this->tocService->generate($html);

    expect($result['toc'][0]['title'])->toBe('1. Nghệ thuật bài trí ánh sáng');
    expect($result['toc'][0]['id'])->toBe('1-nghe-thuat-bai-tri-anh-sang');
});
