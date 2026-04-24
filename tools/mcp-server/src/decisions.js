import { z } from "zod";
import { readCollection, writeCollection } from "./storage.js";
import { newId } from "./id.js";

const COLLECTION = "decisions";

async function loadAll() {
  return readCollection(COLLECTION, { decisions: [] });
}

async function saveAll(state) {
  await writeCollection(COLLECTION, state);
}

export function registerDecisionTools(server) {
  server.tool(
    "decision_log",
    "Record an architectural or implementation decision (lightweight ADR).",
    {
      title: z.string().min(1),
      context: z.string().optional(),
      decision: z.string().min(1),
      consequences: z.string().optional(),
      tags: z.array(z.string()).optional(),
    },
    async ({ title, context, decision, consequences, tags }) => {
      const state = await loadAll();
      const entry = {
        id: newId("dec"),
        title,
        context: context ?? null,
        decision,
        consequences: consequences ?? null,
        tags: tags ?? [],
        createdAt: new Date().toISOString(),
      };
      state.decisions.push(entry);
      await saveAll(state);
      return { content: [{ type: "text", text: JSON.stringify(entry, null, 2) }] };
    },
  );

  server.tool(
    "decision_list",
    "List recorded decisions (newest first). Optional tag filter.",
    {
      tag: z.string().optional(),
      limit: z.number().int().positive().max(200).default(50),
    },
    async ({ tag, limit }) => {
      const state = await loadAll();
      const items = state.decisions
        .filter((d) => !tag || (d.tags ?? []).includes(tag))
        .sort((a, b) => (a.createdAt < b.createdAt ? 1 : -1))
        .slice(0, limit);
      return { content: [{ type: "text", text: JSON.stringify(items, null, 2) }] };
    },
  );
}
