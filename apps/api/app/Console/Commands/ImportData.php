<?php

namespace App\Console\Commands;

use App\Services\Import\ImportRunner;
use Illuminate\Console\Command;

class ImportData extends Command
{
    protected $signature = "theja:import
        {--dry-run : Simula import senza scrivere nulla}
        {--source=bludata : Fonte import (bludata|csv)}
        {--file= : Percorso file (CSV) o cartella export}";

    protected $description = 'Import dati (Bludata/Fast/Iride o CSV standard Theja) con dry-run e report finale';

    public function handle(ImportRunner $runner): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $source = (string) ($this->option('source') ?: 'bludata');
        $file = (string) ($this->option('file') ?: '');

        if ($file === '' || ! file_exists($file)) {
            $this->error('Opzione --file mancante o path non valido.');
            return self::FAILURE;
        }

        try {
            $result = $runner->run([
                'dry_run' => $dryRun,
                'source'  => $source,
                'file'    => $file,
            ]);
        } catch (\Throwable $e) {
            $this->error('Import fallito: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info('Import completato.');
        $this->line('Imported: '.$result['imported']);
        $this->line('Skipped: '.$result['skipped']);
        $this->line('Errors: '.$result['errors']);

        return self::SUCCESS;
    }
}

