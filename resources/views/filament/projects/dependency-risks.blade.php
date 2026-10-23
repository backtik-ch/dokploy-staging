<div style="display: grid; gap: 1.25rem">
    <p style="font-size: .875rem">
        {{ $record['manager'] }} · Version auditée : <strong>{{ $record['audit_version'] ?? '—' }}</strong> ({{ $record['audit_source'] }})
        @if ($record['audited_at'])
            · Dernier audit réussi : {{ $record['audited_at'] }}
        @endif
    </p>
    @foreach ($record['risks'] as $risk)
        <x-filament::section compact>
            <div style="display: grid; gap: .875rem">
                <x-filament::badge :color="$risk['color']">{{ $risk['severity'] }}</x-filament::badge>
                <p style="white-space: pre-wrap; overflow-wrap: anywhere; font-size: .875rem; line-height: 1.65">{{ $risk['message'] }}</p>
                @if ($risk['url'] && preg_match('~^https?://~i', $risk['url']))
                    <x-filament::link :href="$risk['url']" target="_blank" rel="noopener noreferrer">Consulter l’avis de sécurité</x-filament::link>
                @endif
            </div>
        </x-filament::section>
    @endforeach
</div>
