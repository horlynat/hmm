import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { navItems, siteConfig } from "@/config/site";
import { Logo } from "@/components/ui";

export async function Footer() {
  const t = await getTranslations("nav");
  const tf = await getTranslations("footer");

  return (
    <footer className="bg-brand-dark px-6 py-11 text-brand-light">
      <div className="mx-auto flex max-w-[1120px] flex-wrap items-center justify-between gap-6">
        <Link
          href="/"
          className="flex items-center gap-2 text-lg font-extrabold text-brand-light"
          style={{ fontFamily: "var(--font-heading)" }}
        >
          <Logo />
          Horlynat
        </Link>
        <ul className="flex flex-wrap gap-6">
          {navItems.map((item) => (
            <li key={item.href} className="list-none">
              <Link href={item.href} className="text-sm opacity-75 hover:opacity-100">
                {t(item.key)}
              </Link>
            </li>
          ))}
        </ul>
      </div>
      <div className="mx-auto mt-4 flex max-w-[1120px] flex-wrap gap-6 font-mono text-xs">
        <Link href="/mentions-legales" className="opacity-65 hover:opacity-100">
          {tf("mentionsLegales")}
        </Link>
        <a
          href={siteConfig.social.linkedin}
          target="_blank"
          rel="noopener"
          className="opacity-65 hover:opacity-100"
        >
          LinkedIn
        </a>
        <a
          href={siteConfig.social.github}
          target="_blank"
          rel="noopener"
          className="opacity-65 hover:opacity-100"
        >
          GitHub
        </a>
      </div>
      <p className="mx-auto mt-7 max-w-[1120px] font-mono text-xs opacity-50">
        {tf("note")}
      </p>
    </footer>
  );
}
