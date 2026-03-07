@php
    use App\Enums\ServerStatus;
    use App\Models\Server;

    $activeServers = Server::query()
        ->whereIn('status', [ServerStatus::Starting, ServerStatus::Booting, ServerStatus::Stopping])
        ->get(['id', 'name', 'status'])
        ->map(fn (Server $s): array => [
            'id' => $s->id,
            'name' => $s->name,
            'status' => $s->status->value,
        ])
        ->values()
        ->toArray();
@endphp

<div
    x-data="{
        toasts: [],
        serverToasts: [],
        listeners: [],

        init() {
            this.listeners.push(
                Livewire.on('toast', (data) => { this.addToast(data); })
            );

            {{-- Subscribe to Echo for real-time server status updates --}}
            window.Echo.channel('servers')
                .listen('ServerStatusChanged', (e) => {
                    this.updateServerStatus({ id: e.serverId, name: e.serverName, status: e.status });
                });

            {{-- Seed with any currently active servers --}}
            @foreach ($activeServers as $server)
                this.updateServerStatus(@js($server));
            @endforeach
        },

        destroy() {
            this.listeners.forEach((cleanup) => cleanup());
            this.serverToasts.forEach(t => { if (t.dismissTimer) clearTimeout(t.dismissTimer); });
            window.Echo.leave('servers');
        },

        {{-- Ephemeral toast methods --}}
        addToast({ message, variant = 'success', duration = 4000 }) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, variant, visible: false });
            this.$nextTick(() => {
                const toast = this.toasts.find(t => t.id === id);
                if (toast) toast.visible = true;
            });
            if (duration > 0) {
                setTimeout(() => this.removeToast(id), duration);
            }
        },

        removeToast(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (toast) {
                toast.visible = false;
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 300);
            }
        },

        {{-- Server status toast methods --}}
        updateServerStatus({ id, name, status }) {
            let toast = this.serverToasts.find(t => t.id === id);

            {{-- Server stopped — dismiss --}}
            if (status === 'stopped') {
                if (toast) {
                    if (toast.dismissTimer) clearTimeout(toast.dismissTimer);
                    toast.visible = false;
                    setTimeout(() => {
                        this.serverToasts = this.serverToasts.filter(t => t.id !== id);
                    }, 500);
                }
                return;
            }

            {{-- New server toast — slide in --}}
            if (!toast) {
                toast = { id, name, status, visible: false, dismissTimer: null };
                this.serverToasts.push(toast);
                this.$nextTick(() => {
                    const t = this.serverToasts.find(t => t.id === id);
                    if (t) t.visible = true;
                });
            } else {
                {{-- Existing toast — update status (gradient cross-fades via CSS) --}}
                if (toast.dismissTimer) clearTimeout(toast.dismissTimer);
                toast.dismissTimer = null;
                toast.status = status;
            }

            {{-- Running — show green briefly then auto-dismiss --}}
            if (status === 'running') {
                const t = this.serverToasts.find(t => t.id === id);
                if (t) {
                    t.dismissTimer = setTimeout(() => {
                        t.visible = false;
                        setTimeout(() => {
                            this.serverToasts = this.serverToasts.filter(st => st.id !== id);
                        }, 500);
                    }, 4000);
                }
            }
        }
    }"
    class="fixed bottom-4 right-4 z-50 flex flex-col items-end gap-2 pointer-events-none"
