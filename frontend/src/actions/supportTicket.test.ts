// @vitest-environment node
import { beforeEach, describe, expect, it, vi } from "vitest";
import { fetchCalls, installFetch, jsonResponse, requestHeaders, resetServerMocks, setFetch } from "@/test/serverMocks";

vi.mock("next/headers", async () => {
  const m = await import("@/test/serverMocks");
  return { headers: async () => new Headers(m.requestHeaders), cookies: async () => m.cookieStore };
});

import { submitContactMessage } from "./contact";
import { replySupportTicket, submitSupportTicket, viewSupportTicket } from "./supportTicket";

const TOKEN = "a".repeat(64);
let ip = 0;

beforeEach(() => {
  resetServerMocks();
  installFetch();
  // IP distincte par test : le limiteur en mémoire est partagé par le module.
  requestHeaders["cf-connecting-ip"] = `198.51.100.${++ip}`;
});

describe("F1 — support ticket token", () => {
  it.each(["../users/1", "..", `${TOKEN}/../../admin`, "A".repeat(64), "x", `${"a".repeat(63)}%2F`])(
    "rejects token %s without any backend call",
    async (evil) => {
      expect(await viewSupportTicket(evil)).toBeNull();
      expect(await replySupportTicket(evil, "message assez long")).toEqual({ ok: false, error: "invalid_input" });
      expect(fetchCalls).toHaveLength(0);
    },
  );

  it("M1 — viewing a ticket forwards the visitor IP and is never cached", async () => {
    setFetch(async () => jsonResponse({ subject: "s", messages: [] }));
    await viewSupportTicket(TOKEN);
    expect(fetchCalls[0].url).toMatch(new RegExp(`/support_tickets/${TOKEN}$`));
    expect(fetchCalls[0].init.headers["X-Forwarded-For"]).toBe(`198.51.100.${ip}`);
    expect(fetchCalls[0].init.cache).toBe("no-store");
  });
});

describe("I2 — only validated data is sent", () => {
  it("submitSupportTicket strips unknown keys", async () => {
    await submitSupportTicket({ name: "  Jo ", email: "jo@x.io", subject: "S", message: "message assez long", status: "closed", user: "/api/users/1" } as never);
    expect(JSON.parse(String(fetchCalls[0].init.body))).toEqual({ name: "Jo", email: "jo@x.io", subject: "S", message: "message assez long" });
  });

  it("submitContactMessage strips unknown keys", async () => {
    await submitContactMessage({ source: "x", name: "Jo", email: "jo@x.io", subject: "S", message: "m", isRead: true } as never);
    expect(JSON.parse(String(fetchCalls[0].init.body))).not.toHaveProperty("isRead");
  });
});
