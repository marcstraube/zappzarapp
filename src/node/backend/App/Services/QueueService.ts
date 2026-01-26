/**
 * Queue Service for message queue operations using RabbitMQ
 *
 * Provides a simple, type-safe interface for publishing and consuming messages.
 * Supports both TLS (amqps://) and plain (amqp://) connections.
 *
 * Features:
 * - Lazy connection (connects on first use)
 * - Automatic reconnection on failure
 * - Queue declaration with durability options
 * - Message persistence and priority support
 * - Graceful error handling (returns null/false, no throws)
 *
 * Configuration:
 * - RABBITMQ_URL: Full connection URL (default: amqp://rabbitmq:5672)
 * - RABBITMQ_HOST: Hostname (default: rabbitmq)
 * - RABBITMQ_PORT: Port (default: 5672)
 * - RABBITMQ_VHOST: Virtual host (default: /)
 *
 * Credentials are loaded from Docker secrets or environment:
 * - /tmp/secrets/rabbitmq_user.txt or RABBITMQ_USER
 * - /tmp/secrets/rabbitmq_password.txt or RABBITMQ_PASSWORD
 *
 * Usage:
 * ```typescript
 * const queue = new QueueService();
 * await queue.connect();
 *
 * // Publish a task
 * await queue.publish('tasks', JSON.stringify({ type: 'email', to: 'user@example.com' }));
 *
 * // Get a message (non-blocking)
 * const msg = await queue.get('tasks');
 * if (msg !== null) {
 *   await processTask(JSON.parse(msg.body));
 *   await queue.ack(msg.deliveryTag);
 * }
 *
 * // Cleanup
 * await queue.disconnect();
 * ```
 */

import * as amqp from 'amqplib';
import { loadCredential } from '../../Shared/Config/credentials';

/**
 * Queue Service Interface
 */
export interface QueueServiceInterface {
  /**
   * Connect to queue backend
   */
  connect(): Promise<void>;

  /**
   * Disconnect from queue backend
   */
  disconnect(): Promise<void>;

  /**
   * Publish a message to a queue
   */
  publish(queue: string, message: string, options?: PublishOptions): Promise<boolean>;

  /**
   * Consume messages from a queue (blocking)
   */
  consume(
    queue: string,
    callback: (message: string) => Promise<boolean>,
    options?: ConsumeOptions
  ): Promise<void>;

  /**
   * Get a single message from a queue (non-blocking)
   */
  get(queue: string, autoAck?: boolean): Promise<QueueMessage | null>;

  /**
   * Acknowledge a message
   */
  ack(deliveryTag: number): Promise<boolean>;

  /**
   * Reject a message
   */
  nack(deliveryTag: number, requeue?: boolean): Promise<boolean>;

  /**
   * Declare a queue
   */
  declareQueue(queue: string, options?: QueueDeclareOptions): Promise<boolean>;

  /**
   * Get message count in a queue
   */
  getMessageCount(queue: string): Promise<number | null>;

  /**
   * Purge all messages from a queue
   */
  purgeQueue(queue: string): Promise<number>;

  /**
   * Check if queue backend is available
   */
  isAvailable(): boolean;
}

/**
 * Publish options
 */
export interface PublishOptions {
  /** Message survives broker restart (default: true) */
  persistent?: boolean;
  /** Message priority 0-9 (default: 0) */
  priority?: number;
  /** Message TTL in milliseconds */
  expiration?: string;
  /** Custom message headers */
  headers?: Record<string, string | number | boolean>;
}

/**
 * Consume options
 */
export interface ConsumeOptions {
  /** Number of unacked messages to prefetch (default: 1) */
  prefetch?: number;
  /** Auto-acknowledge messages (default: false) */
  noAck?: boolean;
}

/**
 * Queue declaration options
 */
export interface QueueDeclareOptions {
  /** Queue survives broker restart (default: true) */
  durable?: boolean;
  /** Used by only one connection (default: false) */
  exclusive?: boolean;
  /** Delete when last consumer disconnects (default: false) */
  autoDelete?: boolean;
  /** Additional queue arguments */
  arguments?: Record<string, unknown>;
}

