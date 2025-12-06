const Y = require('yjs');
const syncProtocol = require('y-protocols/sync');
const awarenessProtocol = require('y-protocols/awareness');
const encoding = require('lib0/encoding');
const decoding = require('lib0/decoding');
const map = require('lib0/map');

// Message types
const messageSync = 0;
const messageAwareness = 1;

// Document storage
const docs = new Map();

// Redis client (set from server.js)
let redis = null;

// Debounce timers for Redis saves
const saveTimers = new Map();
const SAVE_DEBOUNCE_MS = 2000; // Save to Redis 2 seconds after last change

// Backend API URL for syncing to database
const BACKEND_API_URL = process.env.BACKEND_API_URL || 'http://backend:9000';

// Garbage collect inactive documents after 1 hour
const gcTimeout = 60 * 60 * 1000;

/**
 * Set Redis client
 */
const setRedis = (redisClient) => {
  redis = redisClient;
};

/**
 * Save document state to Redis
 */
const saveToRedis = async (docName, doc) => {
  if (!redis) {
    console.warn('Redis not available, skipping save');
    return;
  }

  try {
    // Get the text content based on document type
    const ytext = doc.getText('monaco');
    const content = ytext.toString();

    // Also get XML fragment for rich text
    const xmlFragment = doc.getXmlFragment('prosemirror');

    // Store both formats
    const data = {
      content: content,
      xmlContent: xmlFragment.toString(),
      updatedAt: new Date().toISOString()
    };

    await redis.set(`collab:doc:${docName}`, JSON.stringify(data));
    await redis.expire(`collab:doc:${docName}`, 86400); // Expire after 24 hours

    console.log(`[${new Date().toISOString()}] Saved to Redis: ${docName}`);
  } catch (err) {
    console.error('Error saving to Redis:', err);
  }
};

/**
 * Schedule a debounced save to Redis
 */
const scheduleSave = (docName, doc) => {
  // Clear existing timer
  if (saveTimers.has(docName)) {
    clearTimeout(saveTimers.get(docName));
  }

  // Set new timer
  saveTimers.set(docName, setTimeout(() => {
    saveToRedis(docName, doc);
    saveTimers.delete(docName);
  }, SAVE_DEBOUNCE_MS));
};

/**
 * Sync document from Redis to database via Backend API
 */
const syncToDatabase = async (docName) => {
  if (!redis) {
    console.warn('Redis not available, skipping database sync');
    return;
  }

  try {
    const data = await redis.get(`collab:doc:${docName}`);
    if (!data) {
      console.log(`[${new Date().toISOString()}] No Redis data for: ${docName}`);
      return;
    }

    const parsed = JSON.parse(data);

    // Call backend API to save to database
    const response = await fetch(`${BACKEND_API_URL}/api/v1/documents/public/${docName}/sync`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Internal-Request': 'collaboration-server'
      },
      body: JSON.stringify({
        content: parsed.content,
        xmlContent: parsed.xmlContent
      })
    });

    if (response.ok) {
      console.log(`[${new Date().toISOString()}] Synced to database: ${docName}`);
      // Delete from Redis after successful sync
      await redis.del(`collab:doc:${docName}`);
    } else {
      console.error(`Failed to sync to database: ${response.status}`);
    }
  } catch (err) {
    console.error('Error syncing to database:', err);
  }
};

/**
 * Load document state from Redis if available
 */
const loadFromRedis = async (docName, doc) => {
  if (!redis) return false;

  try {
    const data = await redis.get(`collab:doc:${docName}`);
    if (data) {
      const parsed = JSON.parse(data);

      // Apply text content if document is empty
      const ytext = doc.getText('monaco');
      if (ytext.toString() === '' && parsed.content) {
        ytext.insert(0, parsed.content);
      }

      console.log(`[${new Date().toISOString()}] Loaded from Redis: ${docName}`);
      return true;
    }
  } catch (err) {
    console.error('Error loading from Redis:', err);
  }
  return false;
};

/**
 * Get or create a Y.Doc for the given document name
 */
