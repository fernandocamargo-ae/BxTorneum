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
        .player { padding: 6px 24px 14px; page-break-inside: avoid; }
        .player h2 { font-size: 15px; margin: 12px 0 4px; border-bottom: 2px solid #0e7490; padding-bottom: 2px; }
        .nickname { color: #0e7490; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #e4e4e7; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f4f4f5; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; }
        .combo { margin-bottom: 4px; }
        .combo-line { font-weight: bold; color: #0e7490; }
    </style>
</head>
<body>
    <div class="header">
        <h1>BEYBLADE <span class="x">X</span> &middot; Torneum</h1>
        <div class="sub">Reporte de jugadores</div>
    </div>
    <div class="meta">Generado el {{ $generatedAt }} &middot; {{ count($players) }} jugadores</div>

    @foreach ($players as $player)
        <div class="player">
            <h2>{{ $player['name'] }} &middot; <span class="nickname">{{ $player['nickname'] }}</span></h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 20%">Deck de torneo</th>
                        <th>Combos</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{{ $player['deck_name'] }}</td>
                        <td>
                            @foreach ($player['beyblades'] as $combo)
                                <div class="combo">
                                    <span class="combo-line">{{ $combo['line_label'] }}:</span>
                                    @foreach ($combo['parts'] as $part)
                                        {{ $part['slot_label'] }} {{ $part['name'] }}@if (! $loop->last) &middot; @endif
                                    @endforeach
                                </div>
                            @endforeach
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