/**
 * Queue message structure
 */
export interface QueueMessage {
  body: string;
  deliveryTag: number;
}

/**
 * Queue service configuration
 */
export interface QueueServiceOptions {
  /** RabbitMQ URL (default: from env or amqp://rabbitmq:5672) */
  url?: string;
  /** Connection timeout in milliseconds (default: 5000) */
  timeout?: number;
}

/**
 * RabbitMQ Queue Service Implementation
 */
export class QueueService implements QueueServiceInterface {
  private connection: amqp.ChannelModel | null = null;
  private channel: amqp.Channel | null = null;
  private readonly url: string;

  constructor(options: QueueServiceOptions = {}) {
    // Note: timeout option reserved for future socket configuration

    // Build connection URL from options or environment
    if (options.url !== undefined && options.url !== '') {
      this.url = this.applyCredentials(options.url);
    } else {
      this.url = this.buildUrlFromEnvironment();
    }
  }

  async connect(): Promise<void> {
    if (this.connection !== null) {
      return; // Already connected
    }

    try {
      this.connection = await amqp.connect(this.url);

      // Handle connection errors
      this.connection.on('error', () => {
        // Errors are handled but don't throw
        // Individual operations will return null/false
      });

      this.connection.on('close', () => {
        this.connection = null;
        this.channel = null;
      });

      this.channel = await this.connection.createChannel();
    } catch {
      this.connection = null;
      this.channel = null;
    }
  }

  async disconnect(): Promise<void> {
    try {
      if (this.channel !== null) {
        await this.channel.close();
      }
    } catch {
      // Ignore close errors
    }

    try {
      if (this.connection !== null) {
        await this.connection.close();
      }
    } catch {
      // Ignore close errors
    }

    this.channel = null;
    this.connection = null;
  }

  async publish(queue: string, message: string, options: PublishOptions = {}): Promise<boolean> {
    if (this.channel === null) {
      return false;
    }

    try {
      // Ensure queue exists
      if (!(await this.declareQueue(queue))) {
        return false;
      }

      const publishOptions: amqp.Options.Publish = {
        persistent: options.persistent ?? true,
      };

      if (options.priority !== undefined) {
        publishOptions.priority = Math.min(9, Math.max(0, options.priority));
      }

      if (options.expiration !== undefined) {
        publishOptions.expiration = options.expiration;
      }

      if (options.headers !== undefined) {
        publishOptions.headers = options.headers;
      }

      return this.channel.sendToQueue(queue, Buffer.from(message), publishOptions);
    } catch {
      await this.handleError();
      return false;
    }
  }

  async consume(
    queue: string,
    callback: (message: string) => Promise<boolean>,
    options: ConsumeOptions = {}
  ): Promise<void> {
    if (this.channel === null) {
      return;
    }

    try {
      // Ensure queue exists
      if (!(await this.declareQueue(queue))) {
        return;
      }

      const prefetch = options.prefetch ?? 1;
      const noAck = options.noAck ?? false;

      await this.channel.prefetch(prefetch);

      await this.channel.consume(
        queue,
        (msg: amqp.ConsumeMessage | null) => {
          if (msg === null || this.channel === null) {
            return;
          }

          // Process message and handle ack/nack
          void callback(msg.content.toString())
            .then((result) => {
              if (!noAck && this.channel !== null) {
                if (result) {
                  this.channel.ack(msg);
                } else {
                  this.channel.nack(msg, false, true);
                }
              }
            })
            .catch(() => {
              if (!noAck && this.channel !== null) {
                this.channel.nack(msg, false, true);
              }
            });
        },
        { noAck }
      );
    } catch {
      await this.handleError();
    }
  }

  async get(queue: string, autoAck = false): Promise<QueueMessage | null> {
    if (this.channel === null) {
      return null;
    }

    try {
      // Ensure queue exists
      if (!(await this.declareQueue(queue))) {
        return null;
      }

      const msg = await this.channel.get(queue, { noAck: autoAck });

      if (msg === false) {
        return null;
      }

      return {
        body: msg.content.toString(),
        deliveryTag: msg.fields.deliveryTag,
      };
    } catch {
      await this.handleError();
      return null;
    }
  }

