import { httpClient } from "@/api/client";

export async function downloadResume(id: string): Promise<void> {
  const { data } = await httpClient.get<Blob>(
    `/client/resumes/${encodeURIComponent(id)}/download`,
    { responseType: "blob" },
  );

  const url = URL.createObjectURL(data);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = "curriculo.pdf";
  document.body.appendChild(anchor);
  anchor.click();
  anchor.remove();
  // Keep the object URL alive until the browser has started the download.
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}
