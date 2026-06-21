<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #18181b; font-size: 12px; margin: 0; }
        .header { padding: 18px 24px; background: #0e7490; color: #ffffff; }
        .header h1 { margin: 0; font-size: 22px; letter-spacing: 1px; }
        .header .x { background: #db2777; padding: 0 6px; border-radius: 3px; }
        .header .sub { margin-top: 2px; font-size: 11px; color: #cffafe; }
        .meta { padding: 6px 24px; font-size: 10px; color: #52525b; }
        .team { padding: 6px 24px 14px; page-break-inside: avoid; }
        .team h2 { font-size: 15px; margin: 12px 0 4px; border-bottom: 2px solid #0e7490; padding-bottom: 2px; }
        .badge { font-size: 9px; font-weight: bold; padding: 1px 6px; border-radius: 8px; }
        .badge-ok { background: #cffafe; color: #0e7490; }
        .badge-no { background: #ffedd5; color: #c2410c; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #e4e4e7; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f4f4f5; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
        .role { font-weight: bold; white-space: nowrap; }
        .combo { margin-bottom: 4px; }
        .combo-line { font-weight: bold; color: #0e7490; }
        .pending { color: #a1a1aa; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1>BEYBLADE <span class="x">X</span> &middot; Torneum</h1>
        <div class="sub">Reporte de equipos</div>
    </div>
    <div class="meta">Generado el {{ $generatedAt }} &middot; {{ count($teams) }} equipos</div>

    @foreach ($teams as $team)
        <div class="team">
            <h2>
                {{ $team['name'] }}
                @if ($team['is_complete'])
                    <span class="badge badge-ok">Completo</span>
                @else
                    <span class="badge badge-no">Incompleto {{ $team['beyblades_count'] }}/9</span>
                @endif
            </h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 18%">Rol</th>
                        <th style="width: 22%">Jugador</th>
                        <th>Combos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($team['members'] as $member)
                        <tr>
                            <td class="role">{{ $member['role_label'] }}</td>
                            <td>{{ $member['name'] }}</td>
                            <td>
                                @forelse ($member['beyblades'] as $combo)
                                    <div class="combo">
                                        <span class="combo-line">{{ $combo['line_label'] }}:</span>
                                        @foreach ($combo['parts'] as $part)
                                            {{ $part['slot_label'] }} {{ $part['name'] }}@if (! $loop->last) &middot; @endif
                                        @endforeach
                                    </div>
                                @empty
                                    <span class="pending">Pendiente</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
