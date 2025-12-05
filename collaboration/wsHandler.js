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

// Garbage collect inactive documents after 1 hour
const gcTimeout = 60 * 60 * 1000;

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
      doc.gcTimer = setTimeout(() => {
        if (doc.conns.size === 0) {
          console.log(`[${new Date().toISOString()}] Garbage collecting document: ${docName}`);
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

  conn.on('close', () => {
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
  docs
};
