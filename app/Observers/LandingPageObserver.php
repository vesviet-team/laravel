<?php

namespace App\Observers;

use App\Models\LandingPage;
use Illuminate\Support\Facades\Cache;

class LandingPageObserver
{
    private function clearCache(LandingPage $landingPage): void
    {
        Cache::forget('landing_page_'.$landingPage->slug);
    }

    /**
     * Handle the LandingPage "created" event.
     */
    public function created(LandingPage $landingPage): void
    {
        $this->clearCache($landingPage);
    }

    /**
     * Handle the LandingPage "updated" event.
     */
    public function updated(LandingPage $landingPage): void
    {
        $this->clearCache($landingPage);
        if ($landingPage->isDirty('slug')) {
            Cache::forget('landing_page_'.$landingPage->getOriginal('slug'));
        }
    }

    /**
     * Handle the LandingPage "deleted" event.
     */
    public function deleted(LandingPage $landingPage): void
    {
        $this->clearCache($landingPage);
    }

    /**
     * Handle the LandingPage "restored" event.
     */
    public function restored(LandingPage $landingPage): void
    {
        $this->clearCache($landingPage);
    }

    /**
     * Handle the LandingPage "force deleted" event.
     */
    public function forceDeleted(LandingPage $landingPage): void
    {
        $this->clearCache($landingPage);
    }
}
