import { usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle,
    Info,
    Loader2,
    X,
    XCircle,
} from 'lucide-react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';
import type { ReactNode } from 'react';
import echo from '@/echo';
import { statusGradientColors } from '@/lib/server-status';
import { serverStatusLabel } from '@/lib/utils';
import type { ServerStatus } from '@/types/game';

type ToastVariant = 'success' | 'error' | 'info' | 'warning';

type Toast = {
    id: number;
    message: string;
    variant: ToastVariant;
    visible: boolean;
};

type ServerToast = {
    id: number;
    name: string;
    status: string;
    visible: boolean;
    dismissTimer: ReturnType<typeof setTimeout> | null;
};

type ActiveServer = {
    id: number;
    name: string;
    status: string;
};

type ToastContextValue = {
    addToast: (
        message: string,
        variant?: ToastVariant,
        duration?: number,
    ) => void;
};

const ToastContext = createContext<ToastContextValue | null>(null);

export function useToast(): ToastContextValue {
    const context = useContext(ToastContext);
    if (!context) {
        throw new Error('useToast must be used within a ToastProvider');
    }
    return context;
}

const variantStyles: Record<ToastVariant, string> = {
    success:
        'border-emerald-500/30 bg-white/95 text-emerald-800 dark:border-emerald-500/20 dark:bg-zinc-800/95 dark:text-emerald-200',
    error: 'border-red-500/30 bg-white/95 text-red-800 dark:border-red-500/20 dark:bg-zinc-800/95 dark:text-red-200',
    info: 'border-blue-500/30 bg-white/95 text-blue-800 dark:border-blue-500/20 dark:bg-zinc-800/95 dark:text-blue-200',
    warning:
        'border-amber-500/30 bg-white/95 text-amber-800 dark:border-amber-500/20 dark:bg-zinc-800/95 dark:text-amber-200',
};

const VariantIcon = ({ variant }: { variant: ToastVariant }) => {
    switch (variant) {
        case 'success':
            return <CheckCircle className="size-5 shrink-0 text-emerald-500" />;
        case 'error':
            return <XCircle className="size-5 shrink-0 text-red-500" />;
        case 'info':
            return <Info className="size-5 shrink-0 text-blue-500" />;
        case 'warning':
            return <AlertTriangle className="size-5 shrink-0 text-amber-500" />;
    }
};

const statusSpinnerColors: Record<string, string> = {
    starting: 'text-amber-500',
    booting: 'text-blue-500',
    downloading_mods: 'text-purple-500',
    stopping: 'text-red-500',
};

const statusTextColors: Record<string, string> = {
    starting: 'text-amber-800 dark:text-amber-200',
    booting: 'text-blue-800 dark:text-blue-200',
    downloading_mods: 'text-purple-800 dark:text-purple-200',
    running: 'text-emerald-800 dark:text-emerald-200',
    stopping: 'text-red-800 dark:text-red-200',
    crashed: 'text-red-800 dark:text-red-200',
};

