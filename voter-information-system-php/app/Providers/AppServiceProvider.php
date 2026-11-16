<?php

namespace App\Providers;

use App\Services\Auth\CurrentUser;
use App\Services\Reports\ReportService;
use App\Support\Lang;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentUser::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();
        if (str_starts_with((string) config('app.url'), 'https://')) \Illuminate\Support\Facades\URL::forceScheme('https');
        View::composer('*', function ($view) {
            $user = app(CurrentUser::class);
            $view->with([
                'languages' => Lang::languages(),
                'lang' => Lang::current(),
                'rtl' => Lang::rtl(),
                'me' => $user,
                'branding' => config('voters.branding'),
                'reportIds' => array_values(array_filter(array_diff(ReportService::all(), \App\Services\Reports\AnalyticsService::IDS),
                    fn ($r) => !in_array($r, ReportService::ADMIN_ONLY, true) || $user->can('REPORT_SENSITIVE'))),
                'analyticsIds' => array_values(array_filter(\App\Services\Reports\AnalyticsService::IDS,
                    fn ($r) => !in_array($r, ReportService::ADMIN_ONLY, true) || $user->can('REPORT_SENSITIVE'))),
            ]);
        });
    }
}
