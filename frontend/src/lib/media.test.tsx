import { describe, expect, it } from "vitest";
import { render } from "@testing-library/react";
import { Avatar } from "@/components/ui/Avatar";
import { getAvatarImageUrl, getInitials } from "./media";

describe("F5 — avatars generated locally", () => {
  it("computes initials from the name, else the e-mail local part", () => {
    expect(getInitials({ fullName: "Jean-Pierre Dupont" })).toBe("JD");
    expect(getInitials({ fullName: "  élodie  " })).toBe("ÉL");
    expect(getInitials({ fullName: null, email: "marie.curie@example.com" })).toBe("MC");
    expect(getInitials({ fullName: "", email: "" })).toBe("?");
    expect(getInitials({ fullName: "<script>" })).toBe("SC");
  });

  it("no longer builds third-party avatar URLs", () => {
    expect(getAvatarImageUrl({ profileImage: null })).toBeNull();
    expect(getAvatarImageUrl({ profileImage: "/uploads/a.jpg" })).toMatch(/\/uploads\/a\.jpg$/);
  });

  it("renders initials without any network image (no name/e-mail sent anywhere)", () => {
    const { container } = render(<Avatar user={{ fullName: "Ada Lovelace", email: "ada@example.com", profileImage: null }} />);
    expect(container.querySelector("img")).toBeNull();
    expect(container.textContent).toBe("AL");
    expect(container.innerHTML).not.toMatch(/ui-avatars|ada@example\.com/);
  });

  it("renders the profile photo when there is one", () => {
    const { container } = render(<Avatar user={{ fullName: "Ada", email: "a@b.c", profileImage: "/uploads/a.jpg" }} />);
    expect(container.querySelector("img")?.getAttribute("src")).toMatch(/\/uploads\/a\.jpg$/);
  });
});
