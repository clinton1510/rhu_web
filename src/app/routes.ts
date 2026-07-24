import { createBrowserRouter } from "react-router-dom";
import { LandingPage } from "./components/LandingPage";
import { DonorDashboard } from "./components/DonorDashboard";
import { AdminDashboard } from "./components/AdminDashboard";
import { HospitalRegistration } from "./components/HospitalRegistration";
import { AdminLogin } from "./components/AdminLogin";
import { LoginSelection } from "./components/LoginSelection";
// RHU Staff logins & dashboards
import { RHULogin } from "./components/RHULogin";
import { RHUDashboard } from "./components/RHUDashboard";            // MHO
import { MidwifeDashboard } from "./components/MidwifeDashboard";
import { NurseDashboard } from "./components/NurseDashboard";
import { MedTechDashboard } from "./components/MedTechDashboard";
import { SanitaryDashboard } from "./components/SanitaryDashboard";
import { AdminStaffDashboard } from "./components/AdminStaffDashboard";
// RHU Admin
import { RHUAdminLogin } from "./components/RHUAdminLogin";
import { RHUAdminDashboard } from "./components/RHUAdminDashboard";
// BHW
import { BHWLogin } from "./components/BHWLogin";
import { BHWDashboard } from "./components/BHWDashboard";
// Resident
import { ResidentLogin } from "./components/ResidentLogin";
import { ResidentDashboard } from "./components/ResidentDashboard";

export const router = createBrowserRouter([
  { path: "/", Component: LandingPage },
  { path: "/login", Component: LoginSelection },

  // Resident portal
  { path: "/resident/login", Component: ResidentLogin },
  { path: "/resident/dashboard", Component: ResidentDashboard },

  // RHU Staff — one login, role-based dashboard routing
  { path: "/rhu/login", Component: RHULogin },
  { path: "/rhu/dashboard", Component: RHUDashboard },                        // MHO
  { path: "/rhu/dashboard/midwife", Component: MidwifeDashboard },
  { path: "/rhu/dashboard/nurse", Component: NurseDashboard },
  { path: "/rhu/dashboard/medtech", Component: MedTechDashboard },
  { path: "/rhu/dashboard/sanitary", Component: SanitaryDashboard },
  { path: "/rhu/dashboard/admin-staff", Component: AdminStaffDashboard },

  // RHU Admin
  { path: "/rhu/admin/login", Component: RHUAdminLogin },
  { path: "/rhu/admin/dashboard", Component: RHUAdminDashboard },

  // BHW
  { path: "/bhw/login", Component: BHWLogin },
  { path: "/bhw/dashboard", Component: BHWDashboard },

  // Legacy donation routes removed to avoid missing-module errors
  { path: "/donor/dashboard", Component: DonorDashboard },
  { path: "/hospital/register", Component: HospitalRegistration },
  { path: "/admin/login", Component: AdminLogin },
  { path: "/admin/dashboard", Component: AdminDashboard },
], { basename: "/CAPSTONERHU" });
