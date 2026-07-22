
📂 Arborescence Admin (vue hiérarchique)
Principal
Dashboard (vue globale, KPIs, graphiques)

Contenu
Projets (CRUD, statuts, timeline)

Compétences (skills, catégories, tags)

Articles (blog, publication, brouillons)

Formations (courses, progression, certification)

Expériences (portfolio, historique pro)

Utilisateurs
Admin (gestion des comptes, rôles, permissions)

Clients (liste, activité, sessions)

Contacts (messages reçus, formulaires)

Requests (devis, support, partenariats)

Témoignages (validation, publication, suppression)

Sécurité
Logs (connexions, tentatives échouées)

Sessions (actives, expirées, terminées)

Rôles & Permissions (ROLE_ADMIN, ROLE_USER, etc.)

Audit & Monitoring (qui a fait quoi, quand)

2FA & Authentification (activation, gestion)

Paramètres de sécurité (politiques de mot de passe, verrouillage)

Paramètres
Configuration système (branding, thèmes, langues)

Notifications (emails, push, alertes)

Intégrations externes (API, CRM, Slack, GitHub)

Sauvegardes & restauration (backup, export)

Support
Documentation
FAQ
Contact support


## Matrice complète rôles × permissions

Permission               USER  EDITOR  MODERATOR  MANAGER  ADMIN  SUPER_ADMIN
─────────────────────────────────────────────────────────────────────────────
PROJECT_VIEW              ✅     ✅       ✅         ✅       ✅       ✅
PROJECT_EDIT              ❌     ✅       ✅         ✅       ✅       ✅
PROJECT_DELETE            ❌     ❌       ❌         ✅       ✅       ✅
PROJECT_MANAGE_BUDGET     ❌     ❌       ❌         ✅       ✅       ✅
PROJECT_CHANGE_STATUS     ❌     ✅       ✅         ✅       ✅       ✅
─────────────────────────────────────────────────────────────────────────────
ARTICLE_VIEW              ✅     ✅       ✅         ✅       ✅       ✅
ARTICLE_CREATE            ❌     ✅       ✅         ✅       ✅       ✅
ARTICLE_EDIT              ❌     ✅       ✅         ✅       ✅       ✅
ARTICLE_DELETE            ❌     ❌       ✅         ✅       ✅       ✅
ARTICLE_PUBLISH           ❌     ❌       ✅         ✅       ✅       ✅
─────────────────────────────────────────────────────────────────────────────
USER_VIEW                 ❌     ❌       ✅         ✅       ✅       ✅
USER_EDIT                 ❌     ❌       ❌         ✅       ✅       ✅
USER_DELETE               ❌     ❌       ❌         ❌       ✅       ✅
USER_BAN                  ❌     ❌       ✅         ✅       ✅       ✅
USER_IMPERSONATE          ❌     ❌       ❌         ❌       ❌       ✅
USER_CHANGE_ROLE          ❌     ❌       ❌         ❌       ✅       ✅
─────────────────────────────────────────────────────────────────────────────
CONTACT_VIEW              ❌     ❌       ✅         ✅       ✅       ✅
CONTACT_REPLY             ❌     ❌       ✅         ✅       ✅       ✅
CONTACT_DELETE            ❌     ❌       ❌         ✅       ✅       ✅
─────────────────────────────────────────────────────────────────────────────
QUOTE_VIEW                ❌     ❌       ❌         ✅       ✅       ✅
QUOTE_APPROVE             ❌     ❌       ❌         ✅       ✅       ✅
QUOTE_CONVERT             ❌     ❌       ❌         ❌       ✅       ✅
─────────────────────────────────────────────────────────────────────────────
TESTIMONIAL_APPROVE       ❌     ❌       ✅         ✅       ✅       ✅
TESTIMONIAL_FEATURE       ❌     ❌       ❌         ✅       ✅       ✅
─────────────────────────────────────────────────────────────────────────────
SECURITY_VIEW_LOGS        ❌     ❌       ❌         ❌       ✅       ✅
SECURITY_FORCE_LOGOUT     ❌     ❌       ❌         ❌       ✅       ✅
USER_IMPERSONATE          ❌     ❌       ❌         ❌       ❌       ✅
DASHBOARD_EXPORT          ❌     ❌       ❌         ✅       ✅       ✅
─────────────────────────────────────────────────────────────────────────────
SETTINGS_VIEW_CONFIG      ❌     ❌       ❌         ❌       ✅       ✅
SETTINGS_MANAGE_CONFIG    ❌     ❌       ❌         ❌       ✅       ✅
SETTINGS_VIEW_NOTIFICATIONS   ❌ ❌       ❌         ❌       ✅       ✅
SETTINGS_MANAGE_NOTIFICATIONS ❌ ❌       ❌         ❌       ✅       ✅
SETTINGS_VIEW_INTEGRATIONS    ❌ ❌       ❌         ❌       ✅       ✅
SETTINGS_MANAGE_INTEGRATIONS  ❌ ❌       ❌         ❌       ✅       ✅
SETTINGS_VIEW_BACKUPS     ❌     ❌       ❌         ❌       ✅       ✅
SETTINGS_CREATE_BACKUP    ❌     ❌       ❌         ❌       ✅       ✅
SETTINGS_DOWNLOAD_BACKUP  ❌     ❌       ❌         ❌       ✅       ✅
SETTINGS_DELETE_BACKUP    ❌     ❌       ❌         ❌       ❌       ✅
SETTINGS_RESTORE_BACKUP   ❌     ❌       ❌         ❌       ❌       ✅



