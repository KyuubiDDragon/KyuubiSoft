import { z } from "zod";
import { readCollection, writeCollection } from "./storage.js";
import { newId } from "./id.js";

const COLLECTION = "checklists";

async function loadAll() {
  return readCollection(COLLECTION, { checklists: [] });
}

async function saveAll(state) {
  await writeCollection(COLLECTION, state);
}

function summarise(checklist) {
  const total = checklist.items.length;
  const done = checklist.items.filter((i) => i.done).length;
  return {
    id: checklist.id,
    name: checklist.name,
    description: checklist.description ?? null,
    tags: checklist.tags ?? [],
    progress: { done, total, percent: total === 0 ? 0 : Math.round((done / total) * 100) },
    createdAt: checklist.createdAt,
    updatedAt: checklist.updatedAt,
  };
}

function findChecklist(state, id) {
  const idx = state.checklists.findIndex((c) => c.id === id);
  if (idx === -1) throw new Error(`Checklist not found: ${id}`);
  return idx;
}

function findItem(checklist, itemId) {
  const idx = checklist.items.findIndex((i) => i.id === itemId);
  if (idx === -1) throw new Error(`Item not found: ${itemId}`);
  return idx;
}

export function registerChecklistTools(server) {
  server.tool(
    "checklist_create",
    "Create a new checklist (e.g. for a feature, sprint, or refactor).",
    {
      name: z.string().min(1, "name required"),
      description: z.string().optional(),
      tags: z.array(z.string()).optional(),
      items: z.array(z.string()).optional().describe("Initial item texts."),
    },
    async ({ name, description, tags, items }) => {
      const state = await loadAll();
      const now = new Date().toISOString();
      const checklist = {
        id: newId("cl"),
        name,
        description: description ?? null,
        tags: tags ?? [],
        items: (items ?? []).map((text) => ({
          id: newId("it"),
          text,
          done: false,
          createdAt: now,
          completedAt: null,
        })),
        createdAt: now,
        updatedAt: now,
      };
      state.checklists.push(checklist);
      await saveAll(state);
      return { content: [{ type: "text", text: JSON.stringify(summarise(checklist), null, 2) }] };
    },
  );

  server.tool(
    "checklist_list",
    "List all checklists with progress summaries. Optionally filter by tag.",
    { tag: z.string().optional() },
    async ({ tag }) => {
      const state = await loadAll();
      const items = state.checklists
        .filter((c) => !tag || (c.tags ?? []).includes(tag))
        .map(summarise);
      return { content: [{ type: "text", text: JSON.stringify(items, null, 2) }] };
    },
  );

  server.tool(
    "checklist_get",
    "Fetch a single checklist with all its items.",
    { id: z.string() },
    async ({ id }) => {
      const state = await loadAll();
      const idx = findChecklist(state, id);
      return { content: [{ type: "text", text: JSON.stringify(state.checklists[idx], null, 2) }] };
    },
  );

  server.tool(
    "checklist_add_item",
    "Append an item to a checklist.",
    {
      id: z.string(),
      text: z.string().min(1),
    },
    async ({ id, text }) => {
      const state = await loadAll();
      const idx = findChecklist(state, id);
      const now = new Date().toISOString();
      const item = { id: newId("it"), text, done: false, createdAt: now, completedAt: null };
      state.checklists[idx].items.push(item);
      state.checklists[idx].updatedAt = now;
      await saveAll(state);
      return { content: [{ type: "text", text: JSON.stringify(item, null, 2) }] };
    },
  );

  server.tool(
    "checklist_toggle_item",
    "Toggle an item's done state, or set it explicitly via `done`.",
    {
      id: z.string(),
      itemId: z.string(),
      done: z.boolean().optional(),
    },
    async ({ id, itemId, done }) => {
      const state = await loadAll();
      const idx = findChecklist(state, id);
      const checklist = state.checklists[idx];
      const itemIdx = findItem(checklist, itemId);
      const item = checklist.items[itemIdx];
      const now = new Date().toISOString();
      item.done = typeof done === "boolean" ? done : !item.done;
      item.completedAt = item.done ? now : null;
      checklist.updatedAt = now;
      await saveAll(state);
      return { content: [{ type: "text", text: JSON.stringify(item, null, 2) }] };
    },
  );

  server.tool(
    "checklist_update_item",
    "Edit the text of an item.",
    { id: z.string(), itemId: z.string(), text: z.string().min(1) },
    async ({ id, itemId, text }) => {
      const state = await loadAll();
      const idx = findChecklist(state, id);
      const checklist = state.checklists[idx];
      const itemIdx = findItem(checklist, itemId);
      checklist.items[itemIdx].text = text;
      checklist.updatedAt = new Date().toISOString();
      await saveAll(state);
      return { content: [{ type: "text", text: JSON.stringify(checklist.items[itemIdx], null, 2) }] };
    },
  );

  server.tool(
    "checklist_remove_item",
    "Remove an item from a checklist.",
    { id: z.string(), itemId: z.string() },
    async ({ id, itemId }) => {
      const state = await loadAll();
      const idx = findChecklist(state, id);
      const checklist = state.checklists[idx];
      const itemIdx = findItem(checklist, itemId);
      const [removed] = checklist.items.splice(itemIdx, 1);
      checklist.updatedAt = new Date().toISOString();
      await saveAll(state);
      return { content: [{ type: "text", text: JSON.stringify({ removed }, null, 2) }] };
    },
  );

  server.tool(
    "checklist_delete",
    "Delete an entire checklist.",
    { id: z.string() },
    async ({ id }) => {
      const state = await loadAll();
      const idx = findChecklist(state, id);
      const [removed] = state.checklists.splice(idx, 1);
      await saveAll(state);
      return { content: [{ type: "text", text: JSON.stringify({ deleted: removed.id, name: removed.name }, null, 2) }] };
    },
  );
}
