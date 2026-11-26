import { beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("server-only", () => ({}));
const apiFetch = vi.fn();
vi.mock("./client", async (importActual) => ({
  ...(await importActual<typeof import("./client")>()),
  apiFetch: (...args: unknown[]) => apiFetch(...args),
}));

const { getSkillDomains } = await import("./skill-domains");

const row = {
  id: 1,
  position: 1,
  icon: "lock",
  title: "Cybersécurité",
  titleEn: "Cybersecurity",
  short: "La sécurité posée dès la conception.",
  shortEn: null,
  text: "Texte FR",
  textEn: "Text EN",
  doItems: ["Audits", "Durcissement"],
  doItemsEn: [],
  tools: ["OWASP", "RBAC"],
  toolsEn: null,
  offerSlugs: ["audit"],
  proofProjectSlug: "portail",
};

describe("getSkillDomains — domaines de compétence du back-office", () => {
  beforeEach(() => apiFetch.mockReset());

  it("résout la langue, avec repli sur le français champ par champ", async () => {
    apiFetch.mockResolvedValue({ member: [row] });

    const [en] = (await getSkillDomains("en")) ?? [];
    expect(en).toEqual({
      icon: "lock",
      title: "Cybersecurity",
      short: "La sécurité posée dès la conception.",
      text: "Text EN",
      do: ["Audits", "Durcissement"],
      tools: ["OWASP", "RBAC"],
      offerSlugs: ["audit"],
      proofProjectSlug: "portail",
    });
    const [fr] = (await getSkillDomains("fr")) ?? [];
    expect(fr.title).toBe("Cybersécurité");
    expect(apiFetch).toHaveBeenCalledWith("/skill_domains", expect.objectContaining({ tags: ["skill-domains"] }));
  });

  it("null si l'API ne répond pas (jamais confondu avec « aucun domaine »)", async () => {
    apiFetch.mockResolvedValue(null);
    expect(await getSkillDomains("fr")).toBeNull();

    apiFetch.mockResolvedValue({ member: [] });
    expect(await getSkillDomains("fr")).toEqual([]);
  });

  it("ne suppose rien de la forme : icône inconnue, listes et textes non-chaînes, domaine sans titre", async () => {
    apiFetch.mockResolvedValue({
      member: [
        { ...row, icon: "<script>", doItems: ["ok", 3, null, " "], tools: "PHP", offerSlugs: [{}], proofProjectSlug: 42, text: { x: 1 } },
        { ...row, title: "  " },
      ],
    });

    const domains = (await getSkillDomains("fr")) ?? [];
    expect(domains).toHaveLength(1);
    expect(domains[0]).toMatchObject({ icon: "arch", do: ["ok"], tools: [], offerSlugs: [], proofProjectSlug: null, text: "" });
  });
});
