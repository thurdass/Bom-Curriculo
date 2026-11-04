import { expect, it, vi } from "vitest";
import { httpClient } from "@/api/client";
import { createResume } from "./create-resume";

it("unwraps the API envelope and returns the uploaded resume ID", async () => {
  const post = vi.spyOn(httpClient, "post").mockResolvedValueOnce({
    data: { code: 200, data: { message: "Uploaded", user_resume_id: "upload-id" } },
  });
  try {
    await expect(createResume({ resume_cv: new File(["cv"], "cv.pdf") })).resolves.toEqual({
      message: "Uploaded", user_resume_id: "upload-id",
    });
    expect(post).toHaveBeenCalledWith("/client/resumes/new-resume", expect.any(FormData));
  } finally {
    post.mockRestore();
  }
});
