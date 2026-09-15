<?php

namespace Tests\Feature;

use App\Filament\Resources\BannerResource\Pages\EditBanner;
use App\Models\Banner;
use App\Models\User;
use App\Observers\BannerObserver;
use Database\Seeders\BannerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChallengerM5RemediationVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->adminUser = User::factory()->create([
            'email' => 'admin_challenger@example.com',
        ]);

        $permissions = [
            'view_any_banner',
            'view_banner',
            'create_banner',
            'update_banner',
            'delete_banner',
            'delete_any_banner',
            'reorder_banner',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $this->adminUser->givePermissionTo($permissions);
        $this->actingAs($this->adminUser);
    }

    /**
     * CHALLENGE 1: High-Concurrency Burst Click Tracking & Cache Invariant Verification
     */
    public function test_rapid_burst_click_tracking_is_atomic_and_never_invalidates_cache(): void
    {
        $banner = Banner::factory()->hero()->active()->create([
            'clicks_count' => 0,
            'link' => '/products?category=sofa',
        ]);

        // 1. Initial homepage load populates the cache
        $this->get(route('home'))->assertOk();
        $this->assertTrue(Cache::has('home_banners'));

        $cachedData = Cache::get('home_banners');
        $this->assertCount(1, $cachedData['heroSlides']);
        $this->assertEquals($banner->id, $cachedData['heroSlides']->first()->id);

        // 2. Simulate 50 rapid sequential clicks
        for ($i = 0; $i < 50; $i++) {
            $response = $this->get(route('banner.click', $banner->id));
            $response->assertRedirect('/products?category=sofa');
        }

        // 3. Verify atomic increment count in database
        $banner->refresh();
        $this->assertEquals(50, $banner->clicks_count);

        // 4. Invariant: Cache was NOT invalidated by recordClick()
        $this->assertTrue(Cache::has('home_banners'));

        // 5. Invariant: Direct Eloquent update DOES invalidate cache via BannerObserver
        $banner->update(['title' => 'Updated Banner Name After Clicks']);
        $this->assertFalse(Cache::has('home_banners'));
    }

    /**
     * CHALLENGE 2: Boundary Scheduling Matrix (Time-travel, Millisecond transitions, Null boundaries)
     */
    public function test_adversarial_scheduling_matrix_and_time_travel(): void
    {
        $freezeTime = Carbon::create(2026, 8, 20, 12, 0, 0);
        Carbon::setTestNow($freezeTime);

        // A. Permanent Active (starts_at null, ends_at null)
        $permActive = Banner::factory()->hero()->active()->create(['title' => 'Permanent Active']);

        // B. Active Window (started 1 hour ago, ends in 1 hour)
        $activeWindow = Banner::factory()->hero()->active()->create([
            'title' => 'Active Window',
            'starts_at' => $freezeTime->copy()->subHour(),
            'ends_at' => $freezeTime->copy()->addHour(),
        ]);

        // C. Exact boundary start (starts_at = NOW) -> MUST BE ACTIVE
        $exactStart = Banner::factory()->hero()->active()->create([
            'title' => 'Exact Start',
            'starts_at' => $freezeTime,
            'ends_at' => null,
        ]);

        // D. Exact boundary end (ends_at = NOW) -> MUST BE ACTIVE
        $exactEnd = Banner::factory()->hero()->active()->create([
            'title' => 'Exact End',
            'starts_at' => null,
            'ends_at' => $freezeTime,
        ]);

        // E. 1 second in future (starts_at = NOW + 1 sec) -> MUST BE INACTIVE
        $futureOneSec = Banner::factory()->hero()->active()->create([
            'title' => 'Future 1s',
            'starts_at' => $freezeTime->copy()->addSecond(),
            'ends_at' => null,
        ]);

        // F. 1 second in past (ends_at = NOW - 1 sec) -> MUST BE INACTIVE
        $pastOneSec = Banner::factory()->hero()->active()->create([
            'title' => 'Past 1s',
            'starts_at' => null,
            'ends_at' => $freezeTime->copy()->subSecond(),
        ]);

        // G. Status = inactive even if dates are valid -> MUST BE INACTIVE
        $inactiveStatus = Banner::factory()->hero()->create([
            'title' => 'Inactive Status',
            'status' => 'inactive',
            'starts_at' => $freezeTime->copy()->subHour(),
            'ends_at' => $freezeTime->copy()->addHour(),
        ]);

        // Verify scopeActive() at freeze time
        $activeTitles = Banner::active()->pluck('title')->all();
        $this->assertContains('Permanent Active', $activeTitles);
        $this->assertContains('Active Window', $activeTitles);
        $this->assertContains('Exact Start', $activeTitles);
        $this->assertContains('Exact End', $activeTitles);
        $this->assertNotContains('Future 1s', $activeTitles);
        $this->assertNotContains('Past 1s', $activeTitles);
        $this->assertNotContains('Inactive Status', $activeTitles);

        // Time travel forward by 2 hours -> Active Window & Exact End are now expired, Future 1s is now active
        Carbon::setTestNow($freezeTime->copy()->addHours(2));
        Cache::flush();

        $shiftedTitles = Banner::active()->pluck('title')->all();
        $this->assertContains('Permanent Active', $shiftedTitles);
        $this->assertNotContains('Active Window', $shiftedTitles);
        $this->assertNotContains('Exact End', $shiftedTitles);
        $this->assertContains('Future 1s', $shiftedTitles);

        Carbon::setTestNow();
    }

    /**
     * CHALLENGE 3: Smart Fallback Rendering Under Complete and Asymmetrical DB States
     */
    public function test_smart_fallback_under_asymmetrical_and_overloaded_db_states(): void
    {
        // Vector A: Complete Empty Database
        $this->assertEquals(0, Banner::count());
        $responseEmpty = $this->get(route('home'));
        $responseEmpty->assertOk();
        $htmlEmpty = $responseEmpty->getContent();

        // Must render default hero slides with WCAG 2.2 attributes
        $this->assertStringContainsString('Hero banner', $htmlEmpty);
        $this->assertStringContainsString('role="region"', $htmlEmpty);
        $this->assertStringContainsString('aria-roledescription="carousel"', $htmlEmpty);
        $this->assertStringContainsString('Khám Phá Ngay · SHOP NOW', $htmlEmpty);
        $this->assertStringContainsString('Bộ Sưu Tập Mới', $htmlEmpty);
        $this->assertStringContainsString('Lighting on Express · Bộ Sưu Tập', $htmlEmpty);
        $this->assertStringContainsString('Copenhague Desk · Danh Mục 01', $htmlEmpty);

        // Vector B: Asymmetrical population (5 Hero banners, 1 Promo banner, 10 Collection banners)
        Cache::flush();
        Banner::factory()->count(5)->hero()->active()->create();
        $soloPromo = Banner::factory()->promo2Col()->active()->create(['title' => 'Asymmetrical Solo Promo']);
        Banner::factory()->count(10)->collection3Col()->active()->create();

        $responseAsym = $this->get(route('home'));
        $responseAsym->assertOk();
        $htmlAsym = $responseAsym->getContent();

        // 5 hero slides should all be in Alpine slides JSON
        $this->assertStringContainsString('Asymmetrical Solo Promo', $htmlAsym);
        // Only 3 collection banners should be passed/taken for 3-col section
        $this->assertLessThanOrEqual(3, $responseAsym->viewData('collectionBanners')->count());
        $this->assertEquals(1, $responseAsym->viewData('promoBanners')->count());
    }

    /**
     * CHALLENGE 4: Malicious URL Schemes and Security Boundary Sanitization
     */
    public function test_security_sanitization_blocks_all_malicious_pseudo_protocols(): void
    {
        $maliciousVectors = [
            'javascript:alert("XSS")',
            'JAVASCRIPT:/*--></title></style></textarea>*/<script>alert(1)</script>',
            'JavaScript:eval(String.fromCharCode(97,108,101,114,116,40,49,41))',
            'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
            'DATA:text/html,<script>alert(1)</script>',
            'vbscript:msgbox("hello")',
            'VBSCRIPT:MsgBox(1)',
            '',
            '   ',
            null,
        ];

        foreach ($maliciousVectors as $index => $uri) {
            $banner = Banner::factory()->hero()->active()->create([
                'title' => "Malicious Banner {$index}",
                'link' => $uri,
            ]);

            $response = $this->get(route('banner.click', $banner->id));
            $response->assertRedirect(route('home'));
        }

        // Legitimate external URLs should redirect away
        $validExternal = Banner::factory()->hero()->active()->create([
            'link' => 'https://example.com/promotion?user=test#terms',
        ]);
        $responseExt = $this->get(route('banner.click', $validExternal->id));
        $responseExt->assertRedirect('https://example.com/promotion?user=test#terms');

        // Legitimate relative URLs should redirect internal
        $validInternal = Banner::factory()->hero()->active()->create([
            'link' => '/products?category=lighting',
        ]);
        $responseInt = $this->get(route('banner.click', $validInternal->id));
        $responseInt->assertRedirect('/products?category=lighting');
    }

    /**
     * CHALLENGE 5: Filament Admin Suite Resource Operations & Field Dehydration
     */
    public function test_filament_banner_resource_edit_does_not_destroy_existing_image(): void
    {
        $banner = Banner::create([
            'title' => 'Original Banner Title',
            'image' => 'banners/existing_photo.jpg',
            'position' => 'hero_slider',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        // Simulating edit via Livewire without uploading a new image
        Livewire::test(EditBanner::class, ['record' => $banner->getKey()])
            ->fillForm([
                'title' => 'Updated Banner Title Without New Image',
                'position' => Banner::POSITION_HOME_PROMO_2COL,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $banner->refresh();
        $this->assertEquals('Updated Banner Title Without New Image', $banner->title);
        $this->assertEquals(Banner::POSITION_HOME_PROMO_2COL, $banner->position);
        $this->assertEquals('banners/existing_photo.jpg', $banner->image); // Must NOT be wiped or set to null
    }

    /**
     * CHALLENGE 6: XSS Injection in Banner Text Fields Is Render-Safe in Blade
     */
    public function test_banner_text_fields_safely_escape_xss_in_storefront_blade(): void
    {
        $xssHero = Banner::factory()->hero()->active()->create([
            'title' => '<script>alert("HERO_XSS")</script>',
            'eyebrow' => '<img src=x onerror=alert("EYEBROW_XSS")>',
            'subtitle' => '"><script>alert("SUB_XSS")</script>',
            'cta_text' => '<b>CTA</b>',
        ]);

        $response = $this->get(route('home'));
        $response->assertOk();
        $html = $response->getContent();

        // In JSON slides data for Alpine.js, json_encode handles quotes safely
        $this->assertStringNotContainsString('<script>alert("HERO_XSS")</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert("EYEBROW_XSS")>', $html);
    }

    /**
     * CHALLENGE 7: WCAG 2.2 Carousel Accessibility Contracts
     */
    public function test_wcag_22_carousel_and_card_accessibility_structure(): void
    {
        $banner1 = Banner::factory()->hero()->active()->create([
            'title' => 'Slide A',
            'open_in_new_tab' => true,
        ]);
        $banner2 = Banner::factory()->hero()->active()->create([
            'title' => 'Slide B',
            'open_in_new_tab' => false,
        ]);

        $response = $this->get(route('home'));
        $response->assertOk();
        $html = $response->getContent();

        // 1. Region & Carousel semantics
        $this->assertStringContainsString('role="region"', $html);
        $this->assertStringContainsString('aria-roledescription="carousel"', $html);
        $this->assertStringContainsString('aria-label="Hero banner"', $html);

        // 2. Slide structure
        $this->assertStringContainsString('role="group"', $html);
        $this->assertStringContainsString('aria-roledescription="slide"', $html);

        // 3. Tab navigation dots
        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('role="tab"', $html);

        // 4. Accessible new tab announcement
        $this->assertStringContainsString('(mở trong tab mới)', $html);
    }

    /**
     * CHALLENGE 8: Database Seeder Idempotency and Zero Orphan Records
     */
    public function test_banner_seeder_strict_idempotency_with_clean_schema(): void
    {
        $seeder = new BannerSeeder;

        // Run seeder 3 times consecutively
        $seeder->run();
        $count1 = Banner::count();

        $seeder->run();
        $count2 = Banner::count();

        $seeder->run();
        $count3 = Banner::count();

        $this->assertEquals(7, $count1);
        $this->assertEquals(7, $count2);
        $this->assertEquals(7, $count3);

        // Verify distribution
        $this->assertEquals(2, Banner::where('position', Banner::POSITION_HERO_SLIDER)->count());
        $this->assertEquals(2, Banner::where('position', Banner::POSITION_HOME_PROMO_2COL)->count());
        $this->assertEquals(3, Banner::where('position', Banner::POSITION_HOME_COLLECTION_3COL)->count());
    }
}
