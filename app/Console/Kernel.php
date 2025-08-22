// app/Console/Kernel.php
protected $commands = [
    \App\Console\Commands\BuildPrices::class,
	 \App\Console\Commands\ExportBuildCommand::class,
	 \App\Console\Commands\RecountExportRunProducts::class,
	 \App\Console\Commands\ExportsRepair::class,
];
 