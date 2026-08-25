{{--
    فهرست شمارش انبارگردانی.

    عمداً ستون «موجودی سامانه» ندارد — اگر شمارنده عدد دفتر را ببیند، ناخودآگاه
    همان را می‌نویسد و انبارگردانی بی‌معنی می‌شود. ستون آخر خالی است تا شمارش
    دستی نوشته شود.
--}}
@extends('pdf.layout')

@section('meta')
    {{ __('stocktake.code') }}: <span class="ltr">{{ $stocktake->code }}</span>
    <br>{{ __('stocktake.warehouse') }}: {{ $stocktake->warehouse->name }}
@endsection

@section('content')
    <div class="summary-box">
        {{ __('stocktake.sheet_hint') }}
        &nbsp;|&nbsp;
        {{ __('stocktake.total_lines') }}: {{ $qty(count($rows)) }}
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">{{ __('reports.row_no') }}</th>
                <th style="width: 9%;">{{ __('items.code') }}</th>
                <th style="width: 24%;">{{ __('items.name') }}</th>
                <th style="width: 13%;">{{ __('items.category_label') }}</th>
                <th style="width: 10%;">{{ __('items.version_label') }}</th>
                <th style="width: 13%;">{{ __('items.location') }}</th>
                <th style="width: 6%;">{{ __('items.unit') }}</th>
                <th style="width: 20%;">{{ __('stocktake.sheet_counted') }}</th>
            </tr>
        </thead>
        <tbody>
            @php $rowNo = 0; @endphp
            @foreach ($rows as $row)
                <tr>
                    <td class="num">{{ $digits(++$rowNo) }}</td>
                    <td class="ltr">{{ $row['code'] }}</td>
                    <td>
                        {{ $row['name'] }}
                        @if ($row['notes'])
                            <br><span class="muted" style="font-size: 7.5pt;">{{ $row['notes'] }}</span>
                        @endif
                    </td>
                    <td>{{ $row['category'] }}</td>
                    <td>{{ $row['version'] }}</td>
                    <td class="ltr">{{ $row['location'] }}</td>
                    <td>{{ $row['unit'] }}</td>
                    <td><div class="write-in"></div></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
