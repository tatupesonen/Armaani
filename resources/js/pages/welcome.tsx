import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRightIcon,
    Box,
    Check,
    DownloadIcon,
    ExternalLink,
    FolderIcon,
    Github,
    LayersIcon,
    Loader2,
    MonitorIcon,
    Pause,
    Play,
    PlayIcon,
    RefreshCw,
    ServerIcon,
    ShieldIcon,
    Sparkle,
    UsersIcon,
} from 'lucide-react';
import type { ComponentType, SVGAttributes } from 'react';
import { useEffect, useRef, useState } from 'react';

import AppLogoColor from '@/components/app-logo-color';
import { Badge } from '@/components/ui/badge';
import { statusGradients } from '@/lib/server-status';
import { dashboard, login } from '@/routes';

const GITHUB_URL = 'https://github.com/tatupesonen/Armaani';

export default function Welcome() {
    const { auth } = usePage().props;
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const handleScroll = () => setScrolled(window.scrollY > 50);
        window.addEventListener('scroll', handleScroll, { passive: true });
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    return (
        <>
            <Head title="Armaani - Game Server Manager" />

            <div className="min-h-screen bg-background text-foreground">
                {/* Header */}
                <header
                    className={`sticky top-0 z-50 transition-colors duration-300 ${
                        scrolled
                            ? 'border-b border-border/50 bg-background/80 text-foreground backdrop-blur-md'
                            : 'border-b border-transparent bg-transparent text-white'
                    }`}
                >
                    <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
                        <div className="flex items-center gap-3">
                            <AppLogoColor className="size-8 drop-shadow-[0_0_12px_rgba(239,68,68,0.15)]" />
                            <span className="text-lg font-semibold tracking-tight">
                                Armaani
                            </span>
                        </div>
                        <nav className="flex items-center gap-3">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className={`inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors ${
                                        scrolled
                                            ? 'bg-foreground text-background hover:bg-foreground/90'
                                            : 'bg-white text-[#0c0c0f] hover:bg-white/90'
                                    }`}
                                >
                                    Dashboard
                                    <ArrowRightIcon className="size-4" />
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className={`rounded-lg px-4 py-2 text-sm font-medium transition-colors ${
                                            scrolled
                                                ? 'hover:bg-accent'
                                                : 'hover:bg-white/10'
                                        }`}
                                    >
                                        Log in
                                    </Link>
                                    <a
                                        href={GITHUB_URL}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className={`inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors ${
                                            scrolled
                                                ? 'bg-foreground text-background hover:bg-foreground/90'
                                                : 'bg-white text-[#0c0c0f] hover:bg-white/90'
                                        }`}
                                    >
                                        <Github className="size-4" />
                                        GitHub
                                    </a>
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero */}
                <section className="relative -mt-16 overflow-hidden bg-[#0c0c0f] pt-16 text-white">
                    {/* Radial gradient backdrop */}
                    <div className="absolute inset-0 bg-[radial-gradient(ellipse_80%_60%_at_50%_50%,rgba(39,39,42,0.5)_0%,transparent_70%),radial-gradient(ellipse_40%_40%_at_75%_25%,rgba(239,68,68,0.06)_0%,transparent_60%),radial-gradient(ellipse_40%_40%_at_25%_75%,rgba(239,68,68,0.04)_0%,transparent_60%)]" />
                    {/* Subtle grid */}
                    <div className="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:60px_60px]" />
                    {/* Red accent line */}
                    <div className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-transparent via-red-500 to-transparent opacity-60" />

                    <div className="relative mx-auto max-w-6xl px-6 py-24 text-center lg:py-36">
                        <div className="mx-auto mb-8 flex size-20 items-center justify-center">
                            <AppLogoColor className="size-20 drop-shadow-[0_0_40px_rgba(239,68,68,0.15)]" />
                        </div>
                        <h1 className="mx-auto max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                            Manage your Arma servers and actually enjoy it
                        </h1>
                        <p className="mx-auto mt-6 max-w-2xl text-lg text-[#a1a1aa]">
                            Install, configure, and control dedicated servers
                            for Arma 3, Arma Reforger, and DayZ from a single
                            web-based dashboard. Workshop mods, mod presets,
                            missions, and real-time logs included.
                        </p>

                        {/* Free badge */}
                        <div className="mt-6 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-4 py-1.5 text-sm text-[#a1a1aa]">
                            <Sparkle className="size-4 text-red-400" />
                            Free &amp; open source
                        </div>

                        <div className="mt-10 flex items-center justify-center gap-4">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-medium text-[#0c0c0f] shadow-sm transition-colors hover:bg-white/90"
                                >
                                    Go to Dashboard
                                    <ArrowRightIcon className="size-4" />
                                </Link>
                            ) : (
                                <>
                                    <a
                                        href={GITHUB_URL}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-2 rounded-lg bg-white px-6 py-3 text-sm font-medium text-[#0c0c0f] shadow-sm transition-colors hover:bg-white/90"
                                    >
                                        Get started
                                        <ExternalLink className="size-4" />
                                    </a>
                                    <Link
                                        href={login()}
                                        className="inline-flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-6 py-3 text-sm font-medium text-white shadow-sm transition-colors hover:bg-white/10"
                                    >
                                        Log in
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </section>

                {/* Live Demo */}
                <section className="border-t border-border/50 bg-accent/20 dark:bg-accent/5">
                    <div className="mx-auto max-w-6xl px-6 py-20 lg:py-28">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
                                Real-time server lifecycle
                            </h2>
                            <p className="mx-auto mt-4 max-w-xl text-muted-foreground">
                                Watch a server go from stopped to running.
                                Status changes stream live to every connected
                                client via WebSockets.
                            </p>
                        </div>
                        <div className="mx-auto mt-14 max-w-3xl">
                            <ServerDemo />
                        </div>
                    </div>
                </section>

                {/* Supported Games */}
                <section className="border-t border-border/50">
                    <div className="mx-auto max-w-6xl px-6 py-20 lg:py-28">
                        <div className="text-center">
                            <p className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                                Multi-game support
                            </p>
                            <h2 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                One panel, multiple games
                            </h2>
                            <p className="mx-auto mt-4 max-w-xl text-muted-foreground">
                                Purpose-built handlers for each game ensure
                                correct configuration, launch parameters, and
                                mod management out of the box.
                            </p>
                        </div>
                        <div className="mt-14 grid gap-6 sm:grid-cols-3">
                            <GameCard
                                title="Arma 3"
                                description="Full support including difficulty settings, headless clients, profile backups, and HTML preset imports."
                                features={[
                                    'Headless clients (up to 10)',
                                    'Difficulty & network settings',
                                    'Profile backup & restore',
                                    'Launcher preset import',
                                ]}
                                status="Full support"
                                statusColor="text-emerald-600 dark:text-emerald-400"
                            />
                            <GameCard
                                title="Arma Reforger"
                                description="Dedicated server management with scenario selection, Reforger-specific mod support, and JSON config generation."
                                features={[
                                    'Scenario management',
                                    'Reforger mod support',
                                    'Third-person toggle',
                                    'JSON config generation',
                                ]}
                                status="Full support"
                                statusColor="text-emerald-600 dark:text-emerald-400"
                            />
                            <GameCard
                                title="DayZ"
                                description="Server scaffolding is in place and ready for expansion with game-specific settings and configuration."
                                features={[
                                    'Server instances',
                                    'Basic configuration',
                                    'Workshop mods',
                                    'Expanding soon',
                                ]}
                                status="In progress"
                                statusColor="text-amber-600 dark:text-amber-400"
                            />
                        </div>
                    </div>
                </section>

                {/* Features */}
                <section className="border-t border-border/50 bg-accent/30 dark:bg-accent/10">
                    <div className="mx-auto max-w-6xl px-6 py-20 lg:py-28">
                        <div className="text-center">
                            <p className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                                Features
                            </p>
                            <h2 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                Everything you need to run game servers
                            </h2>
                        </div>
                        <div className="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <FeatureCard
                                icon={ServerIcon}
                                title="Server Management"
                                description="Create and configure multiple server instances with custom ports, passwords, and per-game settings."
                            />
                            <FeatureCard
                                icon={PlayIcon}
                                title="Process Control"
                                description="Start, stop, and restart servers from the UI with real-time status tracking and log streaming."
                            />
                            <FeatureCard
                                icon={DownloadIcon}
                                title="Workshop Mods"
                                description="Download and manage Steam Workshop mods with progress tracking and automatic update checking."
                            />
                            <FeatureCard
                                icon={LayersIcon}
                                title="Mod Presets"
                                description="Organize mods into named presets, import Arma 3 Launcher HTML files, and assign presets to servers."
                            />
                            <FeatureCard
                                icon={FolderIcon}
                                title="Mission Management"
                                description="Upload, download, and manage PBO mission files with automatic symlink setup for servers."
                            />
                            <FeatureCard
                                icon={ShieldIcon}
                                title="Profile Backups"
                                description="Automatic backups on every server start with manual create, upload, download, and restore support."
                            />
                            <FeatureCard
                                icon={UsersIcon}
                                title="Headless Clients"
                                description="Dynamically add or remove headless clients for Arma 3 to offload AI processing from the server."
                            />
                            <FeatureCard
                                icon={MonitorIcon}
                                title="Real-time Logs"
                                description="Live server output, install progress, and mod download logs streamed directly to your browser."
                            />
                            <FeatureCard
                                icon={Box}
                                title="Docker Ready"
                                description="Ships as a single Docker container with SteamCMD, PHP, Nginx, and queue workers all included."
                            />
                        </div>
                    </div>
                </section>

                {/* How It Works */}
                <section className="border-t border-border/50">
                    <div className="mx-auto max-w-6xl px-6 py-20 lg:py-28">
                        <div className="text-center">
                            <p className="text-sm font-medium tracking-wide text-muted-foreground uppercase">
                                Quick start
                            </p>
                            <h2 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                                Up and running in minutes
                            </h2>
                        </div>
                        <div className="mt-14 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                            <StepCard
                                step={1}
                                title="Deploy"
                                description="Run the Docker container with a single volume mount. Everything is bundled."
                            />
                            <StepCard
                                step={2}
                                title="Connect Steam"
                                description="Enter your Steam credentials and API key to enable downloads and mod management."
                            />
                            <StepCard
                                step={3}
                                title="Install a game"
                                description="Pick a game and branch, then install the dedicated server files via SteamCMD."
                            />
                            <StepCard
                                step={4}
                                title="Launch"
                                description="Create a server instance, configure it, attach mods, and hit start."
                            />
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-border/50">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
                        <div className="flex items-center gap-2">
                            <AppLogoColor className="size-6" />
                            <span className="text-sm font-medium">Armaani</span>
                            <span className="ml-2 text-sm leading-none text-muted-foreground">
                                Made with love in 🇫🇮
                            </span>
                        </div>
                        <div className="flex items-center gap-4">
                            <a
                                href={GITHUB_URL}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
                            >
                                <Github className="size-4" />
                                GitHub
                            </a>
                            <span className="text-sm text-muted-foreground">
                                Game Server Manager
                            </span>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}

function GameCard({
    title,
    description,
    features,
    status,
    statusColor,
}: {
    title: string;
    description: string;
    features: string[];
    status: string;
    statusColor: string;
}) {
    return (
        <div className="flex flex-col rounded-xl border border-border bg-card p-6 shadow-sm">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-lg font-semibold">{title}</h3>
                <span className={`text-xs font-medium ${statusColor}`}>
                    {status}
                </span>
            </div>
            <p className="mb-5 text-sm text-muted-foreground">{description}</p>
            <ul className="mt-auto space-y-2.5">
                {features.map((feature) => (
                    <li
                        key={feature}
                        className="flex items-center gap-2 text-sm"
                    >
                        <Check className="size-3.5 shrink-0 text-emerald-500" />
                        <span>{feature}</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function FeatureCard({
    icon: Icon,
    title,
    description,
}: {
    icon: ComponentType<SVGAttributes<SVGSVGElement>>;
    title: string;
    description: string;
}) {
    return (
        <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
            <div className="mb-4 flex size-10 items-center justify-center rounded-lg bg-accent">
                <Icon className="size-5 text-foreground" />
            </div>
            <h3 className="mb-2 font-semibold">{title}</h3>
            <p className="text-sm leading-relaxed text-muted-foreground">
                {description}
            </p>
        </div>
    );
}

function StepCard({
    step,
    title,
    description,
}: {
    step: number;
    title: string;
    description: string;
}) {
    return (
        <div className="text-center">
            <div className="mx-auto mb-4 flex size-10 items-center justify-center rounded-full border border-border bg-card text-sm font-bold shadow-sm">
                {step}
            </div>
            <h3 className="mb-2 font-semibold">{title}</h3>
            <p className="text-sm leading-relaxed text-muted-foreground">
                {description}
            </p>
        </div>
    );
}

/* -------------------------------------------------------------------------- */
/*  Server lifecycle demo                                                     */
/* -------------------------------------------------------------------------- */

type DemoStatus =
    | 'stopped'
    | 'starting'
    | 'booting'
    | 'downloading_mods'
    | 'running';

const statusLabels: Record<DemoStatus, string> = {
    stopped: 'Stopped',
    starting: 'Starting...',
    booting: 'Booting...',
    downloading_mods: 'Downloading Mods...',
    running: 'Running',
};

const statusBadgeVariant: Record<
    DemoStatus,
    'success' | 'warning' | 'info' | 'secondary'
> = {
    stopped: 'secondary',
    starting: 'warning',
    booting: 'info',
    downloading_mods: 'warning',
    running: 'success',
};

const demoSequence: { status: DemoStatus; duration: number }[] = [
    { status: 'stopped', duration: 2000 },
    { status: 'starting', duration: 2000 },
    { status: 'downloading_mods', duration: 3000 },
    { status: 'booting', duration: 2500 },
    { status: 'running', duration: 0 },
];

function ServerDemo() {
    const [status, setStatus] = useState<DemoStatus>('stopped');
    const [started, setStarted] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const timeoutRef = useRef<ReturnType<typeof setTimeout>>(null);

    // Start animation when the component scrolls into view
    useEffect(() => {
        if (!containerRef.current) {
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting && !started) {
                    setStarted(true);
                }
            },
            { threshold: 0.3 },
        );

        observer.observe(containerRef.current);

        return () => observer.disconnect();
    }, [started]);

    // Run the sequence once when started
    useEffect(() => {
        if (!started) {
            return;
        }

        let stepIndex = 0;

        function advance() {
            const step = demoSequence[stepIndex];
            setStatus(step.status);

            stepIndex++;

            // Stop after reaching the last step (running)
            if (stepIndex < demoSequence.length) {
                timeoutRef.current = setTimeout(advance, step.duration);
            }
        }

        advance();

        return () => {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
        };
    }, [started]);

    const isTransitioning =
        status === 'starting' ||
        status === 'booting' ||
        status === 'downloading_mods';

    return (
        <div
            ref={containerRef}
            className="overflow-hidden rounded-lg border bg-card shadow-lg"
        >
            {/* Card header — mirrors real server card */}
            <div className="relative flex items-center justify-between p-4">
                {statusGradients.map(({ status: s, color, shimmer }) => (
                    <div
                        key={s}
                        className={`absolute inset-0 overflow-hidden bg-gradient-to-r [mask-image:linear-gradient(to_right,black,black_20%,transparent_45%)] transition-opacity duration-700 ${color} ${status === s ? 'opacity-100' : 'opacity-0'}`}
                    >
                        {shimmer && (
                            <div
                                className={`absolute inset-y-0 left-0 w-1/3 bg-gradient-to-r from-transparent via-white/12 to-transparent dark:via-white/6 ${shimmer}`}
                            />
                        )}
                    </div>
                ))}

                <div className="relative min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <h3 className="text-lg font-semibold">Kolguyev</h3>
                        <Badge variant="outline">Arma Reforger</Badge>
                        <Badge variant={statusBadgeVariant[status]}>
                            {statusLabels[status]}
                        </Badge>
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Port: 2001 &middot; Players: 128 &middot; Install:
                        Reforger Server{' '}
                        <span className="font-mono text-xs">(public)</span>
                    </p>
                </div>

                <div className="relative flex items-center gap-2">
                    {status === 'stopped' && (
                        <span className="inline-flex h-8 items-center gap-2 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground">
                            <Play className="size-3.5" />
                            Start
                        </span>
                    )}
                    {isTransitioning && (
                        <>
                            <span className="inline-flex h-8 items-center gap-2 rounded-md bg-muted px-3 text-xs font-medium text-muted-foreground">
                                <Loader2 className="size-3.5 animate-spin" />
                                {statusLabels[status]}
                            </span>
                            <span className="inline-flex h-8 items-center gap-2 rounded-md bg-destructive px-3 text-xs font-medium text-white">
                                <Pause className="size-3.5" />
                                Stop
                            </span>
                        </>
                    )}
                    {status === 'running' && (
                        <>
                            <span className="inline-flex h-8 items-center gap-2 rounded-md bg-destructive px-3 text-xs font-medium text-white">
                                <Pause className="size-3.5" />
                                Stop
                            </span>
                            <span className="inline-flex h-8 items-center gap-2 rounded-md border border-border bg-background px-3 text-xs font-medium">
                                <RefreshCw className="size-3.5" />
                                Restart
                            </span>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
