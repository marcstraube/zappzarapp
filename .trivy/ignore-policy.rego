# Trivy ignore policy — upstream-only container packages.
#
# These packages carry HIGH/CRITICAL, *fixable* CVEs (scans run with
# --ignore-unfixed) that we cannot remediate ourselves: the vulnerable code is a
# compiled binary, a bundled JAR, or a language runtime baked into an upstream
# image, and only an upstream release changes it. Everything we CAN fix (image
# tag bumps, apk/apt upgrade of OS packages, setuid stripping) is already done;
# what remains lives entirely in the packages below.
#
# Why filter by PACKAGE, not CVE ID: Trivy's vulnerability database updates
# continuously, so the residual CVE-ID set drifts (the same image scanned hours
# apart yields different IDs), and each image rebuild re-fingerprints the SARIF
# results into fresh code-scanning alerts. A CVE-ID allowlist (.trivyignore) or
# per-alert dismissal therefore needs constant maintenance. The vulnerable
# *packages*, by contrast, are stable — so a package-level filter holds without
# a treadmill. See .zappzarapp/docs/security/KNOWN-VULNERABILITIES.md.
#
# NOTE: `pnpm` is deliberately NOT listed — it is our pinned tool. The pnpm 11
# migration has bumped the bundled pnpm to 11.18.0, so its residual CVEs are
# expected to clear on the next image rebuild + re-scan; keeping pnpm un-filtered
# lets that reduction stay visible.

package trivy

# Exact upstream-only package names.
ignore_packages := {
	# Go standard library + modules compiled into bundled gosu / Caddy binaries
	# (postgres, mariadb, mercure, seaweedfs).
	"stdlib",
	"golang.org/x/sys",
	"golang.org/x/net",
	"golang.org/x/text",
	"google.golang.org/grpc",
	"github.com/caddyserver/caddy/v2",
	"github.com/google/cel-go",
	# Java libraries bundled in the Elasticsearch distribution.
	"com.fasterxml.jackson.core:jackson-databind",
	"com.sun.mail:jakarta.mail",
	"org.apache.commons:commons-lang3",
	"io.projectreactor.netty:reactor-netty-http",
	"at.yawk.lz4:lz4-java",
	# Bundled with the Node.js runtime / npm (npm is unused at runtime; we use pnpm).
	"undici",
	"tar",
	"brace-expansion",
	"minimatch",
	"picomatch",
}

default ignore = false

# Ignore any vulnerability whose package is in the upstream-only set.
ignore {
	ignore_packages[input.PkgName]
}

# Ignore the netty family (io.netty:netty-codec-http, -codec, -codec-http2, ...).
ignore {
	startswith(input.PkgName, "io.netty:")
}
