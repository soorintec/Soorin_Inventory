{{-- پرینت کل موجودی انبار --}}
@extends('pdf.layout')

@section('meta')
    {{ __('reports.warehouse') }}: {{ $warehouseName }}
@endsection

@section('content')
    <div class="summary-box">
        {{ __('reports.total_items') }}: {{ $qty($totalItems) }}
        &nbsp;|&nbsp;
        {{ __('reports.total_versions') }}: {{ $qty($totalVersions) }}
        &nbsp;|&nbsp;
        {{ __('reports.total_quantity') }}: {{ $qty($totalQuantity) }}
    </div>

    @php $rowNo = 0; @endphp
    @foreach ($groups as $categoryName => $rows)
        <div class="section-title">{{ $categoryName }}</div>
        <table class="data-table">
            <thead>
                <tr>
                    {{-- ستون ردیف در سمت راست (کنار کد کالا)، شماره‌گذاری پیوسته --}}
                    <th style="width: 5%;">{{ __('reports.row_no') }}</th>
                    <th style="width: 9%;">{{ __('items.code') }}</th>
                    <th style="width: 27%;">{{ __('items.name') }}</th>
                    <th style="width: 12%;">{{ __('items.version_label') }}</th>
                    <th style="width: 13%;">{{ __('items.location') }}</th>
                    <th style="width: 12%;">{{ __('reports.warehouse') }}</th>
                    <th style="width: 10%;">{{ __('stock.quantity') }}</th>
                    <th style="width: 12%;">{{ __('items.fx_price') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="num">{{ $digits(++$rowNo) }}</td>
                        <td class="ltr">{{ $row['code'] }}</td>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['version'] }}</td>
                        <td class="ltr">{{ $row['location'] }}</td>
                        <td>{{ $row['warehouse'] }}</td>
                        <td class="num">{{ $qty($row['quantity']) }} {{ $row['unit'] }}</td>
                        <td class="ltr">{{ $row['fx'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
@endsection
