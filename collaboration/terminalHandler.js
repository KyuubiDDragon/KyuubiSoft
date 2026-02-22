'use strict';

const { Client } = require('ssh2');

// Redis client is shared from server.js via init
let redisClient = null;

function setRedisClient(client) {
  redisClient = client;
}

/**
 * Handle an incoming WebSocket connection for a terminal session.
 * URL format: /terminal/{sessionId}
 *
 * Flow:
 * 1. Parse sessionId from URL
 * 2. Fetch session data from Redis (stored by PHP TerminalController)
 * 3. Open SSH PTY connection
 * 4. Bidirectional proxy: WS <-> SSH stream
 * 5. Delete session key from Redis after connection established
 */
async function handleTerminalConnection(ws, url, req) {
  // Extract sessionId from URL path: /terminal/{sessionId}
  const match = url.match(/^\/terminal\/([a-f0-9-]{36})$/);
  if (!match) {
    ws.close(4000, 'Invalid terminal session URL');
    return;
  }

  const sessionId = match[1];
  const redisKey = `terminal_session:${sessionId}`;

  let sessionData;
  try {
    const raw = await redisClient.get(redisKey);
    if (!raw) {
      ws.close(4001, 'Terminal session not found or expired');
      return;
    }
    sessionData = JSON.parse(raw);
    // Consume the session token immediately (one-time use)
    await redisClient.del(redisKey);
  } catch (err) {
    console.error('[Terminal] Redis error:', err.message);
    ws.close(4002, 'Session lookup failed');
    return;
  }

  const { host, port, username, password, private_key } = sessionData;

  console.log(`[Terminal] Opening SSH PTY session to ${username}@${host}:${port} (session: ${sessionId})`);

  const ssh = new Client();

  // Forward terminal resize messages from client
  let stream = null;

  ws.on('message', (data) => {
    try {
      const msg = JSON.parse(data.toString());

      if (msg.type === 'resize' && stream) {
        stream.setWindow(msg.rows || 24, msg.cols || 80, 0, 0);
        return;
      }

      if (msg.type === 'input' && stream) {
        stream.write(msg.data);
        return;
      }
    } catch {
      // Raw data (legacy input support)
      if (stream) {
        stream.write(data);
      }
    }
  });

  ws.on('close', () => {
    console.log(`[Terminal] WebSocket closed (session: ${sessionId})`);
    ssh.end();
  });

  ssh.on('ready', () => {
    console.log(`[Terminal] SSH connected to ${host} (session: ${sessionId})`);

    ssh.shell({ term: 'xterm-256color', rows: 24, cols: 80 }, (err, sshStream) => {
      if (err) {
        console.error('[Terminal] Shell error:', err.message);
        ws.send(JSON.stringify({ type: 'error', message: err.message }));
        ws.close(4010, 'Shell open failed');
        ssh.end();
        return;
      }

      stream = sshStream;

      // Notify client that connection is established
      ws.send(JSON.stringify({ type: 'connected', host, username }));

      // SSH output → WebSocket
      sshStream.on('data', (chunk) => {
        if (ws.readyState === ws.OPEN) {
          ws.send(JSON.stringify({ type: 'output', data: chunk.toString('base64') }));
        }
      });

      sshStream.stderr.on('data', (chunk) => {
        if (ws.readyState === ws.OPEN) {
          ws.send(JSON.stringify({ type: 'output', data: chunk.toString('base64') }));
        }
      });

      sshStream.on('close', () => {
        console.log(`[Terminal] SSH stream closed (session: ${sessionId})`);
        if (ws.readyState === ws.OPEN) {
          ws.send(JSON.stringify({ type: 'disconnected' }));
          ws.close();
        }
        ssh.end();
      });
    });
  });

  ssh.on('error', (err) => {
    console.error(`[Terminal] SSH error (session: ${sessionId}):`, err.message);
    if (ws.readyState === ws.OPEN) {
      ws.send(JSON.stringify({ type: 'error', message: `SSH connection failed: ${err.message}` }));
      ws.close(4020, 'SSH connection failed');
    }
  });

  // Build SSH connection config
  const sshConfig = {
    host,
    port: parseInt(port, 10) || 22,
    username,
    readyTimeout: 15000,
    keepaliveInterval: 10000,
  };

  if (private_key) {
    sshConfig.privateKey = private_key;
  } else if (password) {
    sshConfig.password = password;
  }

  try {
    ssh.connect(sshConfig);
  } catch (err) {
    console.error('[Terminal] Connect error:', err.message);
    ws.close(4020, 'SSH connect error');
  }
}

module.exports = { handleTerminalConnection, setRedisClient };
