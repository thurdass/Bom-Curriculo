import { expect, it, vi } from "vitest";
import { httpClient } from "@/api/client";
import { analyseResume } from "./analyse-resume";

it("processes the uploaded UUID using the Laravel resume endpoint", async () => {
  const post = vi.spyOn(httpClient, "post").mockResolvedValueOnce({ data: { data: [] } });
  try {
    await analyseResume("resume-id");
    expect(post).toHaveBeenCalledWith("/client/services/resumes/process", { user_resume_id: "resume-id" });
  } finally {
    post.mockRestore();
  }
});
