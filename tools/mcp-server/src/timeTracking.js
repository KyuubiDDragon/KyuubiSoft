import { z } from "zod";
import { readCollection, writeCollection } from "./storage.js";
import { newId } from "./id.js";

const COLLECTION = "time_tracking";

async function loadAll() {
  return readCollection(COLLECTION, { entries: [], activeTimers: [] });
}

async function saveAll(state) {
  await writeCollection(COLLECTION, state);
}

function minutesBetween(startIso, endIso) {
  const start = new Date(startIso).getTime();
  const end = new Date(endIso).getTime();
  return Math.max(0, Math.round((end - start) / 60000));
}

function startOfPeriod(period) {
  const now = new Date();
  if (period === "today") {
    const d = new Date(now);
    d.setHours(0, 0, 0, 0);
    return d.toISOString();
  }
  if (period === "week") {
    const d = new Date(now);
    const day = (d.getDay() + 6) % 7; // Monday = 0
    d.setDate(d.getDate() - day);
    d.setHours(0, 0, 0, 0);
    return d.toISOString();
  }
  return null;
}

export function registerTimeTrackingTools(server) {
  server.tool(
    "timer_start",
    "Start a running timer for a task. Returns the timer id.",
    {
      task: z.string().min(1),
      tags: z.array(z.string()).optional(),
      note: z.string().optional(),
    },
    async ({ task, tags, note }) => {
      const state = await loadAll();
      const timer = {
        id: newId("tm"),
        task,
        tags: tags ?? [],
        note: note ?? null,
        startedAt: new Date().toISOString(),
      };
      state.activeTimers.push(timer);
      await saveAll(state);
      return { content: [{ type: "text", text: JSON.stringify(timer, null, 2) }] };
    },
  );

  server.tool(
    "timer_stop",
    "Stop a running timer (default: the most recently started one) and store the elapsed time as an entry.",
    { id: z.string().optional() },
    async ({ id }) => {
      const state = await loadAll();
      if (state.activeTimers.length === 0) {
        throw new Error("No active timer running");
      }
      let idx;
      if (id) {
        idx = state.activeTimers.findIndex((t) => t.id === id);
        if (idx === -1) throw new Error(`Active timer not found: ${id}`);
      } else {
        idx = state.activeTimers.length - 1;
      }
      const timer = state.activeTimers[idx];
      const stoppedAt = new Date().toISOString();
      const minutes = minutesBetween(timer.startedAt, stoppedAt);
      const entry = {
        id: newId("en"),
        task: timer.task,
        tags: timer.tags,
        note: timer.note,
        startedAt: timer.startedAt,
        stoppedAt,
        minutes,
        source: "timer",
      };
      state.entries.push(entry);
      state.activeTimers.splice(idx, 1);
      await saveAll(state);
      return { content: [{ type: "text", text: JSON.stringify(entry, null, 2) }] };
    },
  );

  server.tool(
    "timer_active",
    "List all currently running timers.",
    {},
    async () => {
      const state = await loadAll();
      return { content: [{ type: "text", text: JSON.stringify(state.activeTimers, null, 2) }] };
    },
  );

  server.tool(
    "time_log",
    "Manually log time for a task (no running timer needed).",
    {
      task: z.string().min(1),
      minutes: z.number().int().positive(),
      date: z.string().optional().describe("ISO date or datetime (defaults to now)."),
      tags: z.array(z.string()).optional(),
      note: z.string().optional(),
    },
    async ({ task, minutes, date, tags, note }) => {
      const state = await loadAll();
      const stoppedAt = date ? new Date(date).toISOString() : new Date().toISOString();
      const startedAt = new Date(new Date(stoppedAt).getTime() - minutes * 60000).toISOString();
      const entry = {
        id: newId("en"),
        task,
        tags: tags ?? [],
        note: note ?? null,
        startedAt,
        stoppedAt,
        minutes,
        source: "manual",
      };
      state.entries.push(entry);
      await saveAll(state);
      return { content: [{ type: "text", text: JSON.stringify(entry, null, 2) }] };
    },
  );

  server.tool(
    "time_report",
    "Aggregate time entries grouped by task. Period: today | week | all.",
    {
      period: z.enum(["today", "week", "all"]).default("all"),
      task: z.string().optional(),
      tag: z.string().optional(),
    },
    async ({ period, task, tag }) => {
      const state = await loadAll();
      const cutoff = startOfPeriod(period);
      const filtered = state.entries.filter((e) => {
        if (cutoff && e.stoppedAt < cutoff) return false;
        if (task && e.task !== task) return false;
        if (tag && !(e.tags ?? []).includes(tag)) return false;
        return true;
      });
      const byTask = new Map();
      for (const e of filtered) {
        const cur = byTask.get(e.task) ?? { task: e.task, minutes: 0, entries: 0 };
        cur.minutes += e.minutes;
        cur.entries += 1;
        byTask.set(e.task, cur);
      }
      const totalMinutes = filtered.reduce((sum, e) => sum + e.minutes, 0);
      const report = {
        period,
        totalMinutes,
        totalHours: Math.round((totalMinutes / 60) * 100) / 100,
        entries: filtered.length,
        byTask: [...byTask.values()].sort((a, b) => b.minutes - a.minutes),
      };
      return { content: [{ type: "text", text: JSON.stringify(report, null, 2) }] };
    },
  );

  server.tool(
    "time_entries",
    "List raw time entries (most recent first). Period: today | week | all.",
    {
      period: z.enum(["today", "week", "all"]).default("today"),
      limit: z.number().int().positive().max(500).default(50),
    },
    async ({ period, limit }) => {
      const state = await loadAll();
      const cutoff = startOfPeriod(period);
      const filtered = state.entries
        .filter((e) => !cutoff || e.stoppedAt >= cutoff)
        .sort((a, b) => (a.stoppedAt < b.stoppedAt ? 1 : -1))
        .slice(0, limit);
      return { content: [{ type: "text", text: JSON.stringify(filtered, null, 2) }] };
    },
  );

  server.tool(
    "time_entry_delete",
    "Delete a time entry by id.",
    { id: z.string() },
    async ({ id }) => {
      const state = await loadAll();
      const idx = state.entries.findIndex((e) => e.id === id);
      if (idx === -1) throw new Error(`Entry not found: ${id}`);
      const [removed] = state.entries.splice(idx, 1);
      await saveAll(state);
      return { content: [{ type: "text", text: JSON.stringify({ deleted: removed.id }, null, 2) }] };
    },
  );
}
