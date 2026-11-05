import { afterEach, expect, it, vi } from "vitest";
import { httpClient } from "@/api/client";
import { downloadResume } from "./download-resume";

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
  vi.useRealTimers();
});

it("downloads through the authenticated API and releases the temporary URL", async () => {
  vi.useFakeTimers();
  const pdf = new Blob(["%PDF-1.4"], { type: "application/pdf" });
  const get = vi.spyOn(httpClient, "get").mockResolvedValue({ data: pdf });
  const create = vi.fn().mockReturnValue("blob:test-pdf");
  const revoke = vi.fn();
  vi.stubGlobal("URL", { createObjectURL: create, revokeObjectURL: revoke });
  let link: { download: string; href: string; attached: boolean } | undefined;
  vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(function (this: HTMLAnchorElement) {
    link = { download: this.download, href: this.href, attached: this.isConnected };
  });

  await downloadResume("resume-id");
  expect(get).toHaveBeenCalledWith("/client/resumes/resume-id/download", { responseType: "blob" });
  expect(create).toHaveBeenCalledWith(pdf);
  expect(link?.download).toBe("curriculo.pdf");
  expect(link?.href).toBe("blob:test-pdf");
  expect(link?.attached).toBe(true);
  expect(document.querySelector('a[download="curriculo.pdf"]')).toBeNull();
  expect(revoke).not.toHaveBeenCalled();
  vi.runAllTimers();
  expect(revoke).toHaveBeenCalledWith("blob:test-pdf");
});

it("propagates download failures instead of opening an invalid file", async () => {
  vi.spyOn(httpClient, "get").mockRejectedValue(new Error("Download failed"));
  const click = vi.spyOn(HTMLAnchorElement.prototype, "click");
  await expect(downloadResume("missing")).rejects.toThrow("Download failed");
  expect(click).not.toHaveBeenCalled();
});
