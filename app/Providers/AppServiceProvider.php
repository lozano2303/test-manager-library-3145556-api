<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\VerifyDatabaseSchemaCommand::class,
                \App\Console\Commands\VerifyDomainValueObjectsCommand::class,
                \App\Console\Commands\VerifyBookDomainCommand::class,
                \App\Console\Commands\VerifyLoanDomainCommand::class,
                \App\Console\Commands\VerifyMemberUserDomainCommand::class,
                \App\Console\Commands\VerifyAuthenticationServicesCommand::class,
                \App\Console\Commands\VerifyBookServicesCommand::class,
                \App\Console\Commands\VerifyLendBooksCommand::class,
                \App\Console\Commands\VerifyReturnBooksCommand::class,
                \App\Console\Commands\VerifyCoverStorageCommand::class,
                \App\Console\Commands\VerifyHttpRoutesCommand::class,
                \App\Console\Commands\VerifySystemStartupCommand::class,
                \App\Console\Commands\VerifyDomainConstraintsCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}