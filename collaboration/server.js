const http = require('http');
const WebSocket = require('ws');
const Y = require('yjs');
const { setupWSConnection, docs } = require('./wsHandler');

const PORT = process.env.PORT || 1234;
const HOST = process.env.HOST || '0.0.0.0';

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
  // Extract room name from URL path (e.g., /doc-token-here)
  const docName = req.url.slice(1).split('?')[0];

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
process.on('SIGTERM', () => {
  console.log('SIGTERM received, closing server...');
  wss.close(() => {
    server.close(() => {
      console.log('Server closed');
      process.exit(0);
    });
  });
});
