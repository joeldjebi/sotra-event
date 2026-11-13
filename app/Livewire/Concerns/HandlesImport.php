<?php

namespace App\Livewire\Concerns;

use App\Exports\ImportTemplateExport;
use App\Imports\RowImport;
use App\Models\AuditLog;
use App\Models\User;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Import Excel / CSV dans un écran de gestion de l'espace compagnie.
 */
trait HandlesImport
{
    use WithFileUploads;

    public bool $showImport = false;

    /** @var mixed Fichier temporaire Livewire */
    public $importFile = null;

    /** @var list<string> */
    public array $importErrors = [];

    /**
     * @return class-string<RowImport>
     */
    abstract protected function importClass(): string;

    abstract protected function importAuditAction(): string;

    abstract protected function templateFileName(): string;

    public function openImport(): void
    {
        $this->reset(['importFile', 'importErrors']);
        $this->resetValidation('importFile');
        $this->showImport = true;
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new ImportTemplateExport($this->importClass()), $this->templateFileName());
    }

    public function import(): void
    {
        $this->validate(['importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']], attributes: ['importFile' => 'fichier']);

        /** @var User $user */
        $user = auth()->user();
        $class = $this->importClass();
        $import = new $class((int) $user->company_id);

        Excel::import($import, $this->importFile->getRealPath(), null, $this->readerType());

        if ($import->errors !== []) {
            $this->importErrors = $import->errors;

            return;
        }

        AuditLog::record($this->importAuditAction(), $user->company, [], ['created' => $import->created, 'updated' => $import->updated]);

        $this->showImport = false;
        $this->reset(['importFile', 'importErrors']);
        $this->notify("Import terminé : {$import->created} ajouté(s), {$import->updated} mis à jour.");
    }

    private function readerType(): string
    {
        return match (strtolower($this->importFile->getClientOriginalExtension())) {
            'csv', 'txt' => \Maatwebsite\Excel\Excel::CSV,
            'xls' => \Maatwebsite\Excel\Excel::XLS,
            default => \Maatwebsite\Excel\Excel::XLSX,
        };
    }
}
