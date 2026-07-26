import { createElement, useEffect } from "react";
import { createBrowserRouter } from "react-router-dom";

const componentBase = "/RURAL-HEALTH-UNIT/src/app/components";

function PhpRedirect({ page }: { page: string }) {
  useEffect(() => {
    window.location.replace(`${componentBase}/${page}`);
  }, [page]);

  return createElement(
    "main",
    { className: "flex min-h-screen items-center justify-center bg-slate-50 p-6" },
    createElement(
      "p",
      { className: "text-sm font-medium text-slate-600" },
      "Opening ResiHUnity RHU…",
    ),
  );
}

const route = (path: string, page: string) => ({
  path,
  element: createElement(PhpRedirect, { page }),
});

export const router = createBrowserRouter(
  [
    route("/", "LandingPage.php"),
    route("/login", "LoginSelection.php"),
    route("/resident/login", "ResidentLogin.php"),
    route("/resident/dashboard", "ResidentDashboard.php"),
    route("/rhu/login", "RHULogin.php"),
    route("/rhu/dashboard", "RHUDashboard.php"),
    route("/rhu/dashboard/midwife", "MidwifeDashboard.php"),
    route("/rhu/dashboard/nurse", "NurseDashboard.php"),
    route("/rhu/dashboard/medtech", "MedTechDashboard.php"),
    route("/rhu/admin/login", "RHUAdminLogin.php"),
    route("/rhu/admin/dashboard", "RHUAdminDashboard.php"),
    route("/bhw/login", "BHWLogin.php"),
    route("/bhw/dashboard", "BHWDashboard.php"),
    route("*", "LandingPage.php"),
  ],
  { basename: "/RURAL-HEALTH-UNIT" },
);
