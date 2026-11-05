import { useNavigate } from "react-router";
import { Header } from "@/components/Home/Header";
import ResumesHeader from "@/components/Home/ResumesHeader";
import HomeEmptyState from "@/components/Home/HomeEmptyState";
import ResumeListSkeleton from "@/components/Home/ResumeListSkeleton";
import ResumeList from "@/components/Home/ResumeList";
import { useUserResumes } from "@/hooks/use-user-resumes";
import { ROUTE_LINKS } from "@/constants/RouteLinks";
import { downloadResume } from "@/api/resume";
import { toast } from "sonner";

const RESUME_LIMIT = 5;

export default function MyResumes() {
  const navigate = useNavigate();
  const { resumes, isLoading } = useUserResumes();

  const hasResumes = resumes.length > 0;

  return (
    <div className="flex min-h-screen flex-col">
      <Header />
      <div className="flex flex-1 flex-col p-6">
        {isLoading ? (
          <ResumeListSkeleton />
        ) : hasResumes ? (
          <>
            <ResumesHeader
              count={resumes.length}
              limit={RESUME_LIMIT}
              onAdd={() => navigate(ROUTE_LINKS.newResume)}
            />
            <ResumeList
              resumes={resumes}
              limit={RESUME_LIMIT}
              onDownloadResume={async (id) => {
                const resume = resumes.find((item) => item.id === id);
                if (resume?.status === "analyze") {
                  toast.info("Confirme os dados do currículo para gerar o PDF.");
                  navigate(ROUTE_LINKS.resumeConfirmId(id));
                  return;
                }
                if (resume?.status !== "ready") {
                  toast.info("O PDF deste currículo ainda não está disponível.");
                  return;
                }
                try {
                  await downloadResume(id);
                } catch {
                  toast.error("Não foi possível baixar o PDF. Atualize a página e tente novamente.");
                }
              }}
              onReviewResume={(id) => navigate(ROUTE_LINKS.resumeConfirmId(id))}
            />
          </>
        ) : (
          <div className="flex flex-1 items-center justify-center">
            <HomeEmptyState onUpload={() => navigate(ROUTE_LINKS.newResume)} />
          </div>
        )}
      </div>
    </div>
  );
}
