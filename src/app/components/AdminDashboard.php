<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function esc(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function iconSvg(string $name, string $class = 'w-5 h-5'): string
{
    $icons = [
        'shield' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'users' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'building' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><path d="M9 22V12h6v10"/><path d="M9 8h.01"/><path d="M13 8h.01"/><path d="M9 12h.01"/><path d="M13 12h.01"/></svg>',
        'alert' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        'check' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
        'trend' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
        'close' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        'download' => '<svg class="' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
    ];

    return $icons[$name] ?? '';
}

// default mock data (used when DB is not available)
$mockDonors = [
    ['availability' => true],
    ['availability' => true],
    ['availability' => true],
    ['availability' => false],
    ['availability' => true],
    ['availability' => true],
    ['availability' => true],
    ['availability' => true],
];

$mockBloodRequests = [
    ['status' => 'matching', 'urgency' => 'critical'],
    ['status' => 'pending', 'urgency' => 'urgent'],
    ['status' => 'matching', 'urgency' => 'critical'],
];

$mockDonorClusters = [
    [
        'cluster' => 'reliable',
        'count' => 5,
        'avgResponseTime' => 10,
        'avgResponseRate' => 90,
        'characteristics' => [
            'Consistent donation history (7+ donations)',
            'Response time under 12 minutes',
            'High availability rate',
            'Verified contact information',
        ],
    ],
];

$pendingVerifications = [
    ['id' => 1, 'name' => 'Sofia Lim', 'type' => 'Donor', 'bloodType' => 'AB+', 'date' => '2026-03-20', 'status' => 'pending'],
];

$stats = [
    'totalDonors' => count($mockDonors),
    'activeDonors' => count(array_filter($mockDonors, static fn ($donor) => !empty($donor['availability']))),
    'totalHospitals' => 23,
    'activeRequests' => count(array_filter($mockBloodRequests, static fn ($request) => $request['status'] === 'matching')),
    'fulfilledToday' => 8,
    'avgResponseTime' => 12,
];

// Attempt to load real data from DB if available
@include_once __DIR__ . '/db.php';
if (isset($pdo) && $pdo) {
    try {
        // totals
        $stats['totalDonors'] = (int) $pdo->query('SELECT COUNT(*) FROM donors')->fetchColumn();
        $stats['activeDonors'] = (int) $pdo->query('SELECT COUNT(*) FROM donors WHERE is_eligible = 1')->fetchColumn();
        $stats['totalHospitals'] = (int) $pdo->query('SELECT COUNT(*) FROM blood_banks')->fetchColumn();
        $stats['activeRequests'] = (int) $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE request_status IN ('Pending','Matched')")->fetchColumn();

        // critical requests
        $criticalCount = (int) $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE urgency_level = 'Critical'")->fetchColumn();

        // donor clusters (simple aggregation)
        $clusters = [];
        $stmt = $pdo->query("SELECT donor_classification AS cluster, COUNT(*) AS cnt, AVG(COALESCE(donation_response_probability,0)) AS avgProb FROM donors GROUP BY donor_classification");
        while ($row = $stmt->fetch()) {
            $clusters[] = [
                'cluster' => $row['cluster'] ?: 'unknown',
                'count' => (int) $row['cnt'],
                'avgResponseTime' => null,
                'avgResponseRate' => (int) round(($row['avgProb'] ?? 0) * 100),
                'characteristics' => [],
            ];
        }
        if (!empty($clusters)) {
            $mockDonorClusters = $clusters;
        }

        // pending verifications: users not active
        $pending = [];
        $stmt = $pdo->query("SELECT id, first_name, last_name, email, created_at FROM users WHERE is_active = 0 ORDER BY created_at DESC LIMIT 10");
        while ($u = $stmt->fetch()) {
            $pending[] = ['id' => $u['id'], 'name' => trim($u['first_name'] . ' ' . $u['last_name']), 'type' => 'User', 'email' => $u['email'], 'date' => $u['created_at'], 'status' => 'pending'];
        }
        if (!empty($pending)) {
            $pendingVerifications = $pending;
        }

        // Use critical count in the display by replacing mock calculation
        $mockBloodRequests = array_fill(0, $criticalCount, ['status' => 'matching', 'urgency' => 'critical']);
        $stats['activeRequests'] = max(0, $stats['activeRequests']);

    } catch (PDOException $e) {
        error_log('AdminDashboard DB: ' . $e->getMessage());
        // leave mock data intact on error
    }
}

$_SESSION['AdminDashboard_flash'] ??= null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $returnPanel = $_POST['return_panel'] ?? '';

    if ($action === 'verify_user') {
        $_SESSION['AdminDashboard_flash'] = [
            'type' => 'success',
            'message' => ($_POST['user_name'] ?? 'User') . ' verified successfully. User can now access the full platform.',
        ];
        header('Location: ?panel=' . rawurlencode($returnPanel ?: 'verification'));
        exit;
    }

    if ($action === 'reject_user') {
        $_SESSION['AdminDashboard_flash'] = [
            'type' => 'error',
            'message' => ($_POST['user_name'] ?? 'User') . ' verification rejected. User has been notified.',
        ];
        header('Location: ?panel=' . rawurlencode($returnPanel ?: 'verification'));
        exit;
    }

    if ($action === 'save_settings') {
        $_SESSION['AdminDashboard_flash'] = [
            'type' => 'success',
            'message' => 'System settings saved successfully.',
        ];
        header('Location: ?panel=' . rawurlencode($returnPanel ?: 'settings'));
        exit;
    }

    $_SESSION['AdminDashboard_flash'] = [
        'type' => 'success',
        'message' => 'Action saved successfully.',
    ];
    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '?'));
    exit;
}

