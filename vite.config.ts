import { defineConfig, loadEnv } from "vite";
import react from "@vitejs/plugin-react";
import tsconfigPaths from "vite-tsconfig-paths";

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "");
  const publicUrl = env.PUBLIC_URL ?? "";
  const base = publicUrl
    ? publicUrl.endsWith("/")
      ? publicUrl
      : `${publicUrl}/`
    : "/";

  const clientEnv = Object.fromEntries(
    Object.entries(env).filter(
      ([key]) => key === "PUBLIC_URL" || key.startsWith("REACT_APP_")
    )
  );

  const defineEnv = Object.fromEntries(
    Object.entries(clientEnv).map(([key, value]) => [
      `process.env.${key}`,
      JSON.stringify(value),
    ])
  );

  return {
    base,
    plugins: [react(), tsconfigPaths()],
    define: defineEnv,
  };
});
