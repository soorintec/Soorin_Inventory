{{-- چاپِ کاردکسِ کالا — ورود سبز، خروج قرمز، همراه با ماندهٔ در حرکت --}}
@extends('pdf.layout')

@section('meta')
    {{ $item->name }} @if ($item->code) ({{ $item->code }}) @endif
    <br>{{ __('items.total_stock') }}: {{ $qty($balance) }} {{ $item->unit }}
@endsection

@section('content')
    @if (empty($rows))
        <div class="summary-box">{{ __('stock.kardex_empty') }}</div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 4%;">{{ __('reports.row_no') }}</th>
                    <th style="width: 16%;">{{ __('common.date') }}</th>
                    <th style="width: 13%;">{{ __('stock.warehouse') }}</th>
                    <th style="width: 9%;">{{ __('items.version_label') }}</th>
                    <th style="width: 14%;">{{ __('stock.reason') }}</th>
                    <th style="width: 10%;">{{ __('stock.directions.in') }}</th>
                    <th style="width: 10%;">{{ __('stock.directions.out') }}</th>
                    <th style="width: 10%;">{{ __('stock.balance') }}</th>
                    <th style="width: 14%;">{{ __('stock.user') }}</th>
                </tr>
            </thead>
            <tbody>
                @php $rowNo = 0; @endphp
                @foreach ($rows as $row)
                    @php $m = $row['movement']; $isIn = $m->direction === \App\Models\StockMovement::DIRECTION_IN; @endphp
                    <tr>
                        <td class="num">{{ $digits(++$rowNo) }}</td>
                        <td class="num">{{ $datetime($m->created_at) }}</td>
                        <td>{{ $m->warehouse?->name ?? '—' }}</td>
                        <td>{{ $m->itemVersion?->version_code ?? '—' }}</td>
                        <td>{{ __('stock.reasons.' . $m->reason) }}</td>
                        <td class="num success">{{ $isIn ? $qty($m->quantity) : '' }}</td>
                        <td class="num danger">{{ $isIn ? '' : $qty($m->quantity) }}</td>
                        <td class="num" style="font-weight: bold;">{{ $qty($row['balance']) }}</td>
                        <td>{{ $m->user?->name ?? __('activity.system') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
