/**
 * App Router
 *
 * Production application routes.
 * This module exports the Express router for the main application.
 *
 * Demonstrates @zappzarapp/browser-utils in the backend (the package
 * supports Node >= 22): CommonValidator + Result for input validation,
 * resolveLocale + formatDateResult for Accept-Language negotiation.
 */

import { Router, Request, Response } from 'express';
import { Result, Validator, type ValidationError } from '@zappzarapp/browser-utils/core';
import { formatDateResult, resolveLocale } from '@zappzarapp/browser-utils/intl';

/**
 * Locales the demo endpoints can answer in. The first entry is the
 * fallback when Accept-Language matches none of them.
 */
const SUPPORTED_LOCALES = ['en-US', 'de-DE', 'fr-FR'] as const;

/**
 * Create the App router with all production routes
 *
 * @public Boilerplate API — shipped for consumers, no in-tree default importer expected.
 */
export function createAppRouter(): Router {
  const router = Router();

  // Hello endpoint: validated query input + locale-negotiated response
  router.get('/hello', (req: Request, res: Response): void => {
    const nameParam = typeof req.query.name === 'string' ? req.query.name : undefined;

    // Absent parameter falls back to the default; a provided value must
    // pass validation (non-empty after trimming) instead of being
    // silently replaced.
    const nameResult: Result<string, ValidationError> =
      nameParam === undefined
        ? Result.ok('World')
        : Validator.nonEmptyResult('name', nameParam.trim());

    Result.match(nameResult, {
      ok: (name): void => {
        const locale = resolveLocale(SUPPORTED_LOCALES, req.acceptsLanguages());
        const timestamp = Result.unwrapOr(
          formatDateResult(new Date(), locale, { dateStyle: 'full', timeStyle: 'medium' }),
          new Date().toISOString()
        );

        res.json({
          message: `Hello, ${name}!`,
          locale,
          timestamp,
          server: 'Node.js + Express',
        });
      },
      err: (error): void => {
        res.status(422).json({
          error: 'Validation failed',
          field: 'name',
          message: error.message,
        });
      },
    });
  });

  // Echo endpoint (POST only - REST-compliant)
  router.post('/echo', (req: Request, res: Response): void => {
    const body: unknown = req.body;
    res.json({
      echo: body,
      timestamp: new Date().toISOString(),
    });
  });

  return router;
}
