<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('dashboard.recent_logins')"
        icon="heroicon-o-arrow-right-on-rectangle"
    >
        @php
            $users = $this->getUsers();
            $never = $this->neverLoggedIn();
        @endphp

        @if ($users->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('dashboard.no_logins') }}</p>
        @else
            <div class="calc-parts-scroll overflow-x-auto">
                <table class="dash-table w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="p-2 text-start font-medium">{{ __('users.name') }}</th>
                            <th class="p-2 text-start font-medium">{{ __('auth.last_login_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="p-2">
                                    {{ $user->name }}
                                    <span class="text-xs text-gray-500">
                                        ({{ __('auth.types.' . $user->user_type) }})
                                    </span>
                                    @unless ($user->is_active)
                                        <x-filament::badge color="danger" class="ms-1">
                                            {{ __('dashboard.inactive') }}
                                        </x-filament::badge>
                                    @endunless
                                </td>
                                <td class="whitespace-nowrap p-2 text-gray-600 dark:text-gray-400">
                                    {{ \App\Support\Jalali::formatDateTime($user->last_login_at) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($never > 0)
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('dashboard.never_logged_in', ['count' => \App\Support\Jalali::quantity($never)]) }}
                </p>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
