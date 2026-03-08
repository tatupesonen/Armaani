import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    KeyRound,
    Settings,
    Shield,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import {
    saveCredentials,
    saveApiKey,
    saveSettings,
    verifyLogin,
    verifyApiKey,
} from '@/actions/App/Http/Controllers/SteamSettingsController';
import Heading from '@/components/heading';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { steamSettings } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type AccountInfo = {
    username: string;
    has_auth_token: boolean;
    has_api_key: boolean;
    mod_download_batch_size: number;
} | null;

type Props = {
    account: AccountInfo;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Steam Settings', href: steamSettings() },
];

export default function SteamSettings({ account }: Props) {
    const [loginVerified, setLoginVerified] = useState<boolean | null>(null);
    const [loginError, setLoginError] = useState<string | null>(null);
    const [loginVerifying, setLoginVerifying] = useState(false);
    const [apiKeyVerified, setApiKeyVerified] = useState<boolean | null>(null);
    const [apiKeyError, setApiKeyError] = useState<string | null>(null);
    const [apiKeyVerifying, setApiKeyVerifying] = useState(false);

    const credentialsForm = useForm({
        username: account?.username ?? '',
        password: '',
        auth_token: '',
    });

    const apiKeyForm = useForm({
        steam_api_key: '',
    });

    const settingsForm = useForm({
        mod_download_batch_size: account?.mod_download_batch_size ?? 5,
    });

    function submitCredentials(e: React.FormEvent) {
        e.preventDefault();
        credentialsForm.post(saveCredentials.url(), { preserveScroll: true });
    }

    function submitVerifyLogin() {
        setLoginVerified(null);
        setLoginError(null);
        setLoginVerifying(true);
        router.post(
            verifyLogin.url(),
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const flash = (page.props as Record<string, unknown>)
                        .flash as Record<string, string> | undefined;
                    if (flash?.success) {
                        setLoginVerified(true);
                        setLoginError(null);
                    } else {
                        setLoginVerified(false);
                        setLoginError(flash?.error ?? 'Verification failed');
                    }
                },
                onError: () => {
                    setLoginVerified(false);
                    setLoginError('Request failed');
                },
                onFinish: () => setLoginVerifying(false),
            },
        );
    }

    function submitApiKey(e: React.FormEvent) {
        e.preventDefault();
        apiKeyForm.post(saveApiKey.url(), { preserveScroll: true });
    }

    function submitVerifyApiKey() {
        setApiKeyVerified(null);
        setApiKeyError(null);
        setApiKeyVerifying(true);
        router.post(
            verifyApiKey.url(),
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const flash = (page.props as Record<string, unknown>)
                        .flash as Record<string, string> | undefined;
                    if (flash?.success) {
                        setApiKeyVerified(true);
                        setApiKeyError(null);
                    } else {
                        setApiKeyVerified(false);
                        setApiKeyError(flash?.error ?? 'Verification failed');
                    }
                },
                onError: () => {
                    setApiKeyVerified(false);
                    setApiKeyError('Request failed');
                },
                onFinish: () => setApiKeyVerifying(false),
            },
        );
    }

    function submitSettings(e: React.FormEvent) {
        e.preventDefault();
        settingsForm.post(saveSettings.url(), { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Steam Settings" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Steam Settings"
                    description="Configure SteamCMD credentials and download preferences."
                />

                <div className="max-w-2xl space-y-6">
                    {/* SteamCMD Credentials */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Shield className="size-5" />
                                SteamCMD Login
                            </CardTitle>
                            <CardDescription>
                                Required for downloading dedicated servers and
                                workshop mods.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitCredentials}
                                className="space-y-4"
                            >
                                <div className="space-y-2">
                                    <Label>Username</Label>
                                    <Input
                                        value={credentialsForm.data.username}
                                        onChange={(e) =>
                                            credentialsForm.setData(
                                                'username',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    {credentialsForm.errors.username && (
                                        <p className="text-sm text-destructive">
                                            {credentialsForm.errors.username}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label>Password</Label>
                                    <Input
                                        type="password"
                                        value={credentialsForm.data.password}
                                        onChange={(e) =>
                                            credentialsForm.setData(
                                                'password',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={account ? '********' : ''}
                                        required
                                    />
                                    {credentialsForm.errors.password && (
                                        <p className="text-sm text-destructive">
                                            {credentialsForm.errors.password}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label>Steam Guard Token (optional)</Label>
                                    <Input
                                        value={credentialsForm.data.auth_token}
                                        onChange={(e) =>
                                            credentialsForm.setData(
                                                'auth_token',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={
                                            account?.has_auth_token
                                                ? '********'
                                                : ''
                                        }
                                    />
                                </div>
                                <Alert
                                    variant="default"
                                    className="border-amber-500/50 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-950/50 dark:text-amber-200"
                                >
                                    <AlertTriangle className="size-4 text-amber-600 dark:text-amber-400" />
                                    <AlertDescription>
                                        Steam Guard: If you have email-based
                                        Steam Guard enabled, you may need to
                                        attempt a server install first (which
                                        will fail), then enter the token sent to
                                        your email.
                                    </AlertDescription>
                                </Alert>
                                <div className="flex items-center gap-3">
                                    <Button
                                        type="submit"
                                        disabled={credentialsForm.processing}
                                    >
                                        {credentialsForm.processing && (
                                            <Spinner className="mr-2" />
                                        )}
                                        Save Credentials
                                    </Button>
                                    <Button
                                        type="button"
                                        variant={
                                            loginVerified === true
                                                ? 'default'
                                                : loginVerified === false
                                                  ? 'destructive'
                                                  : 'outline'
                                        }
                                        onClick={submitVerifyLogin}
                                        disabled={!account || loginVerifying}
                                        className={
                                            loginVerified === true
                                                ? 'bg-green-600 text-white hover:bg-green-700'
                                                : ''
                                        }
                                    >
                                        {loginVerifying && (
                                            <Spinner className="mr-2" />
                                        )}
                                        <ShieldCheck className="mr-2 size-4" />
                                        Verify Login
                                    </Button>
                                    {loginError && (
                                        <code className="rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                            {loginError}
                                        </code>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    {/* Steam API Key */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <KeyRound className="size-5" />
                                Steam Web API Key
                            </CardTitle>
                            <CardDescription>
                                Used to fetch workshop mod metadata (name, file
                                size). Get one at{' '}
                                <a
                                    href="https://steamcommunity.com/dev/apikey"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="underline"
                                >
                                    steamcommunity.com/dev/apikey
                                </a>
                                . Optional — the public API works without a key,
                                but may be rate-limited.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submitApiKey} className="space-y-4">
                                <div className="space-y-2">
                                    <Label>API Key</Label>
                                    <Input
                                        value={apiKeyForm.data.steam_api_key}
                                        onChange={(e) =>
                                            apiKeyForm.setData(
                                                'steam_api_key',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={
                                            account?.has_api_key
                                                ? '********'
                                                : ''
                                        }
                                    />
                                    {apiKeyForm.errors.steam_api_key && (
                                        <p className="text-sm text-destructive">
                                            {apiKeyForm.errors.steam_api_key}
                                        </p>
                                    )}
                                </div>
                                <div className="flex items-center gap-3">
                                    <Button
                                        type="submit"
                                        disabled={apiKeyForm.processing}
                                    >
                                        {apiKeyForm.processing && (
                                            <Spinner className="mr-2" />
                                        )}
                                        Save API Key
                                    </Button>
                                    <Button
                                        type="button"
                                        variant={
                                            apiKeyVerified === true
                                                ? 'default'
                                                : apiKeyVerified === false
                                                  ? 'destructive'
                                                  : 'outline'
                                        }
                                        onClick={submitVerifyApiKey}
                                        disabled={
                                            !account?.has_api_key ||
                                            apiKeyVerifying
                                        }
                                        className={
                                            apiKeyVerified === true
                                                ? 'bg-green-600 text-white hover:bg-green-700'
                                                : ''
                                        }
                                    >
                                        {apiKeyVerifying && (
                                            <Spinner className="mr-2" />
                                        )}
                                        <ShieldCheck className="mr-2 size-4" />
                                        Verify Key
                                    </Button>
                                    {apiKeyError && (
                                        <code className="rounded bg-zinc-100 px-2 py-1 text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                            {apiKeyError}
                                        </code>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    {/* Download Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Settings className="size-5" />
                                Download Settings
                            </CardTitle>
                            <CardDescription>
                                Configure how mods are downloaded.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submitSettings}
                                className="space-y-4"
                            >
                                <div className="space-y-2">
                                    <Label>Mod Download Batch Size</Label>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={50}
                                        value={
                                            settingsForm.data
                                                .mod_download_batch_size
                                        }
                                        onChange={(e) =>
                                            settingsForm.setData(
                                                'mod_download_batch_size',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Number of mods to download
                                        simultaneously per SteamCMD session
                                        (1-50).
                                    </p>
                                </div>
                                <Button
                                    type="submit"
                                    disabled={settingsForm.processing}
                                >
                                    {settingsForm.processing && (
                                        <Spinner className="mr-2" />
                                    )}
                                    Save Settings
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
