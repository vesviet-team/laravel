<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class HomepageController extends Controller
{
    public function index(): View
    {
        $featuredProducts = Product::featured()
            ->with('category')
            ->take(8)
            ->get();

        $newArrivals = Product::active()
            ->latest()
            ->with('category')
            ->take(8)
            ->get();

        $categories = Category::whereNull('parent_id')
            ->orderBy('name')
            ->get();

        // Ensure class definitions are fully loaded before cache deserialization
        class_exists(Banner::class);
        class_exists(Collection::class);

        $banners = Cache::remember('home_banners', 3600, function () {
            return [
                'heroSlides' => Banner::active()->position(Banner::POSITION_HERO_SLIDER)->ordered()->get(),
                'promoBanners' => Banner::active()->position(Banner::POSITION_HOME_PROMO_2COL)->ordered()->take(2)->get(),
                'collectionBanners' => Banner::active()->position(Banner::POSITION_HOME_COLLECTION_3COL)->ordered()->take(3)->get(),
            ];
        });

        // Self-healing defensive check against stale incomplete class cache
        if (
            ! is_array($banners) ||
            ! isset($banners['heroSlides']) ||
            $banners['heroSlides'] instanceof \__PHP_Incomplete_Class ||
            (isset($banners['promoBanners']) && $banners['promoBanners'] instanceof \__PHP_Incomplete_Class) ||
            (isset($banners['collectionBanners']) && $banners['collectionBanners'] instanceof \__PHP_Incomplete_Class)
        ) {
            Cache::forget('home_banners');
            $banners = [
                'heroSlides' => Banner::active()->position(Banner::POSITION_HERO_SLIDER)->ordered()->get(),
                'promoBanners' => Banner::active()->position(Banner::POSITION_HOME_PROMO_2COL)->ordered()->take(2)->get(),
                'collectionBanners' => Banner::active()->position(Banner::POSITION_HOME_COLLECTION_3COL)->ordered()->take(3)->get(),
            ];
        }

        $heroSlides = $banners['heroSlides'];
        $promoBanners = $banners['promoBanners'];
        $collectionBanners = $banners['collectionBanners'];

        return view('storefront.home.index', compact(
            'featuredProducts',
            'newArrivals',
            'categories',
            'heroSlides',
            'promoBanners',
            'collectionBanners'
        ));
    }
}