>
    {{-- Ephemeral toasts --}}
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="toast.visible"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="pointer-events-auto flex items-center gap-3 rounded-lg border px-4 py-3 shadow-lg min-w-72 max-w-md backdrop-blur-sm"
            :class="{
                'border-emerald-500/30 bg-white/95 text-emerald-800 dark:border-emerald-500/20 dark:bg-zinc-800/95 dark:text-emerald-200': toast.variant === 'success',
                'border-red-500/30 bg-white/95 text-red-800 dark:border-red-500/20 dark:bg-zinc-800/95 dark:text-red-200': toast.variant === 'danger',
                'border-blue-500/30 bg-white/95 text-blue-800 dark:border-blue-500/20 dark:bg-zinc-800/95 dark:text-blue-200': toast.variant === 'info',
                'border-amber-500/30 bg-white/95 text-amber-800 dark:border-amber-500/20 dark:bg-zinc-800/95 dark:text-amber-200': toast.variant === 'warning',
            }"
        >
            {{-- Success icon --}}
            <svg x-cloak x-show="toast.variant === 'success'" class="size-5 shrink-0 text-emerald-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
            </svg>
            {{-- Danger icon --}}
            <svg x-cloak x-show="toast.variant === 'danger'" class="size-5 shrink-0 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd" />
            </svg>
            {{-- Info icon --}}
            <svg x-cloak x-show="toast.variant === 'info'" class="size-5 shrink-0 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd" />
            </svg>
            {{-- Warning icon --}}
            <svg x-cloak x-show="toast.variant === 'warning'" class="size-5 shrink-0 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 5a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
            </svg>

            <span class="text-sm" x-text="toast.message"></span>

            <button @click="removeToast(toast.id)" class="ml-auto shrink-0 rounded p-0.5 opacity-40 transition-opacity hover:opacity-100">
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                </svg>
            </button>
        </div>
    </template>

    {{-- Server status toasts — Alpine-managed with cross-fade gradients --}}
    <template x-for="st in serverToasts" :key="st.id">
        <div
            x-show="st.visible"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-500"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="pointer-events-auto relative overflow-hidden rounded-lg border border-zinc-200 shadow-lg min-w-72 max-w-md dark:border-zinc-700"
        >
            {{-- Status gradient overlays — always present, cross-fade on status change (matches server config dialog) --}}
            <div class="absolute inset-0 bg-gradient-to-r from-amber-400/20 to-zinc-300/5 transition-opacity duration-700 dark:from-amber-500/15 dark:to-zinc-600/5" :class="st.status === 'starting' ? 'opacity-100' : 'opacity-0'"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-blue-400/20 to-zinc-300/5 transition-opacity duration-700 dark:from-blue-500/15 dark:to-zinc-600/5" :class="st.status === 'booting' ? 'opacity-100' : 'opacity-0'"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-emerald-400/20 to-zinc-300/5 transition-opacity duration-700 dark:from-emerald-500/15 dark:to-zinc-600/5" :class="st.status === 'running' ? 'opacity-100' : 'opacity-0'"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-red-400/20 to-zinc-300/5 transition-opacity duration-700 dark:from-red-500/15 dark:to-zinc-600/5" :class="st.status === 'stopping' ? 'opacity-100' : 'opacity-0'"></div>

            <div class="relative flex items-center gap-3 bg-white/80 px-4 py-3 dark:bg-zinc-800/80">
                {{-- Spinner (starting / booting / stopping) --}}
                <svg x-show="st.status !== 'running'" class="size-5 shrink-0 animate-spin transition-colors duration-700"
                     :class="{
                         'text-amber-500': st.status === 'starting',
                         'text-blue-500': st.status === 'booting',
                         'text-red-500': st.status === 'stopping',
                     }"
                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{-- Check icon (running) --}}
                <svg x-cloak x-show="st.status === 'running'" class="size-5 shrink-0 text-emerald-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                </svg>

                <div>
                    <span class="text-sm font-medium transition-colors duration-700"
                          :class="{
                              'text-amber-800 dark:text-amber-200': st.status === 'starting',
                              'text-blue-800 dark:text-blue-200': st.status === 'booting',
                              'text-emerald-800 dark:text-emerald-200': st.status === 'running',
                              'text-red-800 dark:text-red-200': st.status === 'stopping',
                          }"
                          x-text="st.name"></span>
                    <span class="ml-1 text-xs transition-colors duration-700"
                          :class="{
                              'text-amber-600 dark:text-amber-400': st.status === 'starting',
                              'text-blue-600 dark:text-blue-400': st.status === 'booting',
                              'text-emerald-600 dark:text-emerald-400': st.status === 'running',
                              'text-red-600 dark:text-red-400': st.status === 'stopping',
                          }"
                          x-text="st.status === 'running' ? 'Running' : st.status.charAt(0).toUpperCase() + st.status.slice(1) + '...'"></span>
                </div>
            </div>
        </div>
    </template>
</div>
