<?php

namespace UsmanAhmed\ImportExport;

use Illuminate\Support\ServiceProvider;
use UsmanAhmed\ImportExport\Console\CleanupImportsCommand;
use UsmanAhmed\ImportExport\Console\MaintainExportsCommand;
use UsmanAhmed\ImportExport\Console\RecoverStaleImportsCommand;
use UsmanAhmed\ImportExport\Console\RetryNotificationsCommand;
use UsmanAhmed\ImportExport\Contracts\ContextRestorer;
use UsmanAhmed\ImportExport\Contracts\CurrentContext;
use UsmanAhmed\ImportExport\Contracts\ImportAuthorizer;
use UsmanAhmed\ImportExport\Contracts\NotificationRecipientResolver;
use UsmanAhmed\ImportExport\Contracts\SourceReader;
use UsmanAhmed\ImportExport\Events\ExportChanged;
use UsmanAhmed\ImportExport\Events\ImportCancelled;
use UsmanAhmed\ImportExport\Events\ImportCompleted;
use UsmanAhmed\ImportExport\Events\ImportCompletedWithErrors;
use UsmanAhmed\ImportExport\Events\ImportFailed;
use UsmanAhmed\ImportExport\Notifications\QueueCompletionNotice;
use UsmanAhmed\ImportExport\Readers\CsvReader;
use UsmanAhmed\ImportExport\Readers\ReaderRegistry;
use UsmanAhmed\ImportExport\Readers\XlsxReader;

final class ImportExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/bulk-imports.php', 'bulk-imports');
        $this->mergeConfigFrom(__DIR__.'/../config/import-export.php', 'import-export');
        $this->app->bind(CurrentContext::class, fn ($app) => $app->make(config('import-export.current_context')));

        $this->app->bind(NotificationRecipientResolver::class, fn ($app) => $app->make(config('import-export.notifications.recipient_resolver')));

        $this->app->singleton('bulk-imports', fn (): BulkImportManager => new BulkImportManager);
        $this->app->alias('bulk-imports', BulkImportManager::class);

        $this->app->bind(ContextRestorer::class, function ($app): ContextRestorer {
            $class = config('bulk-imports.contracts.'.ContextRestorer::class)
                ?? config('bulk-imports.context.restorer');

            return $app->make($class);
        });
        $this->app->bind(ImportAuthorizer::class, function ($app): ImportAuthorizer {
            $class = config('bulk-imports.contracts.'.ImportAuthorizer::class)
                ?? config('bulk-imports.authorization.authorizer');

            return $app->make($class);
        });

        $this->app->singleton(ReaderRegistry::class, function ($app): ReaderRegistry {
            $registry = new ReaderRegistry([
                $app->make(CsvReader::class),
                $app->make(XlsxReader::class),
            ]);

            foreach (config('bulk-imports.readers', []) as $readerClass) {
                if (! is_string($readerClass)) {
                    throw new \LogicException('Configured readers must be class strings.');
                }
                $reader = $app->make($readerClass);
                if (! $reader instanceof SourceReader) {
                    throw new \LogicException("Configured reader [{$readerClass}] must implement SourceReader.");
                }
                $registry->add($reader);
            }

            return $registry;
        });
    }

    public function boot(): void
    {
        foreach ([ImportCompleted::class, ImportCompletedWithErrors::class,
            ImportFailed::class, ImportCancelled::class, ExportChanged::class] as $event) {
            $this->app['events']->listen($event, [QueueCompletionNotice::class, 'handle']);
        }
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (config('import-export.routes.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/import-export.php');
        }

        if (config('bulk-imports.routes.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([CleanupImportsCommand::class, RecoverStaleImportsCommand::class, MaintainExportsCommand::class, RetryNotificationsCommand::class]);
            $this->publishes([
                __DIR__.'/../config/bulk-imports.php' => config_path('bulk-imports.php'),
            ], 'bulk-imports-config');
            $this->publishes([__DIR__.'/../config/import-export.php' => config_path('import-export.php')], 'import-export-config');
            $migrations = [];
            foreach (glob(__DIR__.'/../database/migrations/*.php') as $file) {
                $migrations[$file] = database_path('migrations/'.basename($file));
            }
            $this->publishesMigrations($migrations, 'bulk-imports-migrations');
            $this->publishesMigrations($migrations, 'import-export-migrations');
        }
    }
}