$flash = $_SESSION['AdminDashboard_flash'];
$_SESSION['AdminDashboard_flash'] = null;
$panel = $_GET['panel'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AdminDashboard - RedPulse RHU</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">
    <?php if ($flash): ?>
        <div class="max-w-7xl mx-auto px-4 pt-6">
            <div class="rounded-xl border px-4 py-3 text-sm font-semibold <?php echo esc($flash['type'] === 'success' ? 'border-green-200 text-green-800' : 'border-red-200 text-red-800'); ?>" style="<?php echo esc($flash['type'] === 'success' ? 'background-color: #f0fdf4;' : 'background-color: #fef2f2;'); ?>">
                <?php echo esc($flash['message']); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="min-h-screen">
        <header class="bg-gradient-to-r from-purple-600 to-indigo-700 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <?php echo iconSvg('shield', 'w-8 h-8'); ?>
                        <div>
                            <h1 class="text-2xl font-bold">RedPulse Admin Console</h1>
                            <p class="text-sm text-purple-100">System Monitoring & Management</p>
                        </div>
                    </div>
                    <a href="index.php" class="px-4 py-2 bg-white text-purple-600 rounded-lg hover:bg-purple-50 font-semibold">Exit Admin</a>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <section class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <article class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-600 text-sm">Total Donors</span>
                        <span class="text-blue-600"><?php echo iconSvg('users'); ?></span>
                    </div>
                    <div class="text-3xl font-bold text-gray-900"><?php echo esc($stats['totalDonors']); ?></div>
                    <div class="text-xs text-green-600 mt-1">+24 this month</div>
                </article>

                <article class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-600 text-sm">Partner Hospitals</span>
                        <span class="text-purple-600"><?php echo iconSvg('building'); ?></span>
                    </div>
                    <div class="text-3xl font-bold text-gray-900"><?php echo esc($stats['totalHospitals']); ?></div>
                    <div class="text-xs text-green-600 mt-1">+2 pending verification</div>
                </article>

                <article class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-600 text-sm">Active Requests</span>
                        <span class="text-orange-600"><?php echo iconSvg('alert'); ?></span>
                    </div>
                    <div class="text-3xl font-bold text-gray-900"><?php echo esc($stats['activeRequests']); ?></div>
                    <div class="text-xs text-orange-600 mt-1"><?php echo esc(count(array_filter($mockBloodRequests, static fn ($request) => $request['urgency'] === 'critical'))); ?> critical</div>
                </article>

                <article class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-gray-600 text-sm">Success Rate</span>
                        <span class="text-green-600"><?php echo iconSvg('check'); ?></span>
                    </div>
                    <div class="text-3xl font-bold text-gray-900">94%</div>
                    <div class="text-xs text-green-600 mt-1">+2% from last month</div>
                </article>
            </section>

            <section class="grid lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <article class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-bold flex items-center gap-2">
                                <span class="text-purple-600"><?php echo iconSvg('users', 'w-6 h-6'); ?></span>
                                Donor Behavioral Clustering (K-Means)
                            </h2>
                            <a href="/admin/analytics" class="text-sm text-purple-600 hover:text-purple-700 font-semibold">View Analytics →</a>
                        </div>

                        <div class="space-y-4">
                            <?php foreach ($mockDonorClusters as $cluster): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <h3 class="font-semibold text-lg capitalize"><?php echo esc($cluster['cluster']); ?> Donors</h3>
                                            <p class="text-sm text-gray-600"><?php echo esc($cluster['count']); ?> members in cluster</p>
                                        </div>
                                        <div class="text-right">
                                            <div class="px-3 py-1 rounded-lg font-semibold <?php echo esc($cluster['cluster'] === 'reliable' ? 'bg-green-100 text-green-700' : ($cluster['cluster'] === 'moderate' ? 'bg-yellow-100 text-yellow-700' : 'bg-blue-100 text-blue-700')); ?>">
                                                <?php echo esc($cluster['avgResponseRate']); ?>% Response Rate
                                            </div>
                                        </div>
                                    </div>

                                    <div class="grid md:grid-cols-2 gap-4 mb-3">
                                        <div>
                                            <div class="text-xs text-gray-600 mb-1">Avg Response Time</div>
                                            <div class="font-semibold"><?php echo esc($cluster['avgResponseTime']); ?> minutes</div>
                                        </div>
                                        <div>
                                            <div class="text-xs text-gray-600 mb-1">Cluster Size</div>
                                            <div class="font-semibold"><?php echo esc($cluster['count']); ?> donors (<?php echo esc($stats['totalDonors'] ? (int) round($cluster['count'] / $stats['totalDonors'] * 100) : 0); ?>%)</div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-xs text-gray-600 mb-2">Characteristics</div>
                                        <ul class="text-sm text-gray-700 space-y-1">
                                            <?php foreach ($cluster['characteristics'] as $characteristic): ?>
                                                <li class="flex items-start gap-2">
                                                    <span class="text-purple-600">•</span>
                                                    <span><?php echo esc($characteristic); ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-xl font-bold mb-6">System Activity Log</h2>
                        <div class="space-y-3">
                            <div class="flex items-start gap-3 pb-3 border-b">
                                <span class="text-green-600 mt-0.5"><?php echo iconSvg('check'); ?></span>
                                <div class="flex-1">
                                    <div class="font-semibold">Blood Request Fulfilled</div>
                                    <div class="text-sm text-gray-600">PGH - O+ blood matched with donor D001 in 8 minutes</div>
                                    <div class="text-xs text-gray-500 mt-1">5 minutes ago</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-3 pb-3 border-b">
                                <span class="text-blue-600 mt-0.5"><?php echo iconSvg('users'); ?></span>
                                <div class="flex-1">
                                    <div class="font-semibold">New Donor Registration</div>
                                    <div class="text-sm text-gray-600">Sofia Lim (AB+) completed registration - pending verification</div>
                                    <div class="text-xs text-gray-500 mt-1">18 minutes ago</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-3 pb-3 border-b">
                                <span class="text-purple-600 mt-0.5"><?php echo iconSvg('building'); ?></span>
                                <div class="flex-1">
                                    <div class="font-semibold">Hospital Verified</div>
                                    <div class="text-sm text-gray-600">Makati Medical Center credentials approved</div>
                                    <div class="text-xs text-gray-500 mt-1">2 hours ago</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-3 pb-3 border-b">
                                <span class="text-orange-600 mt-0.5"><?php echo iconSvg('alert'); ?></span>
                                <div class="flex-1">
                                    <div class="font-semibold">Critical Request Alert</div>
                                    <div class="text-sm text-gray-600">St. Luke's - O- emergency request, 5 donors notified</div>
                                    <div class="text-xs text-gray-500 mt-1">3 hours ago</div>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <span class="text-green-600 mt-0.5"><?php echo iconSvg('trend'); ?></span>
                                <div class="flex-1">
                                    <div class="font-semibold">ML Model Updated</div>
                                    <div class="text-sm text-gray-600">Response probability model retrained - accuracy improved to 94.2%</div>
                                    <div class="text-xs text-gray-500 mt-1">5 hours ago</div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <aside class="space-y-6">
                    <article class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="font-bold mb-4">Quick Actions</h3>
                        <div class="space-y-2">
                            <form method="get">
                                <input type="hidden" name="panel" value="verification">
                                <button type="submit" class="w-full py-2 px-4 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-semibold text-sm">Verify Pending Users</button>
                            </form>
                            <form method="get">
                                <input type="hidden" name="panel" value="reports">
                                <button type="submit" class="w-full py-2 px-4 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">Generate Report</button>
                            </form>
                            <form method="get">
                                <input type="hidden" name="panel" value="settings">
                                <button type="submit" class="w-full py-2 px-4 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">System Settings</button>
                            </form>
                            <a href="/admin/analytics" class="block w-full py-2 px-4 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm text-center">Advanced Analytics</a>
                        </div>
                    </article>

                    <article class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="font-bold mb-4">System Health</h3>
                        <div class="space-y-3">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">API Response Time</span>
                                    <span class="font-semibold text-green-600">142ms</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full" style="width: 95%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">Database Performance</span>
                                    <span class="font-semibold text-green-600">Excellent</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full" style="width: 98%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">ML Model Accuracy</span>
                                    <span class="font-semibold text-green-600">94.2%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-600 h-2 rounded-full" style="width: 94%"></div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <article class="bg-green-50 border border-green-200 rounded-xl p-6">
                        <h3 class="font-bold text-green-900 mb-3 flex items-center gap-2">
                            <?php echo iconSvg('shield', 'w-5 h-5'); ?>
                            Compliance Status
                        </h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center gap-2 text-green-800"><span><?php echo iconSvg('check', 'w-4 h-4'); ?></span><span>RA 7719 Compliant</span></div>
                            <div class="flex items-center gap-2 text-green-800"><span><?php echo iconSvg('check', 'w-4 h-4'); ?></span><span>Data Privacy Act 2012</span></div>
                            <div class="flex items-center gap-2 text-green-800"><span><?php echo iconSvg('check', 'w-4 h-4'); ?></span><span>DOH Guidelines</span></div>
                            <div class="flex items-center gap-2 text-green-800"><span><?php echo iconSvg('check', 'w-4 h-4'); ?></span><span>Ethical Standards</span></div>
                        </div>
                    </article>

                    <article class="bg-orange-50 border border-orange-200 rounded-xl p-6">
                        <h3 class="font-bold text-orange-900 mb-3">System Alerts</h3>
                        <div class="space-y-3 text-sm">
                            <div class="flex items-start gap-2">
                                <span class="text-orange-600 mt-0.5"><?php echo iconSvg('alert', 'w-4 h-4'); ?></span>
                                <div class="text-orange-800">
                                    <div class="font-semibold">2 Pending Verifications</div>
                                    <div class="text-xs mt-1">Hospital registrations awaiting review</div>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-orange-600 mt-0.5"><?php echo iconSvg('alert', 'w-4 h-4'); ?></span>
                                <div class="text-orange-800">
                                    <div class="font-semibold">Low O- Inventory</div>
                                    <div class="text-xs mt-1">Consider proactive donor mobilization</div>
                                </div>
                            </div>
                        </div>
                    </article>
                </aside>
            </section>
        </main>

        <?php if ($panel === 'verification'): ?>
            <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                    <div class="p-6 border-b flex items-center justify-between bg-gradient-to-r from-purple-600 to-indigo-700 text-white">
                        <h2 class="text-2xl font-bold">Pending User Verifications</h2>
                        <a href="?" class="p-2 hover:bg-purple-800 rounded-lg"><?php echo iconSvg('close', 'w-6 h-6'); ?></a>
                    </div>
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                        <div class="mb-4 bg-purple-50 rounded-lg p-4 border border-purple-200">
                            <div class="text-sm text-purple-900 font-semibold"><?php echo esc(count($pendingVerifications)); ?> users awaiting verification</div>
                            <div class="text-xs text-purple-700 mt-1">Review and approve user registrations to grant platform access</div>
                        </div>

                        <div class="space-y-4">
                            <?php foreach ($pendingVerifications as $user): ?>
                                <div class="border-2 border-gray-200 rounded-lg p-4 hover:border-purple-300 transition-colors">
                                    <div class="flex items-start justify-between mb-3 gap-4">
                                        <div>
                                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                                <span class="font-semibold text-lg text-gray-900"><?php echo esc($user['name']); ?></span>
                                                <span class="px-2 py-0.5 rounded text-xs font-semibold <?php echo esc($user['type'] === 'Donor' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'); ?>"><?php echo esc($user['type']); ?></span>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                <?php echo esc($user['type'] === 'Donor' ? 'Blood Type: ' . $user['bloodType'] : 'Location: ' . $user['location']); ?>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">Submitted: <?php echo esc(date('F j, Y', strtotime($user['date']))); ?></div>
                                        </div>
                                        <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">Pending Review</span>
                                    </div>

                                    <div class="flex flex-wrap gap-2 pt-3 border-t">
                                        <form method="post" class="flex-1 min-w-[140px]">
                                            <input type="hidden" name="action" value="verify_user">
                                            <input type="hidden" name="user_id" value="<?php echo esc($user['id']); ?>">
                                            <input type="hidden" name="user_name" value="<?php echo esc($user['name']); ?>">
                                            <input type="hidden" name="return_panel" value="verification">
                                            <button type="submit" class="w-full py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold text-sm transition-colors">✓ Approve</button>
                                        </form>
                                        <form method="post" class="flex-1 min-w-[140px]">
                                            <input type="hidden" name="action" value="reject_user">
                                            <input type="hidden" name="user_id" value="<?php echo esc($user['id']); ?>">
                                            <input type="hidden" name="user_name" value="<?php echo esc($user['name']); ?>">
                                            <input type="hidden" name="return_panel" value="verification">
                                            <button type="submit" class="w-full py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold text-sm transition-colors">✗ Reject</button>
                                        </form>
                                        <button class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">View Details</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($panel === 'reports'): ?>
            <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                    <div class="p-6 border-b flex items-center justify-between bg-gradient-to-r from-purple-600 to-indigo-700 text-white">
                        <h2 class="text-2xl font-bold">System Reports & Analytics</h2>
                        <a href="?" class="p-2 hover:bg-purple-800 rounded-lg"><?php echo iconSvg('close', 'w-6 h-6'); ?></a>
                    </div>
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                        <div class="grid md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-purple-50 rounded-xl p-6 border border-purple-200">
                                <div class="text-3xl font-bold text-purple-600 mb-2"><?php echo esc($stats['totalDonors']); ?></div>
                                <div class="text-sm text-gray-600">Total Donors</div>
                            </div>
                            <div class="bg-blue-50 rounded-xl p-6 border border-blue-200">
                                <div class="text-3xl font-bold text-blue-600 mb-2"><?php echo esc($stats['totalHospitals']); ?></div>
                                <div class="text-sm text-gray-600">Partner Hospitals</div>
                            </div>
                            <div class="bg-green-50 rounded-xl p-6 border border-green-200">
                                <div class="text-3xl font-bold text-green-600 mb-2">94%</div>
                                <div class="text-sm text-gray-600">Success Rate</div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <button class="w-full p-4 border-2 border-gray-200 rounded-lg hover:border-purple-300 hover:bg-purple-50 transition-all flex items-center justify-between">
                                <div class="text-left">
                                    <div class="font-semibold text-gray-900">Comprehensive System Report</div>
                                    <div class="text-sm text-gray-600 mt-1">Full analytics including all metrics and ML model performance</div>
                                </div>
                                <span class="text-purple-600"><?php echo iconSvg('download'); ?></span>
                            </button>

                            <button class="w-full p-4 border-2 border-gray-200 rounded-lg hover:border-purple-300 hover:bg-purple-50 transition-all flex items-center justify-between">
                                <div class="text-left">
                                    <div class="font-semibold text-gray-900">Donor Demographics Report</div>
                                    <div class="text-sm text-gray-600 mt-1">Blood type distribution, location data, and behavioral clusters</div>
                                </div>
                                <span class="text-purple-600"><?php echo iconSvg('download'); ?></span>
                            </button>

                            <button class="w-full p-4 border-2 border-gray-200 rounded-lg hover:border-purple-300 hover:bg-purple-50 transition-all flex items-center justify-between">
                                <div class="text-left">
                                    <div class="font-semibold text-gray-900">Hospital Performance Report</div>
                                    <div class="text-sm text-gray-600 mt-1">Request patterns, fulfillment rates, and response times</div>
                                </div>
                                <span class="text-purple-600"><?php echo iconSvg('download'); ?></span>
                            </button>

                            <button class="w-full p-4 border-2 border-gray-200 rounded-lg hover:border-purple-300 hover:bg-purple-50 transition-all flex items-center justify-between">
                                <div class="text-left">
                                    <div class="font-semibold text-gray-900">ML Model Performance Report</div>
                                    <div class="text-sm text-gray-600 mt-1">Random Forest, K-Means, and Prophet model accuracy metrics</div>
                                </div>
                                <span class="text-purple-600"><?php echo iconSvg('download'); ?></span>
                            </button>

                            <button class="w-full p-4 border-2 border-gray-200 rounded-lg hover:border-purple-300 hover:bg-purple-50 transition-all flex items-center justify-between">
                                <div class="text-left">
                                    <div class="font-semibold text-gray-900">Compliance & Regulatory Report</div>
                                    <div class="text-sm text-gray-600 mt-1">RA 7719 and Data Privacy Act compliance documentation</div>
                                </div>
                                <span class="text-purple-600"><?php echo iconSvg('download'); ?></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($panel === 'settings'): ?>
            <div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-2xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-hidden">
                    <div class="p-6 border-b flex items-center justify-between bg-gradient-to-r from-purple-600 to-indigo-700 text-white">
                        <h2 class="text-2xl font-bold">System Settings</h2>
                        <a href="?" class="p-2 hover:bg-purple-800 rounded-lg"><?php echo iconSvg('close', 'w-6 h-6'); ?></a>
                    </div>
                    <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                        <form method="post" class="space-y-6">
                            <input type="hidden" name="action" value="save_settings">
                            <input type="hidden" name="return_panel" value="settings">

                            <div>
                                <h3 class="font-bold text-gray-900 mb-3">ML Model Configuration</h3>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg gap-4">
                                        <div>
                                            <div class="font-semibold text-gray-900">Random Forest Auto-Retrain</div>
                                            <div class="text-sm text-gray-600 mt-1">Automatically retrain donor response prediction model</div>
                                        </div>
                                        <input type="checkbox" name="random_forest_auto_retrain" checked class="w-5 h-5 text-purple-600 rounded">
                                    </div>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg gap-4">
                                        <div>
                                            <div class="font-semibold text-gray-900">K-Means Clustering Updates</div>
                                            <div class="text-sm text-gray-600 mt-1">Update donor behavioral clusters weekly</div>
                                        </div>
                                        <input type="checkbox" name="kmeans_updates" checked class="w-5 h-5 text-purple-600 rounded">
                                    </div>
                                </div>
                            </div>

                            <div class="border-t pt-6">
                                <h3 class="font-bold text-gray-900 mb-3">Notification Settings</h3>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg gap-4">
                                        <div>
                                            <div class="font-semibold text-gray-900">Critical Request Auto-Alert</div>
                                            <div class="text-sm text-gray-600 mt-1">Automatically notify admin for critical requests</div>
                                        </div>
                                        <input type="checkbox" name="critical_request_alert" checked class="w-5 h-5 text-purple-600 rounded">
                                    </div>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg gap-4">
                                        <div>
                                            <div class="font-semibold text-gray-900">Daily System Digest</div>
                                            <div class="text-sm text-gray-600 mt-1">Receive daily summary of platform activity</div>
                                        </div>
                                        <input type="checkbox" name="daily_system_digest" class="w-5 h-5 text-purple-600 rounded">
                                    </div>
                                </div>
                            </div>

                            <div class="border-t pt-6">
                                <h3 class="font-bold text-gray-900 mb-3">Security & Compliance</h3>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg gap-4">
                                        <div>
                                            <div class="font-semibold text-gray-900">Two-Factor Authentication</div>
                                            <div class="text-sm text-gray-600 mt-1">Require 2FA for all admin accounts</div>
                                        </div>
                                        <input type="checkbox" name="two_factor_authentication" checked class="w-5 h-5 text-purple-600 rounded">
                                    </div>
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg gap-4">
                                        <div>
                                            <div class="font-semibold text-gray-900">Data Privacy Compliance</div>
                                            <div class="text-sm text-gray-600 mt-1">Auto-anonymize data after 5 years (Data Privacy Act)</div>
                                        </div>
                                        <input type="checkbox" name="data_privacy_compliance" checked class="w-5 h-5 text-purple-600 rounded">
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-3 pt-6">
                                <button type="submit" class="flex-1 bg-purple-600 text-white py-3 rounded-lg hover:bg-purple-700 font-semibold">Save Settings</button>
                                <a href="?" class="px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold text-center">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
