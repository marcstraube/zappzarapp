# 0012: Compose Production Preset Runs Every Service as a Non-Root User

**Date:** 2026-08-12

**Status:** Accepted

**Context:** After the Kubernetes chart moved to the full Pod Security Standard
"restricted" posture ([0011](0011-k8s-chart-runs-every-service-non-root.md)),
the Compose production preset (`compose.production.yaml`) remained the last
deployment surface with root processes: postgres, mariadb and rabbitmq started
as root with capability add-lists (CHOWN/SETUID/SETGID/FOWNER) so their
entrypoints could chown certs and drop privileges, and
seaweedfs/meilisearch/mercure/mailpit ran as root outright. The capability adds
also forced `read_only: false` on the DB services. The building blocks for going
unprivileged already existed: the official DB/broker entrypoints skip their
root-only chown/gosu/su-exec phase when started as a non-root user (verified in
the cluster for 0011), the cert generator manages per-uid read ACLs on the
private keys, and secrets are bind-mounted 0644.

**Decision:** Run every service in the Compose production preset as a non-root
user with `read_only: true`, `cap_drop: ALL` and no capability adds — mirroring
the k8s chart's posture. Concretely:

- `user:` directives pin the image's own service user (uids verified against the
  built images' /etc/passwd, matching the chart): postgres 70:70, mariadb
  999:999, rabbitmq 100:101, seaweedfs 1000:1000; meilisearch/mercure run as uid
  1000 (no dedicated image user; their Dockerfiles chown the data directories to
  1000 so named volumes inherit that ownership). Mailpit gets a `USER 1000:1000`
  directive in its Dockerfile instead — it needs no root on any path and is
  disabled in production anyway.
- Everything a service writes is a named volume or a uid-owned tmpfs: cert
  copies land in tmpfs (`/run/postgresql/certs`, `/etc/mysql/certs`,
  `/etc/seaweedfs/{config,certs}`), as do logs, sockets and /tmp.
- The postgres cert destination is `/run/postgresql/certs` — writable on both
  startup paths (image directory for root, uid-70 tmpfs in production), unlike
  `/var/lib/postgresql`, which is read-only rootfs in the unprivileged preset.
- Wrapper entrypoints and healthchecks guard root-only helpers with
  `[ "$(id -u)" = "0" ]`: chown/gosu/su-exec run on the root path (development
  preset), and the unprivileged path execs directly — the same pattern 0011
  introduced for the shared wrappers.
- The cert key ACL list gains u:70 (postgres); rabbitmq (uid 100) and mariadb
  (uid 999) are covered by the existing nginx/redis entries.
- Mercure keeps binding :443 as uid 1000 via the per-service
  `net.ipv4.ip_unprivileged_port_start=0` sysctl (namespaced, explicit rather
  than relying on the runtime default).
- The production preset now passes `ZAPPZARAPP_ENV=production` to
  postgres/mariadb/rabbitmq/seaweedfs, arming the entrypoints' fail-fast on
  missing TLS certificates — previously the variable never reached these
  containers, so the mandatory-TLS policy was silently inert.

**Consequences:**

- (+) No root process in either deployment surface; Compose production and the
  k8s chart now express the same security posture
- (+) `read_only: true` extends to all data services (previously blocked by the
  root startup phase needing a writable rootfs)
- (+) The mandatory-TLS fail-fast actually triggers in production
- (-) Named volumes created by a root-started service (development preset for
  meilisearch/mercure/mailpit) are not writable by uid 1000; switching presets
  on an existing volume requires recreating it
- (-) The preset depends on image uids (a base-image switch that renumbers users
  requires updating the `user:` directives; verify uids from the image's
  /etc/passwd, not other distro variants)
- (-) Development (root + privilege drop) and production (unprivileged) exercise
  different entrypoint code paths, so both need runtime coverage when
  entrypoints change
