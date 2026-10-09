import { del, get, put } from '@vercel/blob';
import { timingSafeEqual } from 'node:crypto';
import { Readable } from 'node:stream';

export const config = { api: { bodyParser: false } };

const MAX_UPLOAD_BYTES = 4 * 1024 * 1024;

function isAuthorized(req) {
  const expected = process.env.LITEUP_STORAGE_SERVICE_TOKEN || '';
  const received = req.headers['x-liteup-storage-token'] || '';
  if (!expected || typeof received !== 'string') return false;
  const left = Buffer.from(expected);
  const right = Buffer.from(received);
  return left.length === right.length && timingSafeEqual(left, right);
}

function validObjectKey(value) {
  if (typeof value !== 'string') return false;
  return /^(?:profiles\/[a-f0-9]{32}\.(?:jpg|png|gif|webp)|proofs\/[a-f0-9]{32}\.(?:jpg|png|webp|pdf))$/.test(value);
}

function typeAllowed(key, type) {
  const extension = key.split('.').pop();
  const expected = {
    jpg: 'image/jpeg',
    png: 'image/png',
    gif: 'image/gif',
    webp: 'image/webp',
    pdf: 'application/pdf',
  }[extension];
  return expected === type;
}

async function readBoundedBody(req) {
  const chunks = [];
  let size = 0;
  for await (const chunk of req) {
    size += chunk.length;
    if (size > MAX_UPLOAD_BYTES) {
      const error = new Error('Upload exceeds the maximum size.');
      error.statusCode = 413;
      throw error;
    }
    chunks.push(chunk);
  }
  return Buffer.concat(chunks, size);
}

export default async function handler(req, res) {
  if (!isAuthorized(req)) {
    res.statusCode = 401;
    res.end('Unauthorized.');
    return;
  }

  const key = req.headers['x-liteup-storage-key'];
  if (!validObjectKey(key)) {
    res.statusCode = 400;
    res.end('Invalid storage key.');
    return;
  }

  try {
    if (req.method === 'POST') {
      const contentType = req.headers['x-liteup-storage-content-type'];
      if (typeof contentType !== 'string' || !typeAllowed(key, contentType)) {
        res.statusCode = 415;
        res.end('Unsupported file type.');
        return;
      }
      const body = await readBoundedBody(req);
      if (body.length === 0) {
        res.statusCode = 400;
        res.end('Empty upload.');
        return;
      }
      const blob = await put(key, body, {
        access: 'private',
        addRandomSuffix: false,
        contentType,
        allowOverwrite: false,
      });
      res.statusCode = 201;
      res.setHeader('Content-Type', 'application/json; charset=utf-8');
      res.end(JSON.stringify({ pathname: blob.pathname }));
      return;
    }

    if (req.method === 'GET') {
      const result = await get(key, { access: 'private' });
      if (!result || result.statusCode !== 200 || !result.stream) {
        res.statusCode = 404;
        res.end('Not found.');
        return;
      }
      res.statusCode = 200;
      res.setHeader('Content-Type', result.blob.contentType || 'application/octet-stream');
      res.setHeader('X-Content-Type-Options', 'nosniff');
      res.setHeader('Cache-Control', 'private, no-store');
      if (Number.isInteger(result.blob.size)) res.setHeader('Content-Length', String(result.blob.size));
      Readable.fromWeb(result.stream).pipe(res);
      return;
    }

    if (req.method === 'DELETE') {
      await del(key, { access: 'private' });
      res.statusCode = 204;
      res.end();
      return;
    }

    res.setHeader('Allow', 'GET, POST, DELETE');
    res.statusCode = 405;
    res.end('Method not allowed.');
  } catch (error) {
    console.error('LiteUp private storage request failed:', error?.message || 'unknown error');
    res.statusCode = Number.isInteger(error?.statusCode) ? error.statusCode : 502;
    res.end(res.statusCode === 413 ? 'Upload exceeds the maximum size.' : 'Storage request failed.');
  }
}
