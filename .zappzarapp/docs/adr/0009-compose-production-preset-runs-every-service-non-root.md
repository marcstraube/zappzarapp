# 0009: Compose Production Preset Runs Every Service as a Non-Root User

**Date:** 2026-08-12

**Status:** Accepted

**Context:** The Compose production preset (`compose.production.yaml`) is the
second deployment surface next to the Kubernetes chart, which runs the full Pod
Security Standard "restricted" posture
([0008](0008-k8s-chart-runs-every-service-non-root.md)) — the preset should
express the same guarantees. Letting DB/broker entrypoints start as root to
chown certs and drop privileges requires capability add-lists
(CHOWN/SETUID/SETGID/FOWNER) and a writable rootfs on exactly the services
holding the data, and services without a privilege-drop phase
(seaweedfs/meilisearch/mercure/mailpit) then run as root outright. The building
blocks for going unprivileged instead are all in place: the official DB/broker
entrypoints skip their root-only chown/gosu/su-exec phase when started as a
non-root user (verified in the cluster for 0008), the cert generator manages
per-uid read ACLs on the private keys, and secrets are bind-mounted 0644.

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
  preset), and the unprivileged path execs directly — the same pattern 0008
  introduced for the shared wrappers.
- The cert generator grants per-uid read ACLs on the private keys for every
  unprivileged consumer (see `docker/certs/generate-internal.sh` for the
  authoritative uid list).
- Mercure keeps binding :443 as uid 1000 via the per-service
  `net.ipv4.ip_unprivileged_port_start=0` sysctl (namespaced, explicit rather
  than relying on the runtime default).
- The production preset passes `ZAPPZARAPP_ENV=production` per service to
  postgres/mariadb/rabbitmq/seaweedfs, arming the entrypoints' fail-fast on
  missing TLS certificates — compose interpolation context does not populate
  container environments, so without the explicit per-service entries the
  mandatory-TLS policy is silently inert.

**Consequences:**

- (+) No root process in either deployment surface; Compose production and the
  k8s chart express the same security posture
- (+) `read_only: true` extends to all data services — no root startup phase
  needs a writable rootfs
- (+) The mandatory-TLS fail-fast is armed in production
- (-) Named volumes created by a root-started service (development preset for
  meilisearch/mercure/mailpit) are not writable by uid 1000; switching presets
  on an existing volume requires recreating it
- (-) The preset depends on image uids (a base-image switch that renumbers users
  requires updating the `user:` directives; verify uids from the image's
  /etc/passwd, not other distro variants)
- (-) Development (root + privilege drop) and production (unprivileged) exercise
  different entrypoint code paths, so both need runtime coverage when
  entrypoints change
