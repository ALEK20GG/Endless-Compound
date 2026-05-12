<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>DB Check — Endless Compound</title>
    <style>
        body  { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; }
        h1    { color: #38bdf8; margin-bottom: 1.5rem; }
        h2    { color: #94a3b8; border-bottom: 1px solid #334155; padding-bottom: .4rem; margin-top: 2rem; }
        .ok   { color: #4ade80; }
        .err  { color: #f87171; }
        .skip { color: #fbbf24; }
        table { border-collapse: collapse; width: 100%; margin-top: .5rem; }
        th,td { text-align: left; padding: .4rem .8rem; border: 1px solid #334155; }
        th    { background: #1e293b; color: #94a3b8; }
        pre   { background: #1e293b; padding: 1rem; border-radius: .5rem; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }
        .warn { background: #7c2d12; color: #fca5a5; padding: .5rem 1rem; border-radius: .5rem; margin-bottom: 1rem; display:inline-block; }
    </style>
</head>
<body>

<h1>🔍 DB Check — Endless Compound</h1>
<span class="warn">⚠️ Solo in locale — non deployare questo file in produzione</span>

{{-- 1. ENV --}}
<h2>1. Variabili d'ambiente</h2>
<table>
    <tr><th>Chiave</th><th>Valore</th></tr>
    @foreach($results['env'] as $key => $val)
    <tr><td>{{ $key }}</td><td>{{ $val }}</td></tr>
    @endforeach
</table>

{{-- 2. Connessione --}}
<h2>2. Connessione</h2>
@php $c = $results['connection']; $ok = str_contains($c['status'], 'OK'); @endphp
<p class="{{ $ok ? 'ok' : 'err' }}">{{ $c['status'] }} — {{ $c['message'] }}</p>

{{-- 3. Tabelle --}}
<h2>3. Tabelle</h2>
<table>
    <tr><th>Tabella</th><th>Stato</th><th>Righe / Errore</th></tr>
    @foreach($results['tables'] as $table => $info)
    <tr>
        <td>{{ $table }}</td>
        <td class="{{ str_contains($info['status'], 'OK') ? 'ok' : 'err' }}">{{ $info['status'] }}</td>
        <td>{{ $info['rows'] ?? $info['message'] ?? '' }}</td>
    </tr>
    @endforeach
</table>

{{-- 4. Sample rooms --}}
<h2>4. Sample rooms (ultime 5)</h2>
@if(str_contains($results['rooms_sample']['status'], 'OK'))
    @if(empty($results['rooms_sample']['data']))
        <p class="skip">Tabella vuota — nessuna room presente.</p>
    @else
        <table>
            <tr><th>roid</th><th>name</th><th>isprivate</th><th>maxplayers</th><th>owner_uid</th><th>createdat</th></tr>
            @foreach($results['rooms_sample']['data'] as $row)
            @php $row = (array)$row; @endphp
            <tr>
                <td>{{ $row['roid'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['isprivate'] ? 'Sì' : 'No' }}</td>
                <td>{{ $row['maxplayers'] }}</td>
                <td>{{ $row['owner_uid'] }}</td>
                <td>{{ $row['createdat'] }}</td>
            </tr>
            @endforeach
        </table>
    @endif
@else
    <p class="err">{{ $results['rooms_sample']['message'] }}</p>
@endif

{{-- 5. INSERT + DELETE --}}
<h2>5. Test INSERT + DELETE</h2>
@php $ins = $results['insert_delete']; $cls = str_contains($ins['status'],'OK') ? 'ok' : (str_contains($ins['status'],'SKIP') ? 'skip' : 'err'); @endphp
<p class="{{ $cls }}">{{ $ins['status'] }} — {{ $ins['message'] }}</p>

{{-- Raw JSON --}}
<h2>Raw JSON</h2>
<pre>{{ json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>

</body>
</html>
