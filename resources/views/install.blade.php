@php $dir = config('locales.available.' . app()->getLocale() . '.dir', 'rtl'); @endphp
<!DOCTYPE html>
<html dir="{{ $dir }}" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('install.title') }}</title>
    <style>
        @font-face { font-family: Vazirmatn; src: url('/fonts/vazirmatn/Vazirmatn-400.woff2') format('woff2'); font-weight: 400; font-display: swap; }
        @font-face { font-family: Vazirmatn; src: url('/fonts/vazirmatn/Vazirmatn-500.woff2') format('woff2'); font-weight: 500; font-display: swap; }
        @font-face { font-family: Vazirmatn; src: url('/fonts/vazirmatn/Vazirmatn-700.woff2') format('woff2'); font-weight: 700; font-display: swap; }

        * { box-sizing: border-box; }
        body {
            font-family: Vazirmatn, system-ui, sans-serif;
            background: #eef4f6; color: #0b2b3f; margin: 0;
            min-height: 100vh; display: flex; align-items: flex-start; justify-content: center;
            padding: 28px 16px;
        }
        .card {
            background: #fff; width: 100%; max-width: 640px; border-radius: 16px;
            box-shadow: 0 6px 30px rgba(11,43,63,.12); overflow: hidden;
        }
        .head { background: #0f2d4d; color: #fff; padding: 22px 28px; }
        .head h1 { margin: 0 0 4px; font-size: 18px; }
        .head p { margin: 0; font-size: 12.5px; color: #93b4c9; }
        .body { padding: 24px 28px 28px; }
        .section-title { font-size: 14px; font-weight: 700; color: #0f766e; margin: 18px 0 10px; }
        .section-title:first-child { margin-top: 0; }
        .field { margin-bottom: 14px; }
        label { display: block; font-size: 12.5px; margin-bottom: 5px; font-weight: 500; }
        .hint { font-size: 11px; color: #5f7d8c; margin-top: 4px; }
        input {
            width: 100%; font-family: inherit; font-size: 14px; padding: 10px 12px;
            border: 1px solid #dde8ec; border-radius: 9px; background: #fbfdfe; color: #0b2b3f;
        }
        input:focus { outline: 2px solid #14b8a6; outline-offset: 1px; border-color: #14b8a6; }
        .row { display: flex; gap: 12px; }
        .row > .field { flex: 1; }
        .btn {
            width: 100%; margin-top: 18px; font-family: inherit; font-size: 15px; font-weight: 700;
            padding: 12px; border: 0; border-radius: 10px; background: #0f766e; color: #fff; cursor: pointer;
        }
        .btn:hover { background: #0d655e; }
        .error {
            background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca;
            padding: 10px 14px; border-radius: 9px; font-size: 12.5px; margin-bottom: 16px; line-height: 1.7;
        }
        .ltr { direction: ltr; text-align: left; }
        .note { font-size: 11.5px; color: #5f7d8c; margin-top: 16px; line-height: 1.8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <h1>{{ __('install.heading') }} — {{ config('branding.company.name') }}</h1>
            <p>{{ __('install.subheading') }}</p>
            <form method="POST" action="{{ route('locale.save') }}" style="margin-top:10px;">
                @csrf
                <select name="locale" onchange="this.form.submit()" aria-label="Language"
                    style="font-family:inherit; font-size:13px; font-weight:600; padding:6px 10px; border-radius:8px; border:1px solid #1c3b5a; background:#0b2540; color:#cfe0ea; cursor:pointer;">
                    @foreach (config('locales.available', []) as $code => $meta)
                        <option value="{{ $code }}" @selected($code === app()->getLocale())>{{ $meta['name'] }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="body">
            @if (session('installError'))
                <div class="error">{{ session('installError') }}</div>
            @endif

            @if ($errors->any())
                <div class="error">
                    @foreach ($errors->all() as $e) <div>• {{ $e }}</div> @endforeach
                </div>
            @endif

            <form method="POST" action="/install">
                @csrf

                <div class="section-title">{{ __('install.db_section') }}</div>
                <div class="hint" style="margin-bottom:12px;">
                    {{ __('install.db_hint') }}
                </div>

                <div class="row">
                    <div class="field">
                        <label>{{ __('install.db_host') }}</label>
                        <input class="ltr" name="db_host" value="{{ old('db_host', '127.0.0.1') }}" required>
                    </div>
                    <div class="field" style="max-width:130px;">
                        <label>{{ __('install.db_port') }}</label>
                        <input class="ltr" name="db_port" value="{{ old('db_port', '3306') }}" required>
                    </div>
                </div>

                <div class="field">
                    <label>{{ __('install.db_name') }}</label>
                    <input class="ltr" name="db_database" value="{{ old('db_database') }}" required>
                </div>

                <div class="row">
                    <div class="field">
                        <label>{{ __('install.db_user') }}</label>
                        <input class="ltr" name="db_username" value="{{ old('db_username') }}" required>
                    </div>
                    <div class="field">
                        <label>{{ __('install.db_pass') }}</label>
                        <input class="ltr" name="db_password" type="text" value="{{ old('db_password') }}" autocomplete="off">
                    </div>
                </div>

                <div class="field">
                    <label>{{ __('install.app_url') }}</label>
                    <input class="ltr" name="app_url" value="{{ old('app_url', $guessedUrl) }}">
                    <div class="hint">{{ __('install.app_url_hint') }}</div>
                </div>

                <div class="section-title">{{ __('install.admin_section') }}</div>

                <div class="field">
                    <label>{{ __('install.admin_name') }}</label>
                    <input name="admin_name" value="{{ old('admin_name', __('install.admin_name_default')) }}" required>
                </div>

                <div class="row">
                    <div class="field">
                        <label>{{ __('install.admin_email') }}</label>
                        <input class="ltr" name="admin_email" type="email" value="{{ old('admin_email') }}" required>
                    </div>
                    <div class="field">
                        <label>{{ __('install.admin_password') }}</label>
                        <input class="ltr" name="admin_password" type="text" required>
                    </div>
                </div>

                <button class="btn" type="submit">{{ __('install.submit') }}</button>

                <div class="note">
                    {{ __('install.note') }}
                </div>
            </form>
        </div>
    </div>
</body>
</html>
