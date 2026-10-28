<x-dashboard-tile :position="$position" :refresh-interval="$refresh_interval_in_seconds">
    <div class="grid grid-rows-auto-1 gap-3 h-full">
        <div class="grid grid-cols-[3.5rem_1fr_6rem_3rem] items-center gap-3 px-3">
            <div class="col-span-2 truncate font-medium text-dimmed text-sm uppercase tracking-wide">
                {{ $station_name }} departures
            </div>
            <div class="text-xs font-medium uppercase tracking-wide text-dimmed">Status</div>
            <div class="text-right text-xs font-medium uppercase tracking-wide text-dimmed">Track</div>
        </div>

        <div class="flex min-h-0 flex-col gap-2 overflow-hidden">
            @if($disruptions_are_stale)
                <div class="rounded-xl border border-amber-400/20 bg-amber-400/10 px-3 py-2 text-sm text-amber-300">
                    {{ $disruptions_stale_message }}
                </div>
            @endif

            @foreach($disruptions as $disruption)
                <div class="rounded-xl border border-amber-400/20 bg-amber-400/10 px-3 py-2.5">
                    <div class="truncate text-base font-medium text-amber-300">{{ $disruption['title'] }}</div>
                    @if($disruption['detail'] !== '')
                        <div class="mt-0.5 line-clamp-2 text-sm leading-snug text-dimmed">{{ $disruption['detail'] }}</div>
                    @endif
                </div>
            @endforeach

            @if($additional_disruptions_count > 0)
                <div class="px-3 text-sm text-dimmed">
                    {{ $additional_disruptions_count }} more active disruption{{ $additional_disruptions_count === 1 ? '' : 's' }}
                </div>
            @endif

            @if($departures_are_stale)
                <div class="rounded-xl border border-amber-400/20 bg-amber-400/10 px-3 py-2 text-sm text-amber-300">
                    {{ $departures_stale_message }}
                </div>
            @endif

            @if(empty($departures))
                <div class="flex grow items-center justify-center text-dimmed text-sm">
                    No departures
                </div>
            @else
                @foreach($departures as $departure)
                    <div @class([
                        'grid grid-cols-[3.5rem_1fr_6rem_3rem] items-center gap-3 rounded-xl border border-white/5 bg-white/[0.04] px-3 py-2.5 text-base',
                        'opacity-60' => $departure['status_code'] === 'cancelled',
                    ])>
                        <div @class([
                            'font-semibold tabular-nums text-default',
                            'line-through' => $departure['status_code'] === 'cancelled',
                        ])>
                            {{ $departure['time'] }}
                        </div>

                        <div class="flex min-w-0 items-baseline gap-2">
                            <span class="truncate font-medium text-default">{{ $departure['destination'] }}</span>
                            <span class="min-w-0 shrink-[999] truncate text-sm text-dimmed">{{ $departure['service'] }}</span>
                        </div>

                        <div @class([
                            'flex items-center gap-1.5 truncate text-sm font-semibold tabular-nums',
                            'text-emerald-400' => $departure['status_code'] === 'on_time',
                            'text-amber-400' => $departure['status_code'] === 'delayed',
                            'text-rose-400' => $departure['status_code'] === 'cancelled',
                        ])>
                            <span class="size-2 shrink-0 rounded-full bg-current"></span>
                            {{ $departure['status_code'] === 'delayed' ? $departure['detail'] : $departure['status'] }}
                        </div>

                        <div class="text-right font-semibold tabular-nums text-default">{{ $departure['track'] }}</div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</x-dashboard-tile>