const statusSubTextColors: Record<string, string> = {
    starting: 'text-amber-600 dark:text-amber-400',
    booting: 'text-blue-600 dark:text-blue-400',
    downloading_mods: 'text-purple-600 dark:text-purple-400',
    running: 'text-emerald-600 dark:text-emerald-400',
    stopping: 'text-red-600 dark:text-red-400',
    crashed: 'text-red-600 dark:text-red-400',
};

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<Toast[]>([]);
    const [serverToasts, setServerToasts] = useState<ServerToast[]>([]);
    const serverToastsRef = useRef<ServerToast[]>([]);
    const idCounter = useRef(0);
    const page = usePage<{
        flash: {
            success?: string;
            error?: string;
            info?: string;
            warning?: string;
        };
        activeServers: ActiveServer[];
    }>();

    // Keep ref in sync
    useEffect(() => {
        serverToastsRef.current = serverToasts;
    }, [serverToasts]);

    const removeToast = useCallback((id: number) => {
        setToasts((prev) =>
            prev.map((t) => (t.id === id ? { ...t, visible: false } : t)),
        );
        setTimeout(() => {
            setToasts((prev) => prev.filter((t) => t.id !== id));
        }, 300);
    }, []);

    const addToast = useCallback(
        (
            message: string,
            variant: ToastVariant = 'success',
            duration = 4000,
        ) => {
            const id = ++idCounter.current;
            setToasts((prev) => [
                ...prev,
                { id, message, variant, visible: false },
            ]);

            // Animate in
            requestAnimationFrame(() => {
                setToasts((prev) =>
                    prev.map((t) =>
                        t.id === id ? { ...t, visible: true } : t,
                    ),
                );
            });

            if (duration > 0) {
                setTimeout(() => removeToast(id), duration);
            }
        },
        [removeToast],
    );

    const updateServerStatus = useCallback(
        ({ id, name, status }: ActiveServer) => {
            setServerToasts((prev) => {
                const existing = prev.find((t) => t.id === id);

                // Stopped — dismiss
                if (status === 'stopped') {
                    if (existing) {
                        if (existing.dismissTimer)
                            clearTimeout(existing.dismissTimer);
                        const updated = prev.map((t) =>
                            t.id === id
                                ? { ...t, visible: false, dismissTimer: null }
                                : t,
                        );
                        setTimeout(() => {
                            setServerToasts((p) =>
                                p.filter((t) => t.id !== id),
                            );
                        }, 500);
                        return updated;
                    }
                    return prev;
                }

                // New server toast
                if (!existing) {
                    const toast: ServerToast = {
                        id,
                        name,
                        status,
                        visible: false,
                        dismissTimer: null,
                    };
                    const newList = [...prev, toast];

                    requestAnimationFrame(() => {
                        setServerToasts((p) =>
                            p.map((t) =>
                                t.id === id ? { ...t, visible: true } : t,
                            ),
                        );
                    });

                    // Auto-dismiss running after 4s
                    if (status === 'running') {
                        const timer = setTimeout(() => {
                            setServerToasts((p) =>
                                p.map((t) =>
                                    t.id === id ? { ...t, visible: false } : t,
                                ),
                            );
                            setTimeout(() => {
                                setServerToasts((p) =>
                                    p.filter((t) => t.id !== id),
                                );
                            }, 500);
                        }, 4000);
                        toast.dismissTimer = timer;
                    }

                    return newList;
                }

                // Existing — update status
                if (existing.dismissTimer) clearTimeout(existing.dismissTimer);
                let dismissTimer: ReturnType<typeof setTimeout> | null = null;

                if (status === 'running') {
                    dismissTimer = setTimeout(() => {
                        setServerToasts((p) =>
                            p.map((t) =>
                                t.id === id ? { ...t, visible: false } : t,
                            ),
                        );
                        setTimeout(() => {
                            setServerToasts((p) =>
                                p.filter((t) => t.id !== id),
                            );
                        }, 500);
                    }, 4000);
                }

                return prev.map((t) =>
                    t.id === id ? { ...t, status, dismissTimer } : t,
                );
            });
        },
        [],
    );

    // Flash message consumption
    useEffect(() => {
        const flash = page.props.flash;
        if (flash?.success) addToast(flash.success, 'success');
        if (flash?.error) addToast(flash.error, 'error');
        if (flash?.info) addToast(flash.info, 'info');
        if (flash?.warning) addToast(flash.warning, 'warning');
    }, [page.props.flash, addToast]);

    // Seed active servers on initial mount
    const seededRef = useRef(false);
    useEffect(() => {
        if (seededRef.current) return;
        seededRef.current = true;

        const activeServers = page.props.activeServers;
        if (activeServers?.length) {
            activeServers.forEach((s) => updateServerStatus(s));
        }
    }, [page.props.activeServers, updateServerStatus]);

    // Echo subscription for server status changes
    useEffect(() => {
        const channel = echo
            .private('servers')
            .listen(
                'ServerStatusChanged',
                (e: {
                    serverId: number;
                    serverName: string;
                    status: string;
                }) => {
                    updateServerStatus({
                        id: e.serverId,
                        name: e.serverName,
                        status: e.status,
                    });
                },
            );

        return () => {
            channel.stopListening('ServerStatusChanged');
            echo.leave('private-servers');
        };
    }, [updateServerStatus]);

    return (
        <ToastContext.Provider value={{ addToast }}>
            {children}

            {/* Toast container */}
            <div className="pointer-events-none fixed right-4 bottom-4 z-50 flex flex-col items-end gap-2">
                {/* Ephemeral toasts */}
                {toasts.map((toast) => (
                    <div
                        key={toast.id}
                        className={`pointer-events-auto flex max-w-md min-w-72 items-center gap-3 rounded-lg border px-4 py-3 shadow-lg backdrop-blur-sm transition-all duration-300 ${
                            toast.visible
                                ? 'translate-y-0 opacity-100'
                                : 'translate-y-2 opacity-0'
                        } ${variantStyles[toast.variant]}`}
                    >
                        <VariantIcon variant={toast.variant} />
                        <span className="text-sm">{toast.message}</span>
                        <button
                            onClick={() => removeToast(toast.id)}
                            className="ml-auto shrink-0 rounded p-0.5 opacity-40 transition-opacity hover:opacity-100"
                        >
                            <X className="size-4" />
                        </button>
                    </div>
                ))}

                {/* Server status toasts */}
                {serverToasts.map((st) => (
                    <div
                        key={`server-${st.id}`}
                        className={`pointer-events-auto relative max-w-md min-w-72 overflow-hidden rounded-lg border border-zinc-200 shadow-lg transition-all duration-500 dark:border-zinc-700 ${
                            st.visible
                                ? 'translate-y-0 opacity-100'
                                : 'translate-y-2 opacity-0'
                        }`}
                    >
                        {/* Gradient overlays — cross-fade on status change */}
                        {Object.entries(statusGradientColors).map(
                            ([status, gradient]) => (
                                <div
                                    key={status}
                                    className={`absolute inset-0 bg-gradient-to-r transition-opacity duration-700 ${gradient} ${
                                        st.status === status
                                            ? 'opacity-100'
                                            : 'opacity-0'
                                    }`}
                                />
                            ),
                        )}

                        <div className="relative flex items-center gap-3 bg-white/80 px-4 py-3 dark:bg-zinc-800/80">
                            {st.status === 'running' ? (
                                <CheckCircle className="size-5 shrink-0 text-emerald-500" />
                            ) : st.status === 'crashed' ? (
                                <XCircle className="size-5 shrink-0 text-red-500" />
                            ) : (
                                <Loader2
                                    className={`size-5 shrink-0 animate-spin transition-colors duration-700 ${statusSpinnerColors[st.status] ?? 'text-zinc-500'}`}
                                />
                            )}
                            <div>
                                <span
                                    className={`text-sm font-medium transition-colors duration-700 ${statusTextColors[st.status] ?? ''}`}
                                >
                                    {st.name}
                                </span>
                                <span
                                    className={`ml-1 text-xs transition-colors duration-700 ${statusSubTextColors[st.status] ?? ''}`}
                                >
                                    {serverStatusLabel(
                                        st.status as ServerStatus,
                                    )}
                                </span>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}
