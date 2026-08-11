import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import type { ReactNode } from "react";
import { QuoteWizard } from "./QuoteWizard";
import { submitQuoteRequest, qualifyQuoteRequest, type QualifyQuoteResult } from "@/actions/quote";

/**
 * Filet de sécurité écrit AVANT le découpage de QuoteWizard.tsx en modules
 * plus petits (voir plan de refactor). `t` est mocké en identité — les
 * réponses stockées par le wizard sont des chaînes traduites (pas des clés
 * brutes), donc ce mock garde les comparaisons d'égalité internes
 * (`answers.type === t(key)`) vraies pendant les tests sans dépendre du
 * contenu réel des traductions.
 */
vi.mock("next-intl", () => {
  const t = (key: string) => key;
  (t as unknown as { rich: unknown }).rich = (
    key: string,
    values?: Record<string, (chunks: ReactNode) => ReactNode>,
  ) => {
    const renderer = values ? Object.values(values)[0] : undefined;
    return renderer ? renderer(key) : key;
  };
  return {
    useTranslations: () => t,
    useLocale: () => "fr",
  };
});

// Évite de dépendre du contexte de routing next-intl pour ButtonLink/LegalLink.
vi.mock("@/i18n/navigation", () => ({
  Link: ({ href, children, ...props }: { href: unknown; children: ReactNode }) => (
    <a href={typeof href === "string" ? href : "#"} {...props}>
      {children}
    </a>
  ),
}));

vi.mock("@/actions/quote", () => ({
  submitQuoteRequest: vi.fn(),
  qualifyQuoteRequest: vi.fn(),
}));

const submitQuoteRequestMock = vi.mocked(submitQuoteRequest);
const qualifyQuoteRequestMock = vi.mocked(qualifyQuoteRequest);

const STEP2_FIRST_OPTION: Record<string, string> = {
  typeWeb: "webSiteVitrine",
};

function selectOption(label: string) {
  fireEvent.click(screen.getByText(label));
}

function clickNext() {
  fireEvent.click(screen.getByRole("button", { name: "next" }));
}

/**
 * goNext() est async à l'étape 8 (appel à qualifyQuoteRequest) — on attend que
 * l'indicateur de chargement disparaisse, que la transition vers "ia-qualif"
 * ait eu lieu (cas normal) ou non (honeypot rempli, cf. ce test précis).
 */
async function clickContinueToIa() {
  fireEvent.click(screen.getByRole("button", { name: "continueWithAssistant" }));
  await waitFor(() => expect(screen.queryByText("iaGenerating")).not.toBeInTheDocument());
}

/** Avance des étapes 1 à 6 (inclus), s'arrête sur l'étape 7. */
function fillStepsUpTo7({ type = "typeWeb", budget }: { type?: string; budget?: string } = {}) {
  selectOption(type);
  clickNext();

  if (type !== "typeOther") {
    selectOption(STEP2_FIRST_OPTION[type]);
  }
  clickNext();

  selectOption("sourceGoogle");
  clickNext();

  fireEvent.change(screen.getByPlaceholderText("descPlaceholder"), {
    target: { value: "Une description suffisamment détaillée du projet." },
  });
  clickNext();

  selectOption(budget ?? "budgetLow 500 000 FCFA");
  clickNext();

  selectOption("delaiAsap");
  clickNext();
}

/** Remplit l'étape 7 (nom/email seulement si fournis, càd non déjà pré-remplis) et avance à l'étape 8. */
function completeStep7AndAdvance({
  name,
  email,
  canal = "canalEmail",
  phone,
}: {
  name?: string;
  email?: string;
  canal?: "canalEmail" | "canalWhatsapp" | "canalPhone";
  phone?: string;
} = {}) {
  if (name !== undefined) {
    fireEvent.change(screen.getByPlaceholderText("namePlaceholder"), { target: { value: name } });
  }
  if (email !== undefined) {
    fireEvent.change(screen.getByPlaceholderText("emailPlaceholder"), { target: { value: email } });
  }
  selectOption(canal);
  if (canal !== "canalEmail") {
    fireEvent.change(screen.getByPlaceholderText("phonePlaceholder"), {
      target: { value: phone ?? "+33 6 12 34 56 78" },
    });
  }
  clickNext();
}

