<?php

namespace App\Providers;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();

        Gate::define('admin', fn (User $user): bool => $user->is_admin);

        $this->configureContainsSearch();
    }

    /**
     * ->whereContains('title', $text) / ->orWhereContains(...): rows whose column contains the
     * text exactly as typed. A plain LIKE treats % and _ in user input as wildcards, so typing
     * "%" would match everything. '!' is the escape character because a backslash means
     * different things in MySQL and SQLite.
     */
    protected function configureContainsSearch(): void
    {
        $contains = function (string $boolean) {
            return function (string $column, string $text) use ($boolean) {
                /** @var QueryBuilder $this */
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text);

                return $this->whereRaw($this->getGrammar()->wrap($column)." LIKE ? ESCAPE '!'", ["%{$escaped}%"], $boolean);
            };
        };

        QueryBuilder::macro('whereContains', $contains('and'));
        QueryBuilder::macro('orWhereContains', $contains('or'));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Times are stored in UTC; ->local() shows one in the users' time zone (Europe/Riga),
        // including summer time. Used wherever a date or time is displayed.
        foreach ([CarbonImmutable::class, Carbon::class] as $class) {
            $class::macro('local', function () {
                /** @var CarbonImmutable|Carbon $this */
                return $this->copy()->setTimezone(config('app.display_timezone'));
            });
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
