const http = require('http');
const WebSocket = require('ws');
const Y = require('yjs');
const Redis = require('ioredis');
const { setupWSConnection, docs, setRedis, saveToRedis, syncToDatabase } = require('./wsHandler');
const { handleTerminalConnection, setRedisClient } = require('./terminalHandler');

const PORT = process.env.PORT || 1234;
const HOST = process.env.HOST || '0.0.0.0';
const REDIS_HOST = process.env.REDIS_HOST || 'redis';
const REDIS_PORT = process.env.REDIS_PORT || 6379;

// Initialize Redis connection
const redis = new Redis({
  host: REDIS_HOST,
  port: REDIS_PORT,
  retryDelayOnFailover: 100,
  maxRetriesPerRequest: 3
});

redis.on('connect', () => {
  console.log(`[${new Date().toISOString()}] Connected to Redis at ${REDIS_HOST}:${REDIS_PORT}`);
  setRedis(redis);
  setRedisClient(redis);
});

redis.on('error', (err) => {
  console.error('Redis connection error:', err.message);
});

// Create HTTP server
const server = http.createServer((req, res) => {
  // Health check endpoint
  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({
      status: 'ok',
      documents: docs.size,
      timestamp: new Date().toISOString()
    }));
    return;
  }

  // Stats endpoint
  if (req.url === '/stats') {
    const stats = [];
    docs.forEach((doc, name) => {
      stats.push({
        name,
        connections: doc.conns ? doc.conns.size : 0
      });
    });
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ documents: stats }));
    return;
  }

  res.writeHead(404);
  res.end('Not found');
});

// Create WebSocket server
const wss = new WebSocket.Server({ server });

wss.on('connection', (ws, req) => {
  const url = req.url ? req.url.split('?')[0] : '';

  // Route terminal WebSocket connections to the terminal handler
  if (url.startsWith('/terminal/')) {
    handleTerminalConnection(ws, url, req);
    return;
  }

  // Extract room name from URL path (e.g., /doc-token-here)
  const docName = url.slice(1);

  if (!docName) {
    ws.close(4000, 'Document name required');
    return;
  }

  console.log(`[${new Date().toISOString()}] New connection for document: ${docName}`);

  setupWSConnection(ws, req, { docName });
});

wss.on('error', (error) => {
  console.error('WebSocket server error:', error);
});

server.listen(PORT, HOST, () => {
  console.log(`Collaboration server running on ws://${HOST}:${PORT}`);
  console.log('Health check available at http://' + HOST + ':' + PORT + '/health');
});

// Graceful shutdown
process.on('SIGTERM', async () => {
  console.log('SIGTERM received, closing server...');

  // Save all documents to Redis before shutdown
  const savePromises = [];
  docs.forEach((doc, docName) => {
    console.log(`Saving document before shutdown: ${docName}`);
    savePromises.push(saveToRedis(docName, doc));
  });

  try {
    await Promise.all(savePromises);
    console.log('All documents saved to Redis');

    // Sync all documents to database
    for (const [docName] of docs) {
      await syncToDatabase(docName);
    }
    console.log('All documents synced to database');
  } catch (err) {
    console.error('Error during shutdown save:', err);
  }

  wss.close(() => {
    server.close(() => {
      redis.disconnect();
      console.log('Server closed');
      process.exit(0);
    });
  });
});
