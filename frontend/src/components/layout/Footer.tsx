import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";
import { navItems, siteConfig, type NavHref } from "@/config/site";
import { Logo } from "@/components/ui";
import { FooterAccountLink } from "./FooterAccountLink";

function LinkedinIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 448 512" width="18" height="18" fill="currentColor">
      <path d="M100.28 448H7.4V148.9h92.88zm-46.44-338.4C24.09 109.6 0 85.15 0 54.44 0 23.94 24.05 0 53.84 0s53.84 23.94 53.84 54.44c0 30.71-23.94 55.16-53.84 55.16zM447.9 448h-92.68V302.4c0-34.7-.7-79.3-48.29-79.3-48.29 0-55.69 37.7-55.69 76.7V448h-92.78V148.9h89.08v40.8h1.3c12.4-23.5 42.69-48.3 87.88-48.3 94 0 111.28 61.9 111.28 142.3V448z" />
    </svg>
  );
}

function FacebookIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
      <path d="M22 12.06C22 6.505 17.523 2 12 2S2 6.505 2 12.06c0 5.022 3.657 9.184 8.438 9.94v-7.03H7.898v-2.91h2.54V9.845c0-2.507 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562v1.876h2.773l-.443 2.91h-2.33V22c4.78-.756 8.437-4.918 8.437-9.94z" />
    </svg>
  );
}

function GithubIcon() {
  return (
    <svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M12.006 2a9.847 9.847 0 0 0-6.484 2.44 10.32 10.32 0 0 0-3.393 6.17 10.48 10.48 0 0 0 1.317 6.955 10.045 10.045 0 0 0 5.4 4.418c.504.095.683-.223.683-.494 0-.245-.01-1.052-.014-1.908-2.78.62-3.366-1.21-3.366-1.21a2.711 2.711 0 0 0-1.11-1.5c-.907-.637.07-.621.07-.621.317.044.62.163.885.346.266.183.487.426.647.71.135.253.318.476.538.655a2.079 2.079 0 0 0 2.37.196c.045-.52.27-1.006.635-1.37-2.219-.259-4.554-1.138-4.554-5.07a4.022 4.022 0 0 1 1.031-2.75 3.77 3.77 0 0 1 .096-2.713s.839-.275 2.749 1.05a9.26 9.26 0 0 1 5.004 0c1.906-1.325 2.74-1.05 2.74-1.05.37.858.406 1.828.101 2.713a4.017 4.017 0 0 1 1.029 2.75c0 3.939-2.339 4.805-4.564 5.058a2.471 2.471 0 0 1 .679 1.897c0 1.372-.012 2.477-.012 2.814 0 .272.18.592.687.492a10.05 10.05 0 0 0 5.388-4.421 10.473 10.473 0 0 0 1.313-6.948 10.32 10.32 0 0 0-3.39-6.165A9.847 9.847 0 0 0 12.007 2Z"
      />
    </svg>
  );
}

export async function Footer({ locale }: { locale: string }) {
  const t = await getTranslations({ locale, namespace: "nav" });
  const tc = await getTranslations({ locale, namespace: "common" });
  const tf = await getTranslations({ locale, namespace: "footer" });
  const th = await getTranslations({ locale, namespace: "home" });
  const tl = await getTranslations({ locale, namespace: "legal" });
  const year = new Date().getFullYear();

  const legalLinks: { href: NavHref; label: string }[] = [
    { href: "/mentions-legales", label: tl("mentions.title") },
    { href: "/politique-de-confidentialite", label: tl("privacy.title") },
    { href: "/politique-de-cookies", label: tl("cookies.title") },
    { href: "/conditions-generales", label: tl("terms.title") },
  ];

  const socialLinks = [
    { href: siteConfig.social.linkedin, label: "LinkedIn", Icon: LinkedinIcon },
    { href: siteConfig.social.github, label: "GitHub", Icon: GithubIcon },
    { href: siteConfig.social.facebook, label: "Facebook", Icon: FacebookIcon },
  ];

  return (
    <footer className="bg-[var(--footer-bg)] text-[var(--footer-text)]">
      <div className="mx-auto max-w-[1120px] px-6 pt-12 pb-10">
        <div className="md:flex md:items-start md:justify-between">
          <div className="mb-8 md:mb-0">
            
            <Link
              href="/"
              aria-label={tc("siteName")}
              className="flex items-center gap-3"
            >
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-sm)] bg-brand-primary text-[var(--color-on-brand-primary)]">
                <Logo className="h-6 w-6" />
              </span>
              <span
                className="text-xl font-semibold whitespace-nowrap text-[var(--footer-text)]"
                style={{ fontFamily: "var(--font-heading)" }}
              >
                {tc("siteName")}
              </span>
            </Link>

            <p className="mt-4 max-w-[38ch] text-sm text-[var(--color-footer-muted)]">
              {tf("note")}
            </p>

            <div className="flex gap-4 mt-8">
              {socialLinks.map(({ href, label, Icon }) => (
                <a
                  key={label}
                  href={href}
                  target="_blank"
                  rel="noopener"
                  aria-label={label}
                  className="text-[var(--color-footer-muted)] transition-colors hover:text-[var(--footer-text)]"
                >
                  <Icon />
                </a>
              ))}
            </div>

          </div>

          <div className="grid grid-cols-2 gap-8 sm:gap-12">
            <div>
              <div className="mb-4 font-mono text-xs uppercase tracking-wide text-[var(--color-footer-muted)]">
                {tf("navHeading")}
              </div>
              <ul className="space-y-3">
                {navItems.map((item) => (
                  <li key={item.href} className="list-none">
                    <Link
                      href={item.href}
                      className="text-sm transition-colors hover:text-brand-accent"
                    >
                      {t(item.key)}
                    </Link>
                  </li>
                ))}
                <li className="list-none">
                  <Link
                    href="/freelances"
                    className="text-sm transition-colors hover:text-brand-accent"
                  >
                    {th("ctaFreelance")}
                  </Link>
                </li>
                <li className="list-none">
                  <FooterAccountLink />
                </li>
              </ul>
            </div>

            <div>
              <div className="mb-4 font-mono text-xs uppercase tracking-wide text-[var(--color-footer-muted)]">
                {tf("legalHeading")}
              </div>
              <ul className="space-y-3">
                {legalLinks.map((item) => (
                  <li key={item.href} className="list-none">
                    <Link
                      href={item.href}
                      className="text-sm transition-colors hover:text-brand-accent"
                    >
                      {item.label}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          </div>
        </div>

        <hr className="my-8 border-white/10" />

        {/* Icônes sociales groupées avec le copyright (pas en bord droit) : les
            widgets flottants (WhatsApp, assistant IA) sont fixes en bas à
            droite de l'écran et masqueraient des liens placés là. */}
        <div className="flex flex-wrap items-center gap-x-6 gap-y-4">
          <p className="font-mono text-xs text-[var(--color-footer-muted)]">
            © {year} {tc("siteName")} — {tf("rights")}
          </p>
        </div>
      </div>
    </footer>
  );
}
