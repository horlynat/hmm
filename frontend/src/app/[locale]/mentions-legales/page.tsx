import { getTranslations } from "next-intl/server";

export default async function LegalNoticePage() {
  const t = await getTranslations("legal");

  const sections: [string, string][] = [
    [t("editorTitle"), t("editorText")],
    [t("hostingTitle"), t("hostingText")],
    [t("ipTitle"), t("ipText")],
  ];

  const privacySections: [string, string][] = [
    [t("dataTitle"), t("dataText")],
    [t("purposeTitle"), t("purposeText")],
    [t("rightsTitle"), t("rightsText")],
    [t("cookiesTitle"), t("cookiesText")],
  ];

  return (
    <section className="px-6 py-16">
      <div className="mx-auto max-w-[840px]">
        <h1 className="mb-2 text-[clamp(1.7rem,3.5vw,2.3rem)]">{t("title")}</h1>
        <p className="mb-10 font-mono text-sm opacity-65">{t("lastUpdate")}</p>

        {sections.map(([title, text]) => (
          <div key={title} className="mb-9 border-b border-[var(--border-softer)] pb-8">
            <h2 className="mb-3 text-xl font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
              {title}
            </h2>
            <p className="text-sm opacity-78">{text}</p>
          </div>
        ))}

        <div className="mb-9 border-b border-[var(--border-softer)] pb-8">
          <h2 className="mb-3 text-xl font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
            {t("privacyTitle")}
          </h2>
          <p className="mb-5 text-sm opacity-78">{t("privacyText")}</p>
          {privacySections.map(([title, text]) => (
            <div key={title} className="mb-4">
              <h3 className="mb-1.5 text-base font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
                {title}
              </h3>
              <p className="text-sm opacity-78">{text}</p>
            </div>
          ))}
        </div>

        <div>
          <h2 className="mb-3 text-xl font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
            {t("aiTitle")}
          </h2>
          <p className="text-sm opacity-78">{t("aiText")}</p>
        </div>
      </div>
    </section>
  );
}
