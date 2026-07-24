<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff & Admin Access - RedPulse RHU</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-900 via-slate-800 to-gray-900">
    <div class="min-h-screen flex items-center justify-center px-4 py-8">
        <div class="max-w-4xl w-full">

            <!-- Header -->
            <div class="text-center mb-10">
                <a href="LandingPage.php" class="inline-flex items-center justify-center gap-2 mb-5 group">
                    <svg class="w-10 h-10 text-red-500 group-hover:scale-110 transition-transform" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    <span class="text-3xl font-bold text-white">RedPulse RHU</span>
                </a>
                <div class="inline-flex items-center gap-2 bg-yellow-500/15 border border-yellow-500/30 rounded-full px-4 py-1.5 mb-4">
                    <svg class="w-4 h-4 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm font-bold text-yellow-300">Authorized Personnel Only</span>
                </div>
                <h1 class="text-2xl font-bold text-white mb-2">Staff & Admin Access</h1>
                <p class="text-slate-400 text-sm">Select your role to proceed. This area is restricted to authorized RHU and DOH personnel.</p>
            </div>

            <!-- Primary RHU Roles -->
            <div class="grid md:grid-cols-3 gap-4 mb-6">

                <!-- RHU Staff -->
                <a href="RHULogin.php" class="group bg-white/5 hover:bg-white/10 border border-white/10 hover:border-blue-500/50 rounded-2xl p-6 transition-all duration-200 hover:-translate-y-0.5">
                    <div class="flex flex-col items-center text-center">
                        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform shadow-lg">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.452a6 6 0 00-5.011 1.595h-.209V7a4 4 0 016.364 3.536v9.636l.75.75a2 2 0 11-2.828 2.828l-.75-.75v-.009a6 6 0 00-6-6v.009a4 4 0 00 6.364 3.536h.209V7a6 6 0 00-1.595 5.011l.452 2.387a2 2 0 00.547 1.022l2.387.452a6 6 0 005.011-1.595h.209v6.25a4 4 0 01-6.364-3.536"></path>
                            </svg>
                        </div>
                        <h2 class="text-lg font-bold text-white mb-1">RHU Staff</h2>
                        <p class="text-slate-400 text-xs mb-4">Doctors, nurses, midwives, medical technologists, and sanitary inspectors</p>
                        <span class="inline-flex items-center gap-1 text-blue-400 font-semibold text-sm group-hover:gap-2 transition-all">
                            Staff Login
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </span>
                    </div>
                </a>

                <!-- RHU Admin -->
                <a href="RHUAdminLogin.php" class="group bg-white/5 hover:bg-white/10 border border-white/10 hover:border-purple-500/50 rounded-2xl p-6 transition-all duration-200 hover:-translate-y-0.5">
                    <div class="flex flex-col items-center text-center">
                        <div class="w-16 h-16 bg-gradient-to-br from-purple-600 to-slate-800 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform shadow-lg">
                            <svg class="w-8 h-8 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-lg font-bold text-white mb-1">MHO / Admin</h2>
                        <p class="text-slate-400 text-xs mb-4">Municipal Health Officer & System Administrator — full clinical + admin access, MFA required</p>
                        <span class="inline-flex items-center gap-1 text-purple-400 font-semibold text-sm group-hover:gap-2 transition-all">
                            Admin Panel
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </span>
                    </div>
                </a>

                <!-- BHW -->
                <a href="BHWLogin.php" class="group bg-white/5 hover:bg-white/10 border border-white/10 hover:border-green-500/50 rounded-2xl p-6 transition-all duration-200 hover:-translate-y-0.5">
                    <div class="flex flex-col items-center text-center">
                        <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-emerald-700 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-105 transition-transform shadow-lg">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-lg font-bold text-white mb-1">BHW Portal</h2>
                        <p class="text-slate-400 text-xs mb-4">Barangay Health Workers assigned under this RHU</p>
                        <span class="inline-flex items-center gap-1 text-green-400 font-semibold text-sm group-hover:gap-2 transition-all">
                            BHW Login
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </span>
                    </div>
                </a>
            </div>

            <!-- Other portals -->
            <div class="flex flex-wrap justify-center gap-3 mb-8">
                <a href="DonorDashboard.php" class="text-sm font-semibold text-red-400 hover:text-red-300 transition-colors border border-white/10 px-4 py-2 rounded-lg hover:bg-white/5">
                    Blood Donors
                </a>
                <a href="HospitalRegistration.php" class="text-sm font-semibold text-indigo-400 hover:text-indigo-300 transition-colors border border-white/10 px-4 py-2 rounded-lg hover:bg-white/5">
                    Hospital Portal
                </a>
                <a href="AdminLogin.php" class="text-sm font-semibold text-gray-400 hover:text-gray-300 transition-colors border border-white/10 px-4 py-2 rounded-lg hover:bg-white/5">
                    System Admin
                </a>
            </div>

            <!-- Security Notice -->
            <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-4 mb-6 flex items-start gap-3">
                <svg class="w-5 h-5 text-yellow-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-sm text-yellow-300">
                    Unauthorized access is a violation of the Data Privacy Act (RA 10173) and DOH IT Security Policy. All login attempts are logged and monitored.
                </p>
            </div>

            <!-- Resident redirect -->
            <div class="text-center space-y-3">
                <div class="bg-emerald-900/30 border border-emerald-700/30 rounded-xl p-4 inline-flex items-center gap-3">
                    <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <p class="text-sm text-emerald-300">
                        Are you a resident looking for your health records?
                        <a href="ResidentLogin.php" class="font-bold text-emerald-400 hover:underline">Go to the Resident Portal →</a>
                    </p>
                </div>
                <div>
                    <a href="LandingPage.php" class="inline-flex items-center gap-2 text-slate-500 hover:text-slate-300 font-medium text-sm transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
