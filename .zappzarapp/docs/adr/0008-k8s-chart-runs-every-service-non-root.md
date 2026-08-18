# 0008: Kubernetes Chart Runs Every Service as a Non-Root User

**Date:** 2026-08-12

**Status:** Accepted

**Context:** Every production image runs as a dedicated user
(`USER nginx`/`www-data`/`node` directives, or an official entrypoint that works
without root), so the chart can enforce an unprivileged posture — and the
seemingly softer middle grounds do not actually work. Capability add-lists
(CHOWN/SETUID/SETGID/...) on a non-root pod are inert: added capabilities never
enter the effective set of a non-root process. Running pods as root with
capabilities dropped is worse than it looks: root does not bypass dropped
capabilities, so a root startup phase cannot traverse mode-0700 data directories
it does not own — which crashes mariadb on every container restart. And
manifests that bypass the image entrypoint (redis, seaweedfs) silently run the
server as root unless the pod pins a user. Service-specific constraints shape
the details: uid 101 cannot bind port 80, the nginx entrypoint writes to /run
(an emptyDir there replaces Alpine's /var/run symlink), and rabbitmq 4.x images
reject the deprecated `RABBITMQ_DEFAULT_*_FILE` env vars.

**Decision:** Run every chart service as a non-root user with
`allowPrivilegeEscalation: false`, `readOnlyRootFilesystem: true`,
`capabilities.drop: [ALL]` and no capability adds — the full Pod Security
Standard "restricted" posture. Concretely:

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
- Services listen on unprivileged ports: nginx and mercure bind 8080 instead of
  80 inside the pod (Services keep their external ports via named targetPorts).
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
- (+) The nginx, rabbitmq, and mariadb-restart constraints from the context are
  solved structurally instead of being patched with capabilities
- (+) Compose behavior is untouched: images keep their root startup path for
  bind-mount and cert scenarios; only the Kubernetes layer pins uids
- (-) The chart depends on image uids (a base-image switch that renumbers users
  requires a values update; uids must be verified from the image's /etc/passwd,
  not assumed from other distro variants)
- (-) Kubernetes and Compose exercise different entrypoint code paths for nginx
  and the DB images (unprivileged vs root+drop), so both paths need runtime
  coverage when entrypoints change
