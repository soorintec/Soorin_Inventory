{{-- گزارشِ کالاهای نیازمندِ سفارش (موجودی زیرِ حد هشدار) --}}
@extends('pdf.layout')

@section('meta')
    {{ __('reports.total_items') }}: {{ $qty($totalItems) }}
@endsection

@section('content')
    @if ($groups->isEmpty())
        <div class="summary-box">{{ __('reports.reorder_empty') }}</div>
    @else
        @php $rowNo = 0; @endphp
        @foreach ($groups as $categoryName => $rows)
            <div class="section-title">{{ $categoryName }}</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">{{ __('reports.row_no') }}</th>
                        <th style="width: 10%;">{{ __('items.code') }}</th>
                        <th style="width: 30%;">{{ __('items.name') }}</th>
                        <th style="width: 13%;">{{ __('items.version_label') }}</th>
                        <th style="width: 14%;">{{ __('reports.col_current_stock') }}</th>
                        <th style="width: 13%;">{{ __('reports.col_min_stock') }}</th>
                        <th style="width: 15%;">{{ __('reports.col_suggested') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="num">{{ $digits(++$rowNo) }}</td>
                            <td class="ltr">{{ $row['code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['version'] }}</td>
                            <td class="num danger">{{ $qty($row['current']) }} {{ $row['unit'] }}</td>
                            <td class="num">{{ $qty($row['min']) }}</td>
                            <td class="num success">{{ $qty($row['suggested']) }} {{ $row['unit'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif
@endsection
