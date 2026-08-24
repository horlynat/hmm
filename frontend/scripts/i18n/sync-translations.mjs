#!/usr/bin/env node
// Garde src/messages/fr.json et src/messages/en.json synchronisés
// automatiquement : écris dans UNE seule langue (fr ou en, peu importe
// laquelle), ce script traduit et complète l'autre via l'API Claude — plus
// jamais de bloc oublié dans une des deux langues.
//
// Usage :
//   node --env-file=.env.local scripts/i18n/sync-translations.mjs
//   (ou : npm run i18n:sync, si ANTHROPIC_API_KEY est déjà dans l'environnement)
//
// Nécessite ANTHROPIC_API_KEY dans .env.local (jamais commité — cf.
// .env.example). Ne fait AUCUN appel réseau si les deux fichiers sont déjà
// synchronisés.
//
// Fonctionnement :
// - Chaque clé de traduction est comparée à son état lors du dernier passage
//   (.translation-state.json, à côté de ce script) via un hash de sa valeur.
// - Clé présente d'un seul côté -> traduite vers le côté manquant.
// - Clé modifiée d'un seul côté depuis le dernier passage -> l'autre côté est
//   retraduit à partir de celui qui a changé.
// - Clé modifiée des deux côtés -> laissée telle quelle (édition manuelle
//   des deux langues assumée intentionnelle, on ne l'écrase pas).
// - La syntaxe ICU ({count, plural, ...}), les interpolations ({name}) et
//   les balises façon HTML (<terms>...</terms>) sont préservées telles
//   quelles par le prompt de traduction — seul le texte naturel est traduit.