## Voters (permissions par objet/ressource)

// ── Project ──────────────────────────────────────────────────────
PROJECT_VIEW             // Voir un projet
PROJECT_EDIT             // Modifier un projet
PROJECT_DELETE           // Supprimer un projet
PROJECT_MANAGE_BUDGET    // Gérer le budget
PROJECT_ADD_EXPENSE      // Ajouter une dépense
PROJECT_ADD_COLLABORATOR // Ajouter un collaborateur
PROJECT_CHANGE_STATUS    // Changer le statut
PROJECT_ARCHIVE          // Archiver un projet

// ── Article / Blog ───────────────────────────────────────────────
ARTICLE_VIEW             // Voir un article (même non publié)
ARTICLE_CREATE           // Créer un article
ARTICLE_EDIT             // Modifier un article
ARTICLE_DELETE           // Supprimer un article
ARTICLE_PUBLISH          // Publier/dépublier un article
ARTICLE_MANAGE_TAGS      // Gérer les tags

// ── Skill ────────────────────────────────────────────────────────
SKILL_CREATE
SKILL_EDIT
SKILL_DELETE
SKILL_REORDER            // Changer l'ordre d'affichage

// ── Course / Formation ───────────────────────────────────────────
COURSE_CREATE
COURSE_EDIT
COURSE_DELETE
COURSE_VALIDATE          // Valider/certifier une formation

// ── User / Compte ────────────────────────────────────────────────
USER_VIEW                // Voir le profil d'un user
USER_EDIT                // Modifier un user
USER_DELETE              // Supprimer un user
USER_BAN                 // Bannir/désactiver un user
USER_IMPERSONATE         // Usurper l'identité
USER_CHANGE_ROLE         // Changer les rôles
USER_RESET_PASSWORD      // Forcer la réinitialisation du mot de passe
USER_VERIFY              // Vérifier manuellement un compte

// ── Contact / Message ────────────────────────────────────────────
CONTACT_VIEW             // Voir les messages de contact
CONTACT_REPLY            // Répondre à un message
CONTACT_DELETE           // Supprimer un message
CONTACT_ARCHIVE          // Archiver un message
CONTACT_MARK_SPAM        // Marquer comme spam

// ── QuoteRequest / Devis ─────────────────────────────────────────
QUOTE_VIEW
QUOTE_EDIT
QUOTE_DELETE
QUOTE_APPROVE            // Accepter un devis
QUOTE_REJECT             // Refuser un devis
QUOTE_CONVERT            // Convertir en projet

// ── Testimonial / Témoignage ─────────────────────────────────────
TESTIMONIAL_VIEW
TESTIMONIAL_APPROVE      // Approuver (rendre public)
TESTIMONIAL_REJECT       // Refuser
TESTIMONIAL_DELETE
TESTIMONIAL_FEATURE      // Mettre en avant

// ── Media / Fichiers ─────────────────────────────────────────────
MEDIA_UPLOAD
MEDIA_DELETE
MEDIA_VIEW_PRIVATE       // Voir les médias non publics

// ── Dashboard / Statistiques ─────────────────────────────────────
DASHBOARD_VIEW
DASHBOARD_VIEW_STATS     // Voir les statistiques
DASHBOARD_EXPORT         // Exporter les données (CSV, PDF)
DASHBOARD_VIEW_LOGS      // Voir les logs de sécurité

// ── Sécurité / Audit ─────────────────────────────────────────────
SECURITY_VIEW_LOGS       // Voir l'historique des connexions
SECURITY_MANAGE_2FA      // Gérer la 2FA
SECURITY_FORCE_LOGOUT    // Déconnecter un user de force
SECURITY_VIEW_IPS        // Voir les IPs des users
SECURITY_MANAGE_SESSIONS // Gérer les sessions actives

// ── Paramètres (Configuration / Notifications / Intégrations / Sauvegardes) ──
SETTINGS_VIEW_CONFIG          // Voir la configuration système (branding, thème, langues)
SETTINGS_MANAGE_CONFIG        // Modifier la configuration système
SETTINGS_VIEW_NOTIFICATIONS   // Voir les préférences de notification par importance
SETTINGS_MANAGE_NOTIFICATIONS // Activer/désactiver un canal de notification
SETTINGS_VIEW_INTEGRATIONS    // Voir les intégrations externes
SETTINGS_MANAGE_INTEGRATIONS  // Créer/modifier/supprimer/tester une intégration
SETTINGS_VIEW_BACKUPS         // Voir la liste des sauvegardes
SETTINGS_CREATE_BACKUP        // Déclencher une sauvegarde
SETTINGS_DOWNLOAD_BACKUP      // Télécharger une sauvegarde
SETTINGS_DELETE_BACKUP        // Supprimer une sauvegarde (SUPER_ADMIN uniquement)
SETTINGS_RESTORE_BACKUP       // Restaurer la base depuis une sauvegarde (SUPER_ADMIN uniquement, action irréversible)

## Attributs Symfony natifs (non personnalisables)

IS_AUTHENTICATED_FULLY        // Connecté sans "remember me"
IS_AUTHENTICATED_REMEMBERED   // Connecté via "remember me"
IS_AUTHENTICATED              // Connecté d'une façon ou d'une autre
IS_ANONYMOUS                  // Non connecté
PUBLIC_ACCESS                 // Toujours autorisé (même anonyme)
IS_IMPERSONATOR               // Est en train d'usurper une identité

