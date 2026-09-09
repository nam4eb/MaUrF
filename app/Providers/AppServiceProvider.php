<?php

namespace App\Providers;

use App\Domain\FacebookAnalytics\Parser\CommentsParser;
use App\Domain\FacebookAnalytics\Parser\FriendsParser;
use App\Domain\FacebookAnalytics\Parser\MentionsParser;
use App\Domain\FacebookAnalytics\Parser\MessengerThreadParser;
use App\Domain\FacebookAnalytics\Parser\ParserRegistry;
use App\Domain\FacebookAnalytics\Parser\PostsParser;
use App\Domain\FacebookAnalytics\Parser\ProfileIdentityParser;
use App\Domain\FacebookAnalytics\Parser\ReactionsParser;
use App\Domain\FacebookAnalytics\Parser\TagsParser;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ParserRegistry::class, fn ($app) => new ParserRegistry([$app->make(ProfileIdentityParser::class), $app->make(MessengerThreadParser::class), $app->make(FriendsParser::class), $app->make(CommentsParser::class), $app->make(ReactionsParser::class), $app->make(MentionsParser::class), $app->make(TagsParser::class), $app->make(PostsParser::class)]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
