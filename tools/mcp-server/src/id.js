import { randomBytes } from "node:crypto";

export function newId(prefix) {
  const slug = randomBytes(6).toString("base64url");
  return prefix ? `${prefix}_${slug}` : slug;
}
