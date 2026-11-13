<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Import Excel « tout ou rien » : toutes les lignes sont validées d'abord ;
 * à la moindre erreur rien n'est enregistré et les erreurs sont rendues ligne par ligne.
 */
abstract class RowImport implements ToCollection, WithHeadingRow
{
    /** @var list<string> */
    public array $errors = [];

    public int $created = 0;

    public int $updated = 0;

    public function __construct(protected readonly int $companyId) {}

    /**
     * En-têtes du modèle de fichier (ligne 1).
     *
     * @return list<string>
     */
    abstract public static function headings(): array;

    /**
     * Ligne d'exemple du modèle.
     *
     * @return list<string|int>
     */
    abstract public static function example(): array;

    /**
     * @return array<string, mixed>
     */
    abstract protected function rules(): array;

    /**
     * Contrôles métier supplémentaires sur une ligne valide. Renvoie un message d'erreur ou null.
     *
     * @param  array<string, mixed>  $row
     */
    protected function check(array $row): ?string
    {
        return null;
    }

    /**
     * Crée ou met à jour l'enregistrement. Renvoie true si créé.
     *
     * @param  array<string, mixed>  $row
     */
    abstract protected function persist(array $row): bool;

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        $prepared = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $data = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row->toArray());

            if (array_filter($data, fn ($value) => $value !== null && $value !== '') === []) {
                continue;
            }

            $validator = Validator::make($data, $this->rules());

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $this->errors[] = "Ligne {$line} : {$message}";
                }

                continue;
            }

            $error = $this->check($data);

            if ($error !== null) {
                $this->errors[] = "Ligne {$line} : {$error}";

                continue;
            }

            $prepared[] = $data;
        }

        if ($prepared === [] && $this->errors === []) {
            $this->errors[] = 'Le fichier ne contient aucune ligne.';
        }

        if ($this->errors !== []) {
            return;
        }

        DB::transaction(function () use ($prepared): void {
            foreach ($prepared as $row) {
                $this->persist($row) ? $this->created++ : $this->updated++;
            }
        });
    }

    /**
     * Normalise un libellé saisi librement (« En service », « en_service »…) en valeur d'enum.
     *
     * @param  array<string, string>  $options  valeur => libellé
     */
    protected static function matchOption(mixed $input, array $options): ?string
    {
        $normalize = fn (string $value): string => str_replace([' ', '-', '/'], '_', mb_strtolower(trim(
            strtr($value, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'É' => 'e'])
        )));

        $needle = $normalize((string) $input);

        foreach ($options as $value => $label) {
            if ($needle === $normalize($value) || $needle === $normalize($label)) {
                return $value;
            }
        }

        return null;
    }
}
