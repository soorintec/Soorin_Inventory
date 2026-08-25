{{-- گزارش ورود و خروج کالا در یک بازه --}}
@extends('pdf.layout')

@section('meta')
    {{ __('reports.period', ['from' => $date($from), 'to' => $date($to)]) }}
    @if ($warehouseName)
        <br>{{ __('reports.warehouse') }}: {{ $warehouseName }}
    @endif
@endsection

@section('content')
    <div class="summary-box">
        {{ __('stock.record_in') }}: {{ $qty($totalIn) }}
        &nbsp;|&nbsp;
        {{ __('stock.record_out') }}: {{ $qty($totalOut) }}
        &nbsp;|&nbsp;
        {{ __('reports.net_change') }}: {{ $qty($totalIn - $totalOut) }}
        &nbsp;|&nbsp;
        {{ __('reports.rows') }}: {{ $qty(count($rows)) }}
    </div>

    @if (empty($rows))
        <p class="muted">{{ __('stock.empty') }}</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 13%;">{{ __('common.date') }}</th>
                    <th style="width: 24%;">{{ __('items.label') }}</th>
                    <th style="width: 10%;">{{ __('items.version_label') }}</th>
                    <th style="width: 11%;">{{ __('reports.warehouse') }}</th>
                    <th style="width: 7%;">{{ __('stock.direction') }}</th>
                    <th style="width: 8%;">{{ __('stock.quantity') }}</th>
                    <th style="width: 11%;">{{ __('stock.reason') }}</th>
                    <th style="width: 16%;">{{ __('stock.user') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $datetime($row['at']) }}</td>
                        <td>{{ $row['item'] }}</td>
                        <td>{{ $row['version'] }}</td>
                        <td>{{ $row['warehouse'] }}</td>
                        <td class="{{ $row['direction'] === 'in' ? 'success' : 'danger' }}">
                            {{ __('stock.directions.' . $row['direction']) }}
                        </td>
                        <td class="num">{{ $qty($row['quantity']) }}</td>
                        <td>{{ __('stock.reasons.' . $row['reason']) }}</td>
                        <td>{{ $row['user'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
