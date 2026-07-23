/**
 * App Router
 *
 * Production application routes.
 * This module exports the Express router for the main application.
 */

import { Router, Request, Response } from 'express';

/**
 * Create the App router with all production routes
 *
 * @public Boilerplate API — shipped for consumers, no in-tree default importer expected.
 */
export function createAppRouter(): Router {
  const router = Router();

  // Hello endpoint
  router.get('/hello', (req: Request, res: Response): void => {
    const nameParam = req.query.name;
    const name = typeof nameParam === 'string' && nameParam.length > 0 ? nameParam : 'World';
    res.json({
      message: `Hello, ${name}!`,
      timestamp: new Date().toISOString(),
      server: 'Node.js + Express',
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