const getYDoc = (docName) => {
  return map.setIfUndefined(docs, docName, () => {
    const doc = new Y.Doc();
    doc.name = docName;
    doc.conns = new Map();
    doc.awareness = new awarenessProtocol.Awareness(doc);
    doc.awareness.setLocalState(null);

    // Try to load from Redis
    loadFromRedis(docName, doc);

    // Listen for document updates
    doc.on('update', (update, origin) => {
      const encoder = encoding.createEncoder();
      encoding.writeVarUint(encoder, messageSync);
      syncProtocol.writeUpdate(encoder, update);
      const message = encoding.toUint8Array(encoder);

      // Broadcast to all connections except origin
      doc.conns.forEach((_, conn) => {
        if (conn !== origin && conn.readyState === 1) {
          conn.send(message);
        }
      });

      // Schedule save to Redis (debounced)
      scheduleSave(docName, doc);
    });

    // Listen for awareness updates
    doc.awareness.on('update', ({ added, updated, removed }, origin) => {
      const changedClients = added.concat(updated, removed);
      const encoder = encoding.createEncoder();
      encoding.writeVarUint(encoder, messageAwareness);
      encoding.writeVarUint8Array(
        encoder,
        awarenessProtocol.encodeAwarenessUpdate(doc.awareness, changedClients)
      );
      const message = encoding.toUint8Array(encoder);

      // Broadcast to all connections
      doc.conns.forEach((_, conn) => {
        if (conn.readyState === 1) {
          conn.send(message);
        }
      });
    });

    // Set up garbage collection timer
    doc.gcTimer = null;
    doc.resetGcTimer = () => {
      if (doc.gcTimer) {
        clearTimeout(doc.gcTimer);
      }
      doc.gcTimer = setTimeout(async () => {
        if (doc.conns.size === 0) {
          console.log(`[${new Date().toISOString()}] Garbage collecting document: ${docName}`);

          // Save to Redis one last time before GC
          await saveToRedis(docName, doc);

          // Sync to database
          await syncToDatabase(docName);

          docs.delete(docName);
          doc.destroy();
        }
      }, gcTimeout);
    };

    console.log(`[${new Date().toISOString()}] Created document: ${docName}`);
    return doc;
  });
};

/**
 * Handle incoming WebSocket message
 */
const messageHandler = (conn, doc, message) => {
  try {
    const encoder = encoding.createEncoder();
    const decoder = decoding.createDecoder(message);
    const messageType = decoding.readVarUint(decoder);

    switch (messageType) {
      case messageSync:
        encoding.writeVarUint(encoder, messageSync);
        const syncMessageType = syncProtocol.readSyncMessage(decoder, encoder, doc, conn);

        // If sync step 1, send sync step 2
        if (syncMessageType === syncProtocol.messageYjsSyncStep1) {
          // Sync step 2 is already written by readSyncMessage
        }

        if (encoding.length(encoder) > 1) {
          conn.send(encoding.toUint8Array(encoder));
        }
        break;

      case messageAwareness:
        awarenessProtocol.applyAwarenessUpdate(
          doc.awareness,
          decoding.readVarUint8Array(decoder),
          conn
        );
        break;

      default:
        console.warn('Unknown message type:', messageType);
    }
  } catch (err) {
    console.error('Error handling message:', err);
  }
};

/**
 * Setup WebSocket connection for collaboration
 */
const setupWSConnection = (conn, req, { docName }) => {
  const doc = getYDoc(docName);
  doc.conns.set(conn, new Set());

  // Clear garbage collection timer
  if (doc.gcTimer) {
    clearTimeout(doc.gcTimer);
    doc.gcTimer = null;
  }

  // Send sync step 1
  const encoder = encoding.createEncoder();
  encoding.writeVarUint(encoder, messageSync);
  syncProtocol.writeSyncStep1(encoder, doc);
  conn.send(encoding.toUint8Array(encoder));

  // Send current awareness state
  const awarenessStates = doc.awareness.getStates();
  if (awarenessStates.size > 0) {
    const encoder = encoding.createEncoder();
    encoding.writeVarUint(encoder, messageAwareness);
    encoding.writeVarUint8Array(
      encoder,
      awarenessProtocol.encodeAwarenessUpdate(
        doc.awareness,
        Array.from(awarenessStates.keys())
      )
    );
    conn.send(encoding.toUint8Array(encoder));
  }

  conn.on('message', (message) => {
    messageHandler(conn, doc, new Uint8Array(message));
  });

  conn.on('close', async () => {
    console.log(`[${new Date().toISOString()}] Connection closed for document: ${docName}`);

    // Remove awareness state for this connection
    const controlledIds = doc.conns.get(conn);
    doc.conns.delete(conn);

    awarenessProtocol.removeAwarenessStates(
      doc.awareness,
      Array.from(controlledIds || []),
      null
    );

    // Start garbage collection timer if no connections
    if (doc.conns.size === 0) {
      // Save immediately when last user leaves
      await saveToRedis(docName, doc);
      doc.resetGcTimer();
    }
  });

  conn.on('error', (err) => {
    console.error('WebSocket connection error:', err);
  });
};

module.exports = {
  setupWSConnection,
  getYDoc,
  docs,
  setRedis,
  saveToRedis,
  syncToDatabase
};
