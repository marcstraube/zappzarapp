# Windows Setup Guide

This guide covers setting up the Docker WebDev Boilerplate on Windows.

---

## Prerequisites

- Windows 10 (version 2004+) or Windows 11
- Administrator access for initial setup

---

## Recommended: WSL2 + Docker Desktop

This is the recommended approach for the best development experience.

### Step 1: Enable WSL2

Open PowerShell as Administrator and run:

```powershell
wsl --install
```

Restart your computer when prompted.

After restart, set WSL2 as default:

```powershell
wsl --set-default-version 2
```

### Step 2: Install a Linux Distribution

```powershell
wsl --install -d Ubuntu
```

On first launch, create a username and password.

### Step 3: Install Docker Desktop

1. Download
   [Docker Desktop for Windows](https://www.docker.com/products/docker-desktop/)
2. Run the installer
3. Enable **"Use WSL 2 based engine"** during installation
4. After installation, open Docker Desktop Settings:
   - **Resources → WSL Integration** → Enable for your Linux distribution

### Step 4: Clone and Run in WSL

Open your WSL terminal (Ubuntu):

```bash
# Navigate to your preferred directory
cd ~

# Clone the project
git clone <repository-url> docker-webdev
cd docker-webdev

# Run setup
make init
make setup
make up
```

> **Important:** Always run commands from within WSL, not from Windows
> PowerShell/CMD.

### Step 5: Access the Application

Open in your Windows browser:

- **HTTP:** <http://localhost:8080>
- **HTTPS:** <https://localhost:8443>
- **Dev Dashboard:** <http://localhost:8080/_dev>

---

## Alternative: Git Bash

If you cannot use WSL2, Git Bash provides a Unix-like environment with `make`
support.

### Step 1: Install Git for Windows

1. Download [Git for Windows](https://gitforwindows.org/)
2. During installation, select:
   - **"Use Git and optional Unix tools from the Command Prompt"**
   - **"Checkout as-is, commit Unix-style line endings"**

### Step 2: Install Make

Option A - Via Chocolatey:

```powershell
# Install Chocolatey first (PowerShell as Admin)
Set-ExecutionPolicy Bypass -Scope Process -Force
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072
iex ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))

# Install make
choco install make
```

Option B - Via MSYS2:

1. Download [MSYS2](https://www.msys2.org/)
2. Run: `pacman -S make`

### Step 3: Install Docker Desktop

1. Download
   [Docker Desktop for Windows](https://www.docker.com/products/docker-desktop/)
2. Run the installer (WSL2 backend is still recommended)

### Step 4: Clone and Run

Open Git Bash:

```bash
cd /c/Users/YourName/Projects
git clone <repository-url> docker-webdev
cd docker-webdev

make init
make setup
make up
```

---

## Troubleshooting

### Docker Volume Permissions

If you encounter permission issues with volumes:

1. In Docker Desktop: **Settings → Resources → File Sharing**
2. Add the project directory path
3. Restart Docker Desktop

### WSL2 Memory Usage

WSL2 can consume significant memory. Create `%UserProfile%\.wslconfig`:

```ini
[wsl2]
memory=4GB
processors=2
```

Then restart WSL: `wsl --shutdown`

### Slow File System Performance

For best performance with WSL2:

- Store project files **inside WSL** (`/home/user/...`)
- Avoid mounting from Windows (`/mnt/c/...`)

### Make Command Not Found

Verify make is installed:

```bash
which make
make --version
```

If not found, ensure the installation path is in your PATH environment variable.

---

## IDE Configuration

### VS Code

1. Install the
   [Remote - WSL](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-wsl)
   extension
2. Open VS Code, press `F1`, select **"WSL: Connect to WSL"**
3. Open your project folder from within WSL

### PhpStorm / WebStorm

1. Configure WSL as the terminal: **Settings → Tools → Terminal → Shell Path:**
   `wsl.exe`
2. Configure Docker: **Settings → Build, Execution, Deployment → Docker → WSL**

---

## Quick Reference

| Task             | Command (run in WSL/Git Bash) |
| ---------------- | ----------------------------- |
| Start containers | `make up`                     |
| Stop containers  | `make down`                   |
| View logs        | `make logs`                   |
| Run tests        | `make test`                   |
| PHP shell        | `make shell-php`              |
| Node shell       | `make shell-node`             |
| Full setup       | `make setup`                  |

---

## See Also

- [Main README](../../README.md)
- [Dev Dashboard](../development/DEV-DASHBOARD.md)
- [Xdebug Configuration](../development/XDEBUG.md)
