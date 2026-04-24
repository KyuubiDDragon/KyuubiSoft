import { promises as fs } from "node:fs";
import path from "node:path";

const DATA_DIR = process.env.MCP_DATA_DIR || "/data";

const writeQueues = new Map();

async function ensureDataDir() {
  await fs.mkdir(DATA_DIR, { recursive: true });
}

function filePath(name) {
  return path.join(DATA_DIR, `${name}.json`);
}

export async function readCollection(name, fallback) {
  await ensureDataDir();
  try {
    const raw = await fs.readFile(filePath(name), "utf8");
    return JSON.parse(raw);
  } catch (err) {
    if (err.code === "ENOENT") return fallback;
    throw err;
  }
}

export async function writeCollection(name, data) {
  await ensureDataDir();
  const previous = writeQueues.get(name) || Promise.resolve();
  const next = previous.then(async () => {
    const target = filePath(name);
    const tmp = `${target}.${process.pid}.${Date.now()}.tmp`;
    const payload = JSON.stringify(data, null, 2);
    await fs.writeFile(tmp, payload, "utf8");
    await fs.rename(tmp, target);
  }).catch((err) => {
    writeQueues.delete(name);
    throw err;
  });
  writeQueues.set(name, next);
  return next;
}

export function getDataDir() {
  return DATA_DIR;
}
