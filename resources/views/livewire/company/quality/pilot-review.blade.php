<div>
    <x-ui.table>
        <x-slot:head>
            <th>Événement</th>
            <th class="text-right">Pass vendus</th>
            <th class="text-right">Scannés aller</th>
            <th class="text-right">Note</th>
            <th class="text-right">À l'heure</th>
            <th class="text-right">Réclamations</th>
            <th class="text-right">Pannes</th>
            <th></th>
        </x-slot:head>
        @forelse ($rows as $row)
            <tr wire:key="pilot-{{ $row['event']->id }}">
                <td>
                    <p class="font-medium">{{ $row['event']->name }}</p>
                    <p class="text-[12px] text-ink-secondary">{{ $row['event']->starts_at->translatedFormat('j M Y') }} · {{ $row['event']->venue->name }}</p>
                </td>
                <td class="text-right tabular-nums">{{ number_format($row['sold'], 0, ',', ' ') }}</td>
                <td class="text-right tabular-nums">{{ $row['scanned_rate'] }} %</td>
                <td class="text-right tabular-nums">{{ \App\Services\Reporting\QualityMetrics::rating($row['rating']) }}<span class="text-ink-tertiary"> ({{ $row['reviews'] }})</span></td>
                <td class="text-right tabular-nums">{{ $row['on_time_rate'] !== null ? $row['on_time_rate'].' %' : '—' }}</td>
                <td class="text-right tabular-nums">{{ $row['complaints'] }}</td>
                <td class="text-right tabular-nums">{{ $row['breakdowns'] }}</td>
                <td class="text-right"><a class="text-[13px] font-medium text-brand-text hover:underline" href="{{ route('company.quality.overview', ['evenement' => $row['event']->id]) }}">Bilan</a></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-ui.empty-state icon="document" title="Aucun bilan" description="Les bilans apparaissent après les premiers événements du pilote." /></td></tr>
        @endforelse
    </x-ui.table>
</div>
