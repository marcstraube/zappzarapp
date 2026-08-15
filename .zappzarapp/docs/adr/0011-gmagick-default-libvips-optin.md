# 0011: GraphicsMagick Default with libvips as FFI Opt-In

**Date:** 2026-08-15

**Status:** Accepted

**Context:** The PHP image ships gmagick (GraphicsMagick binding) for image
processing. GraphicsMagick was chosen over ImageMagick for its smaller attack
surface (no delegate zoo, no ImageTragick history); imagick was evaluated and
rejected on those grounds even though it builds fine on PHP 8.5. gmagick itself
is a maintenance risk: no PECL release compiles against PHP >= 8.5 (2.0.6RC1 is
the latest, upstream dormant since 2021), so the image builds a pinned commit of
the maintained fix branch, watched by a Renovate custom manager. That pin can
break again with PHP 8.6.

libvips is the modern alternative (powers Node's sharp and Rails ActiveStorage):
a streaming architecture that is several times faster than ImageMagick-family
libraries at a fraction of the memory, a small actively-maintained codebase,
format handling delegated to well-audited libraries (libjpeg-turbo, libpng,
libwebp), OSS-Fuzz covered. The official PHP binding `php-vips` v2+ uses FFI
instead of a compiled C extension — no PECL compile step that can break on PHP
majors. It is NOT a drop-in replacement for gmagick (pipeline-oriented API vs.
imperative object API), so replacing gmagick would be a breaking change.

**Decision:** gmagick stays the default; the image ships the libvips
prerequisites as a consciously disabled opt-in:

- The `vips` runtime library and the FFI extension are baked into the PHP image.
- `ffi.enable=false` in `docker/php/conf.d/ffi.ini`: the FFI attack surface
  (native calls from PHP) only exists when the user deliberately opens it.
- User activation path: set `ffi.enable=true` in `ffi.ini`, rebuild, and
  `composer require jcupitt/vips` in the project. The binding is deliberately
  NOT part of the boilerplate's composer.json — a user-project decision.
- **A default switch to libvips is bound to a precondition, not to a version
  number: the FFI usage must become containable.** PHP's containment mechanism
  exists — `ffi.enable=preload` declares bindings once via `FFI::load` in an
  `opcache.preload` script, and runtime code can only call the predeclared
  scope, never define new bindings. php-vips however builds its bindings at
  runtime via `FFI::cdef` and has no preload-scope support, so it requires full
  runtime FFI (`ffi.enable=true`) — the general-purpose key. Unrestricted
  runtime FFI as a default would void the shipped hardening (`disable_functions`
  becomes decoration when any injected PHP code can bind libc directly) and hand
  memory-unsafe native calls to every installation. Until php-vips (or an
  equivalent binding) works under preload, libvips stays opt-in permanently.
- If gmagick becomes unbuildable before that precondition is met, the
  re-evaluation candidate for the default is imagick (compiled extension, builds
  on PHP 8.5) rather than a runtime-FFI default: the ImageMagick delegate
  surface it was rejected for weighs differently than giving every installation
  unrestricted native calls.

**Consequences:**

- (+) Users get a maintained, fast image-processing path without waiting for a
  PECL ecosystem revival, and without the boilerplate breaking existing gmagick
  users.
- (+) The FFI surface is opt-in: default images behave exactly as before, and
  the shipped hardening (`disable_functions` etc.) stays effective for everyone
  who does not opt in.
- (+) `php-vips` survives PHP major upgrades without a compile step.
- (-) The `vips` library and its dependencies add ~36 MB to the PHP image even
  for users who never opt in.
- (-) Opting in means accepting unrestricted runtime FFI for the whole PHP
  process — a conscious trade the activation guide spells out
  (`docs/infrastructure/OPTIONAL-SERVICES.md`).
