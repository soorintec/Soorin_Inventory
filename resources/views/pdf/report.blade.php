{{-- خروجی PDF گزارش انبار — جهت بر اساس زبان فعال، فونت وزیرمتن، افقی. --}}
@php $dir = config('locales.available.' . app()->getLocale() . '.dir', 'rtl'); @endphp
<!DOCTYPE html>
<html dir="{{ $dir }}" lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: vazirmatn; font-size: 10pt; color: #0b2b3f; direction: {{ $dir }}; }
    table { width: 100%; border-collapse: collapse; }
    .company-name { font-size: 13pt; font-weight: bold; color: #0f2d4d; }
    .report-title { font-size: 14pt; font-weight: bold; color: #0f766e; text-align: left; }
    .period { font-size: 9.5pt; color: #5f7d8c; text-align: left; }
    .divider { border-top: 2px solid #0f2d4d; margin: 6px 0 10px; }
    .section-title { font-size: 11.5pt; font-weight: bold; color: #0f2d4d; margin-top: 16px; margin-bottom: 6px; }
    .data-table th { background: #0f2d4d; color: #fff; padding: 5px 8px; font-size: 9pt; text-align: right; }
    .data-table td { padding: 5px 8px; font-size: 9pt; border-bottom: 1px solid #dde8ec; }
    .data-table .num { text-align: left; direction: ltr; }
    .footer-note { margin-top: 20px; font-size: 8pt; color: #5f7d8c; text-align: center; }
</style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 55%;">
                <table><tr>
                    @if (! empty($logo ?? null))
                        <td style="width: 52px; vertical-align: middle;">
                            <img src="{{ $logo }}" alt="" style="height: 42px; width: auto;">
                        </td>
                    @endif
                    <td style="vertical-align: middle;"><div class="company-name">{{ $company['name'] }}</div></td>
                </tr></table>
            </td>
            <td style="width: 45%;">
                <div class="report-title">{{ __('reports.pdf_title') }}</div>
                <div class="period">{{ __('reports.period', ['from' => $date($report['from']), 'to' => $date($report['to'])]) }}</div>
            </td>
        </tr>
    </table>
    <div class="divider"></div>

    <div class="section-title">{{ __('reports.by_user') }}</div>
    <table class="data-table">
        <thead><tr>
            <th>{{ __('reports.col_user') }}</th>
            <th>{{ __('reports.col_in_count') }}</th>
            <th>{{ __('reports.col_out_count') }}</th>
            <th>{{ __('reports.col_in_qty') }}</th>
            <th>{{ __('reports.col_out_qty') }}</th>
        </tr></thead>
        <tbody>
        @forelse ($report['by_user'] as $row)
            <tr>
                <td>{{ $row['user'] }}</td>
                <td class="num">{{ $digits($row['in_count']) }}</td>
                <td class="num">{{ $digits($row['out_count']) }}</td>
                <td class="num">{{ $digits($row['in_qty']) }}</td>
                <td class="num">{{ $digits($row['out_qty']) }}</td>
            </tr>
        @empty
            <tr><td colspan="5">{{ __('reports.empty') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="section-title">{{ __('reports.system_costs') }}</div>
    <table class="data-table">
        <thead><tr>
            <th>{{ __('reports.col_code') }}</th>
            <th>{{ __('reports.col_system') }}</th>
            <th>{{ __('reports.col_customer') }}</th>
            <th>{{ __('reports.col_cost') }}</th>
        </tr></thead>
        <tbody>
        @forelse ($report['system_costs'] as $row)
            <tr>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['customer'] }}</td>
                <td class="num">{{ $money($row['cost']) }}</td>
            </tr>
        @empty
            <tr><td colspan="4">{{ __('reports.empty') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="section-title">{{ __('reports.stock_levels') }}</div>
    <table class="data-table">
        <thead><tr>
            <th>{{ __('reports.col_item') }}</th>
            <th>{{ __('reports.col_version') }}</th>
            <th>{{ __('reports.col_qty') }}</th>
            <th>{{ __('reports.col_value') }}</th>
        </tr></thead>
        <tbody>
        @forelse ($report['stock_levels'] as $row)
            <tr>
                <td>{{ $row['item'] }}</td>
                <td>{{ $row['version'] }}</td>
                <td class="num">{{ $digits($row['qty']) }}</td>
                <td class="num">{{ $row['value_label'] ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">{{ __('reports.empty') }}</td></tr>
        @endforelse
        </tbody>
        @if (! empty($report['stock_value']))
            <tfoot>
            @foreach ($report['stock_value'] as $total)
                <tr>
                    <td colspan="3"><strong>{{ __('reports.stock_value_total', ['currency' => $total['label']]) }}</strong></td>
                    <td class="num"><strong>{{ $digits($total['value']) }} {{ $total['label'] }}</strong></td>
                </tr>
            @endforeach
            </tfoot>
        @endif
    </table>

    <div class="footer-note">
        {{ $company['name'] }}@if (! empty($company['website_label'])) · {{ $company['website_label'] }}@endif
        @if (! empty($company['phone'])) · {{ __('common.phone') }}: {{ $company['phone'] }}@endif
        @if (! empty($company['address']))<br>{{ $company['address'] }}@endif
    </div>
</body>
</html>
