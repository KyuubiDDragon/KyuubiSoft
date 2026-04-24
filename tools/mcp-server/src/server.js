import express from "express";
import { randomUUID } from "node:crypto";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { isInitializeRequest } from "@modelcontextprotocol/sdk/types.js";

import { registerChecklistTools } from "./checklists.js";
import { registerTimeTrackingTools } from "./timeTracking.js";
import { registerDecisionTools } from "./decisions.js";
import { getDataDir } from "./storage.js";

const PORT = Number.parseInt(process.env.PORT ?? "3333", 10);
const HOST = process.env.HOST ?? "0.0.0.0";
const AUTH_TOKEN = process.env.MCP_AUTH_TOKEN || null;

function buildServer() {
  const server = new McpServer(
    { name: "kyuubisoft-tools", version: "0.1.0" },
    { capabilities: { tools: {} } },
  );
  registerChecklistTools(server);
  registerTimeTrackingTools(server);
  registerDecisionTools(server);
  return server;
}

const transports = new Map();

function checkAuth(req, res) {
  if (!AUTH_TOKEN) return true;
  const header = req.headers.authorization || "";
  const provided = header.startsWith("Bearer ") ? header.slice(7) : null;
  if (provided !== AUTH_TOKEN) {
    res.status(401).json({ jsonrpc: "2.0", error: { code: -32001, message: "Unauthorized" }, id: null });
    return false;
  }
  return true;
}

const app = express();
app.use(express.json({ limit: "4mb" }));

app.get("/health", (_req, res) => {
  res.json({ status: "ok", dataDir: getDataDir(), activeSessions: transports.size });
});

app.post("/mcp", async (req, res) => {
  if (!checkAuth(req, res)) return;
  const sessionId = req.headers["mcp-session-id"];
  let transport = sessionId ? transports.get(sessionId) : undefined;

  if (!transport) {
    if (!isInitializeRequest(req.body)) {
      res.status(400).json({
        jsonrpc: "2.0",
        error: { code: -32000, message: "No valid session. Send an initialize request first." },
        id: null,
      });
      return;
    }
    transport = new StreamableHTTPServerTransport({
      sessionIdGenerator: () => randomUUID(),
      onsessioninitialized: (id) => {
        transports.set(id, transport);
      },
    });
    transport.onclose = () => {
      if (transport.sessionId) transports.delete(transport.sessionId);
    };
    const server = buildServer();
    await server.connect(transport);
  }

  await transport.handleRequest(req, res, req.body);
});

async function handleSessionRequest(req, res) {
  if (!checkAuth(req, res)) return;
  const sessionId = req.headers["mcp-session-id"];
  const transport = sessionId ? transports.get(sessionId) : undefined;
  if (!transport) {
    res.status(404).send("Session not found");
    return;
  }
  await transport.handleRequest(req, res);
}

app.get("/mcp", handleSessionRequest);
app.delete("/mcp", handleSessionRequest);

app.listen(PORT, HOST, () => {
  console.log(`[kyuubisoft-mcp] listening on http://${HOST}:${PORT}/mcp (data: ${getDataDir()})`);
  if (!AUTH_TOKEN) {
    console.log("[kyuubisoft-mcp] WARNING: MCP_AUTH_TOKEN is not set - the server is open.");
  }
});
