import { describe, expect, it } from "vitest";
import { splitPayment } from "./offerHelpers";

describe("splitPayment — acompte 60 % / solde 40 % (conditions de prestation)", () => {
  it("découpe le prix de l'assistant WhatsApp sans perte ni centime", () => {
    expect(splitPayment(199999)).toEqual({ deposit: 119999, balance: 80000 });
  });

  it("le total est toujours exactement le prix", () => {
    for (const price of [0, 1, 90000, 100000, 120000, 450000, 333333]) {
      const { deposit, balance } = splitPayment(price);
      expect(deposit + balance).toBe(price);
      expect(Number.isInteger(deposit)).toBe(true);
    }
  });
});
