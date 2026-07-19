<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // PropOff module policies (explicit — models live in App\Models\PropOff).
        \Illuminate\Support\Facades\Gate::policy(\App\Models\PropOff\Event::class, \App\Policies\PropOff\EventPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\PropOff\EventQuestion::class, \App\Policies\PropOff\EventQuestionPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\PropOff\Entry::class, \App\Policies\PropOff\EntryPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\PropOff\Group::class, \App\Policies\PropOff\GroupPolicy::class);
        Vite::prefetch(concurrency: 3);
    }
}
