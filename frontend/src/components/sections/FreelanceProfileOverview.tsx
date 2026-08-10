import { CheckCircle2, Circle, ExternalLink, MapPin, Briefcase, Link2, CodeXml } from "lucide-react";
import { getTranslations } from "next-intl/server";
import { Badge, Card } from "@/components/ui";
import { getAvatarUrl } from "@/lib/media";
import { FREELANCE_PROFILE_FIELD_KEYS, FREELANCE_PROFILE_FIELD_LABEL_KEYS } from "@/lib/profileFields";
import type { SessionUser } from "@/lib/types";

/**
 * Aperçu du profil freelance tel qu'il sera vu par l'équipe une fois confié
 * à un projet — toujours affiché avec les données réelles déjà saisies
 * (jamais de valeur inventée), complété d'une checklist tant que le profil
 * n'est pas à 100 %. Réservé aux comptes collaborateur (isCollaborator).
 */
export async function FreelanceProfileOverview({ user, locale }: { user: SessionUser; locale: string }) {
  const t = await getTranslations({ locale, namespace: "auth.profile" });
  const complete = user.profileCompletion >= 100;

  return (
    <Card variant="soft" className="overflow-hidden p-0">
      <div className="flex flex-wrap items-start gap-4 p-5">
        {/* eslint-disable-next-line @next/next/no-img-element -- avatar externe (ui-avatars.com) ou média backend */}
        <img
          src={getAvatarUrl(user)}
          alt=""
          className="h-16 w-16 shrink-0 rounded-full border border-(--border-neutral) object-cover"
        />
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <p className="text-lg font-semibold" style={{ fontFamily: "var(--font-heading)" }}>
              {user.fullName ?? user.email}
            </p>
            {complete && (
              <Badge variant="success" className="gap-1">
                <CheckCircle2 size={12} aria-hidden="true" />
                {t("overview.completeBadge")}
              </Badge>
            )}
          </div>
          {user.specialties && user.specialties.length > 0 && (
            <div className="mt-2 flex flex-wrap gap-1.5">
              {user.specialties.map((specialty) => (
                <Badge key={specialty} variant="accent">
                  {specialty}
                </Badge>
              ))}
            </div>
          )}
          <div className="mt-2 flex flex-wrap items-center gap-x-3.5 gap-y-1 text-xs text-(--color-muted)">
            {user.availability && <span>{t("availabilityLabel")}: {user.availability}</span>}
            {user.city && (
              <span className="flex items-center gap-1">
                <MapPin size={12} aria-hidden="true" />
                {user.city}
              </span>
            )}
            {user.yearsOfExperience != null && (
              <span className="flex items-center gap-1">
                <Briefcase size={12} aria-hidden="true" />
                {t("overview.experienceValue", { count: user.yearsOfExperience })}
              </span>
            )}
          </div>
          {user.languages && user.languages.length > 0 && (
            <div className="mt-2 flex flex-wrap gap-1.5">
              {user.languages.map((language) => (
                <Badge key={language} variant="neutral">
                  {language}
                </Badge>
              ))}
            </div>
          )}
        </div>
      </div>

      {user.bio && (
        <p className="whitespace-pre-line border-t border-(--border-neutral) px-5 py-4 text-sm text-(--color-muted)">
          {user.bio}
        </p>
      )}

      {(user.portfolioUrl || user.linkedinUrl || user.githubUrl) && (
        <div className="flex flex-wrap divide-x divide-(--border-neutral) border-t border-(--border-neutral)">
          {user.portfolioUrl && (
            <a
              href={user.portfolioUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1.5 px-5 py-3.5 text-sm font-semibold text-brand-primary hover:underline"
            >
              {t("overview.portfolioLink")}
              <ExternalLink size={13} aria-hidden="true" />
            </a>
          )}
          {user.linkedinUrl && (
            <a
              href={user.linkedinUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1.5 px-5 py-3.5 text-sm font-semibold text-brand-primary hover:underline"
            >
              <Link2 size={14} aria-hidden="true" />
              LinkedIn
            </a>
          )}
          {user.githubUrl && (
            <a
              href={user.githubUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1.5 px-5 py-3.5 text-sm font-semibold text-brand-primary hover:underline"
            >
              <CodeXml size={14} aria-hidden="true" />
              GitHub
            </a>
          )}
        </div>
      )}

      {!complete && (
        <div className="border-t border-(--border-neutral) bg-(--color-surface-muted) p-5">
          <div className="mb-2.5 flex items-center justify-between text-xs">
            <span className="font-semibold">{t("overview.checklistTitle")}</span>
            <span className="font-semibold text-brand-primary">{user.profileCompletion}%</span>
          </div>
          <div className="mb-3.5 h-1.5 w-full overflow-hidden rounded-full bg-brand-light">
            <div
              className="h-full w-full origin-left rounded-full bg-gradient-to-r from-brand-primary to-brand-accent transition-transform duration-300"
              style={{ transform: `scaleX(${user.profileCompletion / 100})` }}
            />
          </div>
          <ul className="flex flex-col gap-1.5">
            {FREELANCE_PROFILE_FIELD_KEYS.map((field) => {
              const missing = user.missingProfileFields.includes(field);
              return (
                <li key={field} className="flex items-center gap-2 text-sm">
                  {missing ? (
                    <Circle size={15} className="shrink-0 opacity-40" aria-hidden="true" />
                  ) : (
                    <CheckCircle2 size={15} className="shrink-0 text-success" aria-hidden="true" />
                  )}
                  <span className={missing ? "text-(--color-muted)" : ""}>{t(FREELANCE_PROFILE_FIELD_LABEL_KEYS[field])}</span>
                </li>
              );
            })}
          </ul>
        </div>
      )}
    </Card>
  );
}
