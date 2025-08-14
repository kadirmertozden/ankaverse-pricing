// app/Console/Kernel.php
protected $commands = [
    \App\Console\Commands\BuildPrices::class,
	 \App\Console\Commands\ExportBuildCommand::class,
];
