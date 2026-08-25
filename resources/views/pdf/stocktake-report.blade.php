{{-- گزارش نتیجه انبارگردانی: شمارش در برابر دفتر، با مغایرت‌ها. --}}
@extends('pdf.layout')

@section('meta')
    {{ __('stocktake.code') }}: <span class="ltr">{{ $stocktake->code }}</span>
    <br>{{ __('stocktake.warehouse') }}: {{ $stocktake->warehouse->name }}
    <br>{{ __('stocktake.status') }}: {{ __('stocktake.statuses.' . $stocktake->status) }}
@endsection

@section('content')
    <div class="summary-box">
        {{ __('stocktake.total_lines') }}: {{ $qty($stocktake->lines->count()) }}
        &nbsp;|&nbsp;
        {{ __('stocktake.counted_lines') }}: {{ $qty($stocktake->countedLines()->count()) }}
        &nbsp;|&nbsp;
        {{ __('stocktake.discrepancy_lines') }}: {{ $qty($stocktake->discrepancies()->count()) }}
        &nbsp;|&nbsp;
        {{ __('stocktake.total_surplus') }}: {{ $qty($stocktake->totalSurplus()) }}
        &nbsp;|&nbsp;
        {{ __('stocktake.total_shortage') }}: {{ $qty($stocktake->totalShortage()) }}
    </div>

    @if ($stocktake->started_at)
        <p class="muted" style="font-size: 8.5pt;">
            {{ __('stocktake.started_at') }}: {{ $datetime($stocktake->started_at) }}
            @if ($stocktake->startedBy) — {{ $stocktake->startedBy->name }} @endif
            @if ($stocktake->closed_at)
                &nbsp;|&nbsp; {{ __('stocktake.closed_at') }}: {{ $datetime($stocktake->closed_at) }}
                @if ($stocktake->closedBy) — {{ $stocktake->closedBy->name }} @endif
            @endif
        </p>
    @endif

    <div class="section-title">{{ __('stocktake.lines') }}</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">{{ __('reports.row_no') }}</th>
                <th style="width: 9%;">{{ __('items.code') }}</th>
                <th style="width: 25%;">{{ __('items.name') }}</th>
                <th style="width: 10%;">{{ __('items.version_label') }}</th>
                <th style="width: 12%;">{{ __('items.location') }}</th>
                <th style="width: 12%;">{{ __('stocktake.system_qty') }}</th>
                <th style="width: 12%;">{{ __('stocktake.counted_qty') }}</th>
                <th style="width: 15%;">{{ __('stocktake.difference') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $rowNo = 0; @endphp
            @foreach ($stocktake->lines as $line)
                @php $difference = $line->difference(); @endphp
                <tr>
                    <td class="num">{{ $digits(++$rowNo) }}</td>
                    <td class="ltr">{{ $line->itemVersion?->item?->code }}</td>
                    <td>{{ $line->itemVersion?->item?->name }}</td>
                    <td>{{ $line->itemVersion?->version_code }}</td>
                    <td class="ltr">{{ $line->itemVersion?->location ?: '—' }}</td>
                    <td class="num">{{ $qty($line->system_quantity) }}</td>
                    <td class="num">
                        {{ $line->isCounted() ? $qty($line->counted_quantity) : __('stocktake.not_counted') }}
                    </td>
                    <td class="num">
                        @if ($difference === null)
                            <span class="muted">—</span>
                        @elseif (abs($difference) < 0.0001)
                            <span class="success">✓</span>
                        @else
                            <span class="danger">
                                {{ $difference > 0 ? __('stocktake.surplus') : __('stocktake.shortage') }}
                                {{ $qty(abs($difference)) }}
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