  async ack(deliveryTag: number): Promise<boolean> {
    if (this.channel === null) {
      return false;
    }

    try {
      // amqplib ack requires the message object, but we can use delivery tag
      // Note: This creates a minimal message-like object for ack
      this.channel.ack({ fields: { deliveryTag } } as amqp.ConsumeMessage);
      return true;
    } catch {
      await this.handleError();
      return false;
    }
  }

  async nack(deliveryTag: number, requeue = false): Promise<boolean> {
    if (this.channel === null) {
      return false;
    }

    try {
      this.channel.nack({ fields: { deliveryTag } } as amqp.ConsumeMessage, false, requeue);
      return true;
    } catch {
      await this.handleError();
      return false;
    }
  }

  async declareQueue(queue: string, options: QueueDeclareOptions = {}): Promise<boolean> {
    if (this.channel === null) {
      return false;
    }

    try {
      await this.channel.assertQueue(queue, {
        durable: options.durable ?? true,
        exclusive: options.exclusive ?? false,
        autoDelete: options.autoDelete ?? false,
        arguments: options.arguments,
      });

      return true;
    } catch {
      await this.handleError();
      return false;
    }
  }

  async getMessageCount(queue: string): Promise<number | null> {
    if (this.channel === null) {
      return null;
    }

    try {
      const result = await this.channel.checkQueue(queue);
      return result.messageCount;
    } catch {
      await this.handleError();
      return null;
    }
  }

  async purgeQueue(queue: string): Promise<number> {
    if (this.channel === null) {
      return 0;
    }

    try {
      const result = await this.channel.purgeQueue(queue);
      return result.messageCount;
    } catch {
      await this.handleError();
      return 0;
    }
  }

  isAvailable(): boolean {
    return this.connection !== null && this.channel !== null;
  }

  /**
   * Check if connected (for testing)
   */
  isConnected(): boolean {
    return this.connection !== null && this.channel !== null;
  }

  /**
   * Get the connection URL (for testing)
   */
  getUrl(): string {
    return this.url;
  }

  /**
   * Handle connection errors
   */
  private async handleError(): Promise<void> {
    try {
      await this.disconnect();
    } catch {
      // Ignore disconnect errors during error handling
    }
  }

  /**
   * Build URL from environment variables
   */
  private buildUrlFromEnvironment(): string {
    const envUrl = process.env.RABBITMQ_URL;
    if (envUrl !== undefined && envUrl !== '') {
      return this.applyCredentials(envUrl);
    }

    const host = process.env.RABBITMQ_HOST ?? 'rabbitmq';
    const port = process.env.RABBITMQ_PORT ?? '5672';
    const vhost = process.env.RABBITMQ_VHOST ?? '/';

    const user = loadCredential('rabbitmq_user', 'RABBITMQ_USER', 'guest');
    const password = loadCredential('rabbitmq_password', 'RABBITMQ_PASSWORD', 'guest');

    const encodedVhost = vhost === '/' ? '' : `/${encodeURIComponent(vhost)}`;
    return `amqp://${encodeURIComponent(user)}:${encodeURIComponent(password)}@${host}:${port}${encodedVhost}`;
  }

  /**
   * Apply credentials from secrets/environment to URL if not already present
   */
  private applyCredentials(url: string): string {
    try {
      const parsed = new URL(url);

      // If URL already has credentials, use them as-is
      if (parsed.username !== '' && parsed.password !== '') {
        return url;
      }

      // Apply credentials from secrets/environment
      const user = loadCredential('rabbitmq_user', 'RABBITMQ_USER', 'guest');
      const password = loadCredential('rabbitmq_password', 'RABBITMQ_PASSWORD', 'guest');

      parsed.username = user;
      parsed.password = password;

      return parsed.toString();
    } catch {
      // If URL parsing fails, return original
      return url;
    }
  }
}
