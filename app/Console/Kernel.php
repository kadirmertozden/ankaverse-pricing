// app/Console/Kernel.php
protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
{
    // ...
    $schedule->command('exports:sync --all')->everyMinute(); // dueForSync kontrolü içeride
}

protected $commands = [
    \App\Console\Commands\BuildPrices::class,
	 \App\Console\Commands\ExportBuildCommand::class,
	 \App\Console\Commands\RecountExportRunProducts::class,
	 \App\Console\Commands\ExportsRepair::class,
];
 
