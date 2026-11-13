<?php

namespace App\Exports;

use App\Imports\RowImport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Modèle de fichier d'import (en-têtes + une ligne d'exemple).
 */
class ImportTemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param  class-string<RowImport>  $import
     */
    public function __construct(private readonly string $import) {}

    /**
     * @return list<list<string|int>>
     */
    public function array(): array
    {
        return [$this->import::example()];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->import::headings();
    }
}
