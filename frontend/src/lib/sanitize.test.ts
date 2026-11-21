// @vitest-environment node
import { describe, expect, it } from "vitest";
import { sanitizeArticleHtml } from "./sanitize";

describe("I1 — sanitizeArticleHtml class allowlist", () => {
  it("keeps editor classes", () => {
    expect(sanitizeArticleHtml('<p class="ql-align-center">x</p>')).toBe('<p class="ql-align-center">x</p>');
    expect(sanitizeArticleHtml('<li class="ql-indent-2">x</li>')).toContain('class="ql-indent-2"');
  });

  it("drops arbitrary (e.g. Tailwind overlay) classes", () => {
    const out = sanitizeArticleHtml('<a href="https://x.test" class="fixed inset-0 z-50 ql-align-right">click</a>');
    expect(out).not.toMatch(/fixed|inset-0|z-50/);
    expect(out).toContain("ql-align-right");
    expect(sanitizeArticleHtml('<div class="fixed inset-0"><p class="sr-only">x</p></div>')).not.toContain("class=");
  });

  it("still strips scripts, handlers and javascript: URLs", () => {
    const out = sanitizeArticleHtml('<img src="x" onerror="alert(1)"><a href="javascript:alert(1)">a</a><script>alert(1)</script><p style="position:fixed">p</p>');
    expect(out).not.toMatch(/onerror|javascript:|<script|style=/);
  });
});