import { readFileSync, writeFileSync, existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const MESSAGES_DIR = join(__dirname, "..", "..", "src", "messages");
const STATE_FILE = join(__dirname, ".translation-state.json");
const FR_PATH = join(MESSAGES_DIR, "fr.json");
const EN_PATH = join(MESSAGES_DIR, "en.json");

const LOCALE_NAMES = { fr: "français", en: "anglais" };
const BATCH_SIZE = 40;
const MODEL = "claude-sonnet-5";

function readJson(path) {
  return JSON.parse(readFileSync(path, "utf8"));
}

function writeJson(path, data) {
  writeFileSync(path, JSON.stringify(data, null, 2) + "\n", "utf8");
}

/** Aplatit un objet imbriqué en paires "a.b.c" -> valeur (les arrays sont des feuilles, pas récursés). */
function flatten(obj, prefix = "", out = {}) {
  for (const [key, value] of Object.entries(obj)) {
    const path = prefix ? `${prefix}.${key}` : key;
    if (value !== null && typeof value === "object" && !Array.isArray(value)) {
      flatten(value, path, out);
    } else {
      out[path] = value;
    }
  }
  return out;
}

/** Reconstruit un objet imbriqué à partir de paires "a.b.c" -> valeur. */
function unflatten(flat) {
  const out = {};
  for (const [path, value] of Object.entries(flat)) {
    const keys = path.split(".");
    let cur = out;
    for (let i = 0; i < keys.length - 1; i++) {
      cur = cur[keys[i]] ??= {};
    }
    cur[keys[keys.length - 1]] = value;
  }
  return out;
}

/** Hash non-cryptographique (FNV-ish) — juste pour détecter un changement de valeur entre deux passages. */
function hashValue(value) {
  const str = JSON.stringify(value);
  let h = 0;
  for (let i = 0; i < str.length; i++) {
    h = (Math.imul(31, h) + str.charCodeAt(i)) | 0;
  }
  return h.toString(36);
}

async function translateBatch(entries, fromLocale, toLocale) {
  const apiKey = process.env.ANTHROPIC_API_KEY;
  if (!apiKey) {
    throw new Error(
      "ANTHROPIC_API_KEY manquant.\n" +
        "  1. Ajoute la ligne ANTHROPIC_API_KEY=sk-ant-... dans .env.local (jamais commité)\n" +
        "  2. Relance avec : node --env-file=.env.local scripts/i18n/sync-translations.mjs",
    );
  }

  const system = `Tu traduis des chaînes d'interface utilisateur du ${LOCALE_NAMES[fromLocale]} vers le ${LOCALE_NAMES[toLocale]}, pour le site professionnel d'un développeur full-stack (aussi consultant cybersécurité et technicien assurance), ton direct et professionnel.

Règles strictes, à respecter pour CHAQUE valeur :
- Préserve EXACTEMENT toute syntaxe ICU MessageFormat : {variable}, {count, plural, one {...} other {...}}, {jour, plural, ...}, etc. — ne traduis jamais les noms de variables ni les mots-clés ICU (plural, one, other, =0, offset...), seulement le texte naturel qui les entoure.
- Préserve EXACTEMENT les balises façon HTML : <terms>...</terms>, <privacy>...</privacy>, <link>...</link> — traduis seulement le texte à l'intérieur, jamais le nom de la balise.
- Si la valeur d'entrée est un tableau de chaînes, réponds avec un tableau de même longueur, dans le même ordre, chaque élément traduit individuellement.
- Ne reformule pas, ne raccourcis pas, ne développe pas — une traduction fidèle, pas une réécriture.

Réponds UNIQUEMENT avec un JSON valide : un tableau d'objets {"key": "...", "value": "..."}, dans le même ordre que l'entrée, "value" du même type (string ou array de strings) que la valeur d'entrée correspondante. Aucun texte avant ou après le JSON.`;

  const res = await fetch("https://api.anthropic.com/v1/messages", {
    method: "POST",
    headers: {
      "content-type": "application/json",
      "x-api-key": apiKey,
      "anthropic-version": "2023-06-01",
    },
    body: JSON.stringify({
      model: MODEL,
      max_tokens: 8192,
      system,
      messages: [{ role: "user", content: JSON.stringify(entries) }],
    }),
  });

  if (!res.ok) {
    throw new Error(`Anthropic API ${res.status} ${res.statusText} : ${await res.text()}`);
  }

  const data = await res.json();
  const text = data.content?.[0]?.text ?? "";
  try {
    return JSON.parse(text);
  } catch {
    const match = text.match(/\[[\s\S]*\]/);
    if (!match) throw new Error(`Réponse Claude non-JSON pour ${fromLocale}->${toLocale} :\n${text}`);
    return JSON.parse(match[0]);
  }
}

async function processBatch(entries, fromLocale, toLocale, targetFlat) {
  for (let i = 0; i < entries.length; i += BATCH_SIZE) {
    const chunk = entries.slice(i, i + BATCH_SIZE);
    console.log(`  → ${fromLocale}→${toLocale} : ${chunk.length} clé(s)`);
    const translated = await translateBatch(chunk, fromLocale, toLocale);
    for (const { key, value } of translated) {
      targetFlat[key] = value;
    }
  }
}

async function main() {
  const fr = readJson(FR_PATH);
  const en = readJson(EN_PATH);
  const flatFr = flatten(fr);
  const flatEn = flatten(en);
  const state = existsSync(STATE_FILE) ? readJson(STATE_FILE) : {};

  const allKeys = new Set([...Object.keys(flatFr), ...Object.keys(flatEn)]);
  const toEn = []; // clés à traduire fr -> en
  const toFr = []; // clés à traduire en -> fr

  for (const key of allKeys) {
    const hasFr = key in flatFr;
    const hasEn = key in flatEn;

    if (hasFr && !hasEn) {
      toEn.push({ key, value: flatFr[key] });
      continue;
    }
    if (hasEn && !hasFr) {
      toFr.push({ key, value: flatEn[key] });
      continue;
    }

    const prev = state[key];
    if (!prev) continue; // clé déjà présente des deux côtés, jamais suivie : on la suppose déjà traduite

    const frChanged = prev.fr !== hashValue(flatFr[key]);
    const enChanged = prev.en !== hashValue(flatEn[key]);
    if (frChanged && !enChanged) {
      toEn.push({ key, value: flatFr[key] });
    } else if (enChanged && !frChanged) {
      toFr.push({ key, value: flatEn[key] });
    }
    // Si les deux ont changé : édition manuelle assumée des deux langues, on n'écrase rien.
  }

  if (toEn.length === 0 && toFr.length === 0) {
    console.log("✓ fr.json et en.json déjà synchronisés — aucun appel API.");
  } else {
    if (toEn.length) await processBatch(toEn, "fr", "en", flatEn);
    if (toFr.length) await processBatch(toFr, "en", "fr", flatFr);

    writeJson(FR_PATH, unflatten(flatFr));
    writeJson(EN_PATH, unflatten(flatEn));
    console.log(`✓ Terminé — ${toEn.length} clé(s) fr→en, ${toFr.length} clé(s) en→fr.`);
  }

  // Met à jour l'état pour toutes les clés désormais présentes des deux côtés
  // (y compris celles laissées intactes) — sert de référence au prochain passage.
  const newState = {};
  for (const key of allKeys) {
    if (key in flatFr && key in flatEn) {
      newState[key] = { fr: hashValue(flatFr[key]), en: hashValue(flatEn[key]) };
    }
  }
  writeJson(STATE_FILE, newState);
}

main().catch((err) => {
  console.error("✗", err.message);
  process.exit(1);
});
