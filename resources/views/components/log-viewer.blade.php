@props([
    'channel',
    'event',
    'maxHeight' => 'max-h-64',
    'initialLines' => [],
    'trackProgress' => false,
    'tag' => 'div',
    'wireLoadMethod' => null,
])

<{{ $tag }}
    x-data="{
        @if($trackProgress)
        progress: 0,
        @endif
        lines: @js($initialLines),
        channel: null,
        maxLines: 200,
        init() {
            @if($wireLoadMethod)
            $wire.{{ $wireLoadMethod }}.then(initialLines => {
                this.lines = initialLines;
                this.$nextTick(() => this.scrollToBottom());
            });
            @endif
            this.channel = window.Echo.channel('{{ $channel }}');
            this.channel.listen('{{ $event }}', (event) => {
                @if($trackProgress)
                this.progress = event.progressPct;
                @endif
                this.lines.push(event.line);
                if (this.lines.length > this.maxLines) {
                    this.lines = this.lines.slice(-this.maxLines);
                }
                this.$nextTick(() => this.scrollToBottom());
            });
        },
        scrollToBottom() {
            if (this.$refs.logContainer) {
                this.$refs.logContainer.scrollTop = this.$refs.logContainer.scrollHeight;
            }
        },
        destroy() {
            if (this.channel) {
                window.Echo.leave('{{ $channel }}');
            }
        },
    }"
    {{ $attributes }}
>
    {{ $slot }}

    @if (isset($log))
        {{ $log }}
    @else
        <div class="rounded bg-zinc-900 text-zinc-100 p-3 font-mono text-xs {{ $maxHeight }} overflow-y-auto" x-ref="logContainer">
            <template x-if="lines.length === 0">
                <div class="text-zinc-500">{{ __('Waiting for output...') }}</div>
            </template>
            <template x-for="(line, index) in lines" :key="index">
                <div class="whitespace-pre-wrap break-all" x-text="line"></div>
            </template>
        </div>
    @endif
</{{ $tag }}>
