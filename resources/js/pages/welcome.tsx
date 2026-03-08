import AppLogoIcon from '@/components/app-logo-icon';
import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';
import {
    ServerIcon,
    DownloadIcon,
    LayersIcon,
    PlayIcon,
    SquareIcon,
    RotateCcwIcon,
    ShieldIcon,
    UsersIcon,
    FolderIcon,
    MonitorIcon,
    ArrowRightIcon,
} from 'lucide-react';
import type { ComponentType, SVGAttributes } from 'react';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Armaani - Game Server Manager">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700"
                    rel="stylesheet"
                />
            </Head>

            <div className="min-h-screen bg-background text-foreground">
                {/* Header */}
                <header className="sticky top-0 z-50 border-b border-border/50 bg-background/80 backdrop-blur-md">
                    <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
                        <div className="flex items-center gap-3">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-foreground">
                                <AppLogoIcon className="size-5 fill-current text-background" />
                            </div>
                            <span className="text-lg font-semibold tracking-tight">
                                Armaani
                            </span>
                        </div>
                        <nav className="flex items-center gap-3">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex items-center gap-2 rounded-lg bg-foreground px-4 py-2 text-sm font-medium text-background transition-colors hover:bg-foreground/90"
                                >
                                    Dashboard
                                    <ArrowRightIcon className="size-4" />
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="rounded-lg px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-accent"
                                    >
                                        Log in
                                    </Link>
                                    {canRegister && (
                                        <Link
                                            href="/register"
                                            className="inline-flex items-center gap-2 rounded-lg bg-foreground px-4 py-2 text-sm font-medium text-background transition-colors hover:bg-foreground/90"
                                        >
                                            Get started
                                            <ArrowRightIcon className="size-4" />
                                        </Link>
                                    )}
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero */}
                <section className="relative overflow-hidden">
                    <div className="absolute inset-0 bg-gradient-to-b from-accent/50 to-transparent dark:from-accent/20" />
                    <div className="relative mx-auto max-w-6xl px-6 py-24 text-center lg:py-36">
                        <div className="mx-auto mb-8 flex size-20 items-center justify-center rounded-2xl bg-foreground shadow-lg">
                            <AppLogoIcon className="size-12 fill-current text-background" />
                        </div>
                        <h1 className="mx-auto max-w-3xl text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                            Manage your game servers with confidence
                        </h1>
                        <p className="mx-auto mt-6 max-w-2xl text-lg text-muted-foreground">
                            Install, configure, and control dedicated servers
                            for Arma 3, Arma Reforger, and DayZ from a single
                            web-based dashboard. Workshop mods, mod presets,
                            missions, and real-time logs included.
                        </p>
                        <div className="mt-10 flex items-center justify-center gap-4">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex items-center gap-2 rounded-lg bg-foreground px-6 py-3 text-sm font-medium text-background shadow-sm transition-colors hover:bg-foreground/90"
                                >
                                    Go to Dashboard
                                    <ArrowRightIcon className="size-4" />
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={
                                            canRegister ? '/register' : login()
                                        }
                                        className="inline-flex items-center gap-2 rounded-lg bg-foreground px-6 py-3 text-sm font-medium text-background shadow-sm transition-colors hover:bg-foreground/90"
                                    >
                                        Get started
                                        <ArrowRightIcon className="size-4" />
                                    </Link>
                                    <Link
                                        href={login()}
                                        className="inline-flex items-center gap-2 rounded-lg border border-border bg-background px-6 py-3 text-sm font-medium text-foreground shadow-sm transition-colors hover:bg-accent"
                                    >
                                        Log in
                                    </Link>
                                </>
                            )}
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
                                icon={SquareIcon}
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

                {/* CTA */}
                <section className="border-t border-border/50">
                    <div className="mx-auto max-w-6xl px-6 py-20 text-center lg:py-28">
                        <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
                            Ready to manage your servers?
                        </h2>
                        <p className="mx-auto mt-4 max-w-xl text-muted-foreground">
                            Set up Armaani and have your first game server
                            running in minutes.
                        </p>
                        <div className="mt-8 flex items-center justify-center gap-4">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex items-center gap-2 rounded-lg bg-foreground px-6 py-3 text-sm font-medium text-background shadow-sm transition-colors hover:bg-foreground/90"
                                >
                                    Go to Dashboard
                                    <ArrowRightIcon className="size-4" />
                                </Link>
                            ) : (
                                <Link
                                    href={canRegister ? '/register' : login()}
                                    className="inline-flex items-center gap-2 rounded-lg bg-foreground px-6 py-3 text-sm font-medium text-background shadow-sm transition-colors hover:bg-foreground/90"
                                >
                                    Get started
                                    <ArrowRightIcon className="size-4" />
                                </Link>
                            )}
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-border/50">
                    <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
                        <div className="flex items-center gap-2">
                            <div className="flex size-6 items-center justify-center rounded-md bg-foreground">
                                <AppLogoIcon className="size-3.5 fill-current text-background" />
                            </div>
                            <span className="text-sm font-medium">Armaani</span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Game Server Manager
                        </p>
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
                        <RotateCcwIcon className="size-3.5 shrink-0 text-muted-foreground" />
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
