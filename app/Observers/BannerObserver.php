<?php

namespace App\Observers;

use App\Models\Banner;
use Illuminate\Support\Facades\Cache;

class BannerObserver
{
    private function clearCache(): void
    {
        Cache::forget('home_banners');
        Cache::forget('top_announcement_banner');
    }

    /**
     * Handle the Banner "created" event.
     */
    public function created(Banner $banner): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Banner "updated" event.
     */
    public function updated(Banner $banner): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Banner "deleted" event.
     */
    public function deleted(Banner $banner): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Banner "restored" event.
     */
    public function restored(Banner $banner): void
    {
        $this->clearCache();
    }

    /**
     * Handle the Banner "force deleted" event.
     */
    public function forceDeleted(Banner $banner): void
    {
        $this->clearCache();
    }
}
