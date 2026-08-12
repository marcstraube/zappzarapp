# 0011: Kubernetes Chart Runs Every Service as a Non-Root User

**Date:** 2026-08-12

**Status:** Accepted

**Context:** The Helm chart's per-service securityContext blocks predated the
unprivileged refactor of the Docker images. Every production image already runs
as a dedicated user (`USER nginx`/`www-data`/`node` directives, or an official
entrypoint that works without root), yet the chart still declared
`runAsNonRoot: false` with capability add-lists (CHOWN/SETUID/SETGID/...)
justified by comments describing entrypoint behavior that no longer exists.
Those capability adds were inert — added capabilities never enter the effective
set of a non-root process — and for the services whose manifests bypass the
image entrypoint (redis, seaweedfs), the server process silently ran as root.
Two services could not start at all under the chart config: nginx (port 80
unbindable by uid 101; entrypoint writes to /run behind an emptyDir that
replaced Alpine's /var/run symlink) and rabbitmq (deprecated
`RABBITMQ_DEFAULT_*_FILE` env vars rejected by 4.x images), and mariadb crashed
on every container restart (the root startup phase cannot traverse the mode-0700
data directories it does not own once all capabilities are dropped — root does
not bypass dropped capabilities).

**Decision:** Run every chart service as a non-root user with
`readOnlyRootFilesystem: true`, `capabilities.drop: [ALL]` and no capability
adds — the full Pod Security Standard "restricted" posture. Concretely:

- Pods run as the image's own service user via numeric `runAsUser`/`runAsGroup`
  in the pod securityContext (numeric because the kubelet cannot verify
  `runAsNonRoot` against a symbolic image `USER`): nginx 100/101, php 82,
  node/node-backend 50000, postgres 70, mariadb 999, redis 999/1000, rabbitmq
  100/101 (all verified against the built images' /etc/passwd — Alpine uids
  differ from the Debian variants), elasticsearch 1000, seaweedfs 1000; services
  without a dedicated image user (mercure, meilisearch, mailpit) run as
  uid 1000. Stateful services add `fsGroup` so their volumes stay writable.
- Official DB entrypoints (postgres/mariadb/rabbitmq) skip their root-only
  chown/gosu/su-exec phase when started unprivileged, which removes the need for
  any capability and fixes the mariadb restart crash and the rabbitmq CAP_CHOWN
  dependency structurally.
- Services listen on unprivileged ports: nginx and mercure moved from 80 to 8080
  inside the pod (Services keep their external ports via named targetPorts).
- Where the chart supplies the complete configuration, the container starts the
  server directly instead of the image entrypoint (nginx): the entrypoint's
  Compose-oriented artifacts are unused in Kubernetes and its Compose production
  TLS policy does not apply where TLS terminates at the Ingress.
- Shared wrapper entrypoints guard privilege-drop helpers with a
  `[ "$(id -u)" = "0" ]` check (gosu/su-exec fail as non-root at setgroups),
  keeping the Compose root path unchanged.

**Consequences:**

- (+) Uniform security posture: no root process in any pod, PSS "restricted"
  compatible across the whole chart, reviewable in values.yaml
- (+) Fixes three broken services (nginx, rabbitmq, mariadb-on-restart) as a
  structural side effect instead of patching them with capabilities
- (+) Compose behavior is untouched: images keep their root startup path for
  bind-mount and cert scenarios; only the Kubernetes layer pins uids
- (-) The chart depends on image uids (a base-image switch that renumbers users
  requires a values update; uids must be verified from the image's /etc/passwd,
  not assumed from other distro variants)
- (-) Kubernetes and Compose exercise different entrypoint code paths for nginx
  and the DB images (unprivileged vs root+drop), so both paths need runtime
  coverage when entrypoints change
