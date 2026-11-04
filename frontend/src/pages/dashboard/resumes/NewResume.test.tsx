import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { cleanup, render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { MemoryRouter } from "react-router";
import NewResume from "@/pages/dashboard/resumes/NewResume";

const {
  analyseResumeMock,
  createResumeMock,
  listUserResumesMock,
  toastErrorMock,
  toastSuccessMock,
} = vi.hoisted(() => ({
  analyseResumeMock: vi.fn(),
  createResumeMock: vi.fn(),
  listUserResumesMock: vi.fn(),
  toastErrorMock: vi.fn(),
  toastSuccessMock: vi.fn(),
}));

vi.mock("@/api/resume", () => ({
  analyseResume: analyseResumeMock,
  createResume: createResumeMock,
  listUserResumes: listUserResumesMock,
}));

vi.mock("sonner", () => ({
  toast: {
    error: toastErrorMock,
    success: toastSuccessMock,
  },
}));

function renderPage() {
  const queryClient = new QueryClient({
    defaultOptions: {
      mutations: { retry: false },
      queries: { retry: false },
    },
  });

  return render(
    <MemoryRouter>
      <QueryClientProvider client={queryClient}>
        <NewResume />
      </QueryClientProvider>
    </MemoryRouter>,
  );
}

describe("NewResume", () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  afterEach(() => {
    cleanup();
  });

  it("accepts only bot-supported resume and LinkedIn file formats", () => {
    const { container } = renderPage();

    expect(container.querySelector("#resume_pdf")).toHaveAttribute("accept", ".pdf,.docx");
    expect(container.querySelector("#linkedin_pdf")).toHaveAttribute("accept", ".pdf");
    expect(screen.getByText("Selecionar arquivo (PDF ou DOCX)")).toBeInTheDocument();
  });

  it("shows the real processing error instead of reporting success", async () => {
    const user = userEvent.setup();
    createResumeMock.mockResolvedValue({ user_resume_id: "resume-real-error" });
    listUserResumesMock.mockResolvedValue([{ id: "another-concurrent-upload" }]);
    analyseResumeMock.mockRejectedValue(new Error("Bot indisponível"));
    const { container } = renderPage();
    const resumeInput = container.querySelector<HTMLInputElement>("#resume_pdf");

    expect(resumeInput).not.toBeNull();
    await user.upload(resumeInput!, new File(["resume"], "resume.pdf", { type: "application/pdf" }));
    await user.click(screen.getByRole("button", { name: "Gerar Currículo com IA" }));

    await waitFor(() => {
      expect(analyseResumeMock).toHaveBeenCalledWith("resume-real-error");
      expect(toastErrorMock).toHaveBeenCalledWith("Bot indisponível");
      expect(screen.getByText("Bot indisponível")).toBeInTheDocument();
    });

    expect(toastSuccessMock).not.toHaveBeenCalled();
    expect(listUserResumesMock).not.toHaveBeenCalled();
  });

  it("does not guess an upload ID when the upload response is incomplete", async () => {
    createResumeMock.mockResolvedValue({ message: "Uploaded" });
    const user = userEvent.setup();
    const { container } = renderPage();
    await user.upload(container.querySelector<HTMLInputElement>("#resume_pdf")!, new File(["resume"], "resume.pdf", { type: "application/pdf" }));
    await user.click(screen.getByRole("button", { name: "Gerar Currículo com IA" }));
    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith("Não foi possível localizar o currículo enviado."));
    expect(analyseResumeMock).not.toHaveBeenCalled();
    expect(listUserResumesMock).not.toHaveBeenCalled();
  });
});