/** Chaîne complète jusqu'au récap (étape 8), prêt pour cocher le consentement. */
function completeToStep8(opts: { type?: string; budget?: string } = {}) {
  fillStepsUpTo7(opts);
  completeStep7AndAdvance({ name: "Jean Dupont", email: "jean@example.com" });
}

function acceptConsent() {
  fireEvent.click(screen.getByRole("checkbox"));
}

function skipAllIaQuestions() {
  while (screen.queryByRole("button", { name: "iaSkip" })) {
    fireEvent.click(screen.getByRole("button", { name: "iaSkip" }));
  }
}

describe("QuoteWizard", () => {
  beforeEach(() => {
    // Par défaut, qualifyQuoteRequest échoue — le flux emprunte alors
    // déterministiquement le repli local (computeIaQuestions), qui reste le
    // filet de sécurité vérifié par les tests "computeIaQuestions — ...".
    qualifyQuoteRequestMock.mockResolvedValue({ ok: false, error: "network_error" });
  });

  afterEach(() => {
    submitQuoteRequestMock.mockReset();
    qualifyQuoteRequestMock.mockReset();
  });

  it("bloque le passage à l'étape suivante et affiche une erreur sans réponse", () => {
    render(<QuoteWizard />);
    clickNext();

    expect(screen.getByRole("alert")).toHaveTextContent("errorRequired");
    expect(screen.getByText("step1Question")).toBeInTheDocument();
  });

  it("permet de revenir à une étape antérieure depuis le récapitulatif", () => {
    render(<QuoteWizard />);
    completeToStep8();

    expect(screen.getByText("step8Question")).toBeInTheDocument();
    fireEvent.click(screen.getAllByRole("button", { name: "editStep" })[0]);

    expect(screen.getByText("step1Question")).toBeInTheDocument();
  });

  it("exige un téléphone valide quand le canal de contact n'est pas l'email", () => {
    render(<QuoteWizard />);
    fillStepsUpTo7();
    completeStep7AndAdvance({ name: "Jean Dupont", email: "jean@example.com", canal: "canalPhone", phone: "" });

    expect(screen.getByRole("alert")).toHaveTextContent("errorPhoneRequired");
    expect(screen.getByText("step7Question")).toBeInTheDocument();
  });

  it("le honeypot rempli bloque silencieusement le passage à la qualification IA", async () => {
    render(<QuoteWizard />);
    completeToStep8();
    fireEvent.change(screen.getByLabelText("Ne pas remplir ce champ"), { target: { value: "spam" } });
    acceptConsent();
    await clickContinueToIa();

    expect(screen.getByText("step8Question")).toBeInTheDocument();
    expect(screen.queryByText("iaQuestion")).not.toBeInTheDocument();
    // Le honeypot bloque AVANT tout appel réseau — pas seulement avant la transition d'écran.
    expect(qualifyQuoteRequestMock).not.toHaveBeenCalled();
  });

  it("computeIaQuestions — pose la question 'typeOther' puis la question de clôture (repli)", async () => {
    render(<QuoteWizard />);
    completeToStep8({ type: "typeOther" });
    acceptConsent();
    await clickContinueToIa();

    expect(screen.getByText("iaQuestionOther")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "iaSkip" }));
    expect(screen.getByText("iaQuestionLast")).toBeInTheDocument();
  });

  it("computeIaQuestions — pose la question 'budget à définir' quand le budget n'est pas fixé (repli)", async () => {
    render(<QuoteWizard />);
    completeToStep8({ budget: "budgetToDefine" });
    acceptConsent();
    await clickContinueToIa();

    expect(screen.getByText("iaQuestionBudget")).toBeInTheDocument();
  });

  it("computeIaQuestions — pose la question par défaut sinon, et clôt toujours par la dernière question (repli)", async () => {
    render(<QuoteWizard />);
    completeToStep8();
    acceptConsent();
    await clickContinueToIa();

    expect(screen.getByText("iaQuestionDefault")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "iaSkip" }));
    expect(screen.getByText("iaQuestionLast")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "iaSkip" }));
    expect(screen.getByText("iaThanks")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "sendRequest" })).toBeInTheDocument();
  });

  it("utilise les questions générées dynamiquement par qualifyQuoteRequest quand l'appel réussit", async () => {
    qualifyQuoteRequestMock.mockResolvedValueOnce({
      ok: true,
      questions: ["Question générée 1 ?", "Question générée 2 ?"],
    });
    render(<QuoteWizard />);
    completeToStep8();
    acceptConsent();
    await clickContinueToIa();

    expect(screen.getByText("Question générée 1 ?")).toBeInTheDocument();
    expect(screen.queryByText("iaQuestionDefault")).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: "iaSkip" }));
    expect(screen.getByText("Question générée 2 ?")).toBeInTheDocument();
  });

  it("désactive le bouton et affiche un indicateur pendant la génération des questions", async () => {
    let resolveQualify!: (value: QualifyQuoteResult) => void;
    qualifyQuoteRequestMock.mockImplementationOnce(
      () =>
        new Promise((resolve) => {
          resolveQualify = resolve;
        }),
    );
    render(<QuoteWizard />);
    completeToStep8();
    acceptConsent();

    fireEvent.click(screen.getByRole("button", { name: "continueWithAssistant" }));

    await waitFor(() => {
      expect(screen.getByRole("button", { name: "iaGenerating" })).toBeDisabled();
    });

    resolveQualify({ ok: true, questions: ["Question générée ?"] });

    await waitFor(() => expect(screen.getByText("Question générée ?")).toBeInTheDocument());
  });

  it("enregistre une clarification quand on répond, mais pas quand on passe", async () => {
    submitQuoteRequestMock.mockResolvedValueOnce({ ok: true });
    render(<QuoteWizard />);
    completeToStep8();
    acceptConsent();
    await clickContinueToIa();

    fireEvent.change(screen.getByPlaceholderText("iaAnswerPlaceholder"), {
      target: { value: "Réponse détaillée du client." },
    });
    fireEvent.click(screen.getByRole("button", { name: "iaAnswer" }));
    fireEvent.click(screen.getByRole("button", { name: "iaSkip" }));
    fireEvent.click(screen.getByRole("button", { name: "sendRequest" }));

    await waitFor(() => expect(submitQuoteRequestMock).toHaveBeenCalledTimes(1));
    expect(submitQuoteRequestMock).toHaveBeenCalledWith(
      expect.objectContaining({
        clarifications: [{ question: "iaQuestionDefault", answer: "Réponse détaillée du client." }],
      }),
    );
  });

  it("affiche l'écran de succès quand la soumission réussit", async () => {
    submitQuoteRequestMock.mockResolvedValueOnce({ ok: true });
    render(<QuoteWizard />);
    completeToStep8();
    acceptConsent();
    await clickContinueToIa();
    skipAllIaQuestions();

    fireEvent.click(screen.getByRole("button", { name: "sendRequest" }));

    await waitFor(() => {
      expect(screen.getByRole("status")).toHaveTextContent("successTitle");
    });
  });

  it("affiche une erreur et reste sur l'étape IA quand la soumission échoue", async () => {
    submitQuoteRequestMock.mockResolvedValueOnce({ ok: false, error: "server_error" });
    render(<QuoteWizard />);
    completeToStep8();
    acceptConsent();
    await clickContinueToIa();
    skipAllIaQuestions();

    fireEvent.click(screen.getByRole("button", { name: "sendRequest" }));

    await waitFor(() => {
      expect(screen.getByRole("alert")).toHaveTextContent("errorSubmit");
    });
    expect(screen.queryByRole("status")).not.toBeInTheDocument();
  });

  it("pré-remplit le nom et l'email à l'étape 7 depuis initialName/initialEmail", () => {
    render(<QuoteWizard initialName="Jean" initialEmail="jean@test.com" />);
    fillStepsUpTo7();

    expect(screen.getByPlaceholderText("namePlaceholder")).toHaveValue("Jean");
    expect(screen.getByPlaceholderText("emailPlaceholder")).toHaveValue("jean@test.com");
  });
});
