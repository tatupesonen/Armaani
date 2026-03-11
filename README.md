![Dashboard](public/og-image.png)

Armaani _(Finnish for *"my dear"*)_ is a web-based game server manager for Arma 3, Arma Reforger, and (soon) DayZ. Install, configure, and manage dedicated server instances from a single UI.

Built with Laravel 12, Inertia v2, React 19, Tailwind CSS v4, and with a bunch of help from Claude.

## Inspired by

[fugasjunior/arma-server-manager](https://github.com/fugasjunior/arma-server-manager)

## Screenshots

![Dashboard](docs/dashboard.png)

![Servers](docs/servers.png)

## Features

- Install and update game servers via SteamCMD
- Start, stop, and restart server instances with real-time log streaming
- Download and manage Steam Workshop mods
- Organize mods into presets and assign them to servers
- Import Arma 3 Launcher preset files
- Per-game server settings (difficulty, network, cross-platform, etc.)
- Dynamic headless client management (Arma 3)
- Dynamic scenario discovery from installed mods (Arma Reforger)
- Profile backup and restore
- Crash detection with automatic server restart
- Discord webhook notifications for server events
- Real-time status updates via WebSockets

## Docker Deployment

The recommended way to run Armaani is with Docker. The image bundles SteamCMD, PHP, Caddy, and everything else into a single container.

### Quick Start

Create a `docker-compose.yml`:

```yaml
services:
    armaani:
        image: tatupesonen/armaani:latest
        container_name: armaani
        network_mode: host
        volumes:
            - ./storage:/var/www/html/storage
        environment:
            - SITE_ADDRESS=${SITE_ADDRESS:-:80}
        restart: unless-stopped
```

Then start it:

```bash
docker compose up -d
```

This starts Armaani on port 80 (HTTP). On first boot the container will:

1. Run database migrations
2. Create a default admin account with a random password
3. Print the credentials to the container logs

Grab the login credentials from the logs:

```bash
docker logs armaani
```

Look for the `ADMIN ACCOUNT CREATED` box with the email and password.

### Configuration

| Variable       | Default | Description                                                                                                                           |
| -------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| `SITE_ADDRESS` | `:80`   | Set to a domain (e.g. `arma.example.com`) to enable automatic HTTPS via Let's Encrypt. Use `:PORT` for plain HTTP on a specific port. |

### HTTPS with Let's Encrypt

Set `SITE_ADDRESS` to your domain and Caddy will automatically obtain and renew TLS certificates:

```yaml
environment:
    - SITE_ADDRESS=arma.example.com
```

Requirements: ports 80 and 443 must be reachable from the internet, and DNS must point to the server.

### Volume

A single volume mount persists all data:

```yaml
volumes:
    - ./storage:/var/www/html/storage
```

This contains the SQLite database, game installs, server configs, mods, missions, backups, TLS certificates, and the `.env` file. Back up this directory.

### Network

`network_mode: host` is required because game servers bind to dynamic ports that can't be predicted ahead of time.

### Admin Account

On first boot, a default admin user is created automatically:

- **Email:** `admin@armaani.local`
- **Password:** randomly generated (shown once in the logs)

To use custom credentials:

```yaml
services:
    armaani:
        # ...
        command: ['--email=you@example.com', '--password=your-password']
```

Or override the admin after startup:

```bash
docker exec armaani php artisan user:create-admin --email=you@example.com --password=secret
```

> The command is a no-op if users already exist. To reset, delete the SQLite database and restart.

### Building from Source

If you prefer to build the image yourself instead of using the published one:

```bash
git clone https://github.com/tatupesonen/Armaani.git
cd Armaani
docker build -t armaani .
docker run -d --name armaani --network host -v ./storage:/var/www/html/storage armaani
```

## Local Development

### Requirements

- PHP 8.4+
- Node.js 22+
- SQLite
- SteamCMD

### Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

### Running

```bash
composer run dev
```

### Testing

```bash
php artisan test
```

## License

AGPLv3
