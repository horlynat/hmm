import { describe, expect, it } from "vitest";
import { render, screen } from "@testing-library/react";
import { makeOffer } from "@/components/home/testOffers";
import { OfferVisualOrPhoto } from "./offerHelpers";

/**
 * `OfferVisualOrPhoto` : photo réelle du back-office si présente, sinon
 * l'illustration dessinée existante — jamais les deux à la fois.
 */
describe("OfferVisualOrPhoto", () => {
  it("sans coverImage, affiche l'illustration dessinée (SVG du prototype)", () => {
    const offer = makeOffer({ coverImage: null, slug: "whatsapp", previewType: "chat" });
    render(<OfferVisualOrPhoto offer={offer} sizes="100vw" />);

    expect(document.querySelector("svg")).toBeInTheDocument();
    expect(screen.queryByRole("img")).not.toBeInTheDocument();
  });

  it("avec coverImage, affiche la photo réelle (jamais l'illustration dessinée)", () => {
    const offer = makeOffer({
      coverImage: { id: 1, filePath: "/uploads/offers/photo.jpg", altText: "Une boutique", mimeType: "image/jpeg", size: 1000, uploadedAt: null, type: "image" },
      slug: "whatsapp",
      previewType: "chat",
    });
    render(<OfferVisualOrPhoto offer={offer} sizes="100vw" />);

    const img = screen.getByRole("img");
    expect(img).toHaveAttribute("alt", "Une boutique");
    expect(img.getAttribute("src")).toContain("photo.jpg");
    expect(document.querySelector("svg")).not.toBeInTheDocument();
  });

  it("photo sans texte alternatif saisi : retombe sur le titre de l'offre", () => {
    const offer = makeOffer({
      title: "Assistant IA WhatsApp",
      coverImage: { id: 2, filePath: "/uploads/offers/photo.jpg", altText: null, mimeType: "image/jpeg", size: 1000, uploadedAt: null, type: "image" },
    });
    render(<OfferVisualOrPhoto offer={offer} sizes="100vw" />);

    expect(screen.getByRole("img")).toHaveAttribute("alt", "Assistant IA WhatsApp");
  });

  it("visualSlug explicite : utilisé seulement en repli (coverImage absente)", () => {
    const offer = makeOffer({ coverImage: null, slug: "automatisation", previewType: "flow" });
    render(<OfferVisualOrPhoto offer={offer} visualSlug="automatisation" sizes="100vw" />);

    // Pas d'assertion sur le contenu exact du SVG (markup interne du prototype) :
    // on vérifie seulement qu'une illustration (un SVG) est bien rendue sans erreur.
    expect(document.querySelector("svg")).toBeInTheDocument();
  });
});
