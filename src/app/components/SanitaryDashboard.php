<?php
if (session_status() === PHP_SESSION_NONE) session_start();
function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$tabs = ['overview' => 'Overview', 'inspections' => 'Inspections', 'certificates' => 'Certificates', 'disease' => 'Disease'];
$tab = $_GET['tab'] ?? 'overview'; if (!isset($tabs[$tab])) $tab = 'overview';
$inspections = [
  ['SI001','Sunrise Eatery','Food Establishment','Halang','2026-06-08','2026-07-08','conditional',78,2,['Minor pest activity found','Handwashing station needs soap']],
  ['SI002','Mabini Public Market','Public Market','Mabini','2026-06-07','2026-06-21','failed',55,5,['Improper waste segregation','Blocked drainage channel','No valid sanitary permit']],
  ['SI003','San Jose Water Refilling','Water Refilling','San Jose','2026-06-05','2026-09-05','passed',98,0,['Valid sanitary permit','Water analysis up to date']],
  ['SI004','Poblacion Day Care','Child Care Center','Poblacion','2026-06-04','2026-09-04','passed',94,0,['Clean facilities','Adequate handwashing stations']],
];
$certs = [['HC-2026-041','Health Certificate','Maria Santos','Food Handler','2026-06-10','2027-06-10',50,'issued'],['HC-2026-040','Sanitary Permit','Mabini Bakery','Business Renewal','2026-06-09','2027-06-09',300,'issued'],['HC-2026-039','Health Certificate','Jose Reyes','Employment','2026-06-08','2027-06-08',50,'issued']];
$diseases = [['DR-01','Dengue Fever','A90','Week 23',['Halang','Mabini'],8,true,'Barangay clean-up and larval source reduction',['0–4'=>1,'5–14'=>3,'15–49'=>4]],['DR-02','Leptospirosis','A27','Week 23',['Poblacion'],2,false,'Health education and water safety monitoring',['15–49'=>2]],['DR-03','Acute Bloody Diarrhea','A09','Week 22',['San Jose'],3,false,'Water source investigation',['0–4'=>2,'5–14'=>1]]];
$passed=count(array_filter($inspections,fn($i)=>$i[6]==='passed')); $failed=count(array_filter($inspections,fn($i)=>$i[6]==='failed')); $conditional=count(array_filter($inspections,fn($i)=>$i[6]==='conditional')); $avg=round(array_sum(array_column($inspections,7))/count($inspections));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sanitary Inspector Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">

    <header class="sticky top-0 z-40 bg-gradient-to-r from-teal-700 to-cyan-800 text-white shadow-xl">
        <div class="flex items-center justify-between px-4 py-3 sm:px-6">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 text-xl">♢</span>
                <div>
                    <h1 class="text-base font-bold">Sanitary Inspector Portal</h1>
                    <p class="text-xs text-teal-200">Nasugbu Rural Health Unit I</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="hidden rounded-lg bg-white/10 px-3 py-1.5 text-sm font-semibold sm:block">♥ Ramon Villareal</span>
                <a href="RHULogin.php" class="rounded-lg p-2 hover:bg-white/10">↪</a>
            </div>
        </div>
        <nav class="hidden gap-1 px-4 sm:flex">
            <?php foreach($tabs as $id=>$label): ?>
                <a href="?tab=<?= $id ?>" class="rounded-t-lg px-3 py-2 text-xs font-semibold <?= $tab===$id?'bg-white text-teal-700':'text-teal-100 hover:bg-white/10' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <main class="mx-auto max-w-5xl space-y-4 px-3 py-4 pb-20 sm:px-4 sm:py-6">
        <?php if($tab==='overview'): ?>
            <section class="flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4">
                <span class="text-red-600">⚠</span>
                <div>
                    <b class="text-sm text-red-800"><?= $failed ?> establishment(s) failed inspection</b>
                    <p class="text-sm text-red-700">
                        <?php foreach($inspections as $i) if($i[6]==='failed') echo e($i[1]); ?>
                    </p>
                </div>
                <a class="ml-auto rounded-lg bg-red-600 px-3 py-1.5 text-xs text-white" href="?tab=inspections">Review</a>
            </section>

            <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <?php foreach([['Passed',$passed,'text-green-600','bg-green-50'],['Conditional',$conditional,'text-yellow-600','bg-yellow-50'],['Failed',$failed,'text-red-600','bg-red-50'],['Avg Compliance',$avg.'%','text-teal-600','bg-teal-50']] as [$label,$value,$color,$bg]): ?>
                    <div class="<?= $bg ?> rounded-xl border border-white p-4 shadow-sm">
                        <b class="text-2xl font-black <?= $color ?>"><?= $value ?></b>
                        <p class="text-sm font-bold"><?= $label ?></p>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 font-bold">▣ Upcoming Re-inspections</h2>
                    <?php foreach($inspections as $i): if($i[6]==='passed') continue; ?>
                        <div class="mb-2 flex justify-between rounded-lg bg-gray-50 p-2.5">
                            <div>
                                <b class="block text-sm"><?= e($i[1]) ?></b>
                                <small class="text-gray-500"><?= e($i[2]) ?> · <?= e($i[3]) ?></small>
                            </div>
                            <div class="text-right">
                                <b class="text-xs text-teal-600"><?= $i[5] ?></b>
                                <small class="block rounded <?= $i[6]==='failed'?'bg-red-100 text-red-700':'bg-yellow-100 text-yellow-700' ?> px-1.5 text-xs">
                                    <?= $i[6] ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 font-bold">⌁ Disease Alerts (Environmental)</h2>
                    <?php foreach($diseases as $d): ?>
                        <div class="mb-2 flex justify-between rounded-lg <?= $d[6]?'border border-red-100 bg-red-50':'bg-gray-50' ?> p-2.5">
                            <div>
                                <b class="block text-sm"><?= e($d[1]) ?></b>
                                <small class="text-gray-500"><?= implode(', ',$d[4]) ?> · <?= $d[5] ?> cases</small>
                            </div>
                            <span class="text-xs font-bold <?= $d[6]?'text-red-700':'text-gray-600' ?>">
                                <?= $d[6]?'ALERT':'Watch' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

        <?php elseif($tab==='inspections'): ?>
            <div class="flex justify-between">
                <h2 class="text-xl font-bold">▣ Sanitation & Environmental Health Inspections</h2>
                <button class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">＋ New Inspection</button>
            </div>
            
            <div class="space-y-3">
                <?php foreach($inspections as $i): $color=$i[6]==='passed'?'green':($i[6]==='conditional'?'yellow':'red'); ?>
                    <article class="rounded-xl border-l-4 border-<?= $color ?>-400 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap justify-between gap-2">
                            <div>
                                <b><?= e($i[1]) ?></b>
                                <p class="text-xs text-gray-500"><?= e($i[2]) ?> · <?= e($i[3]) ?> · Inspector: Ramon Villareal</p>
                                <p class="text-xs text-gray-400">Inspected: <?= $i[4] ?> · Next: <?= $i[5] ?></p>
                            </div>
                            <div class="text-right">
                                <span class="rounded-full bg-<?= $color ?>-100 px-2 py-1 text-xs font-bold text-<?= $color ?>-700"><?= strtoupper($i[6]) ?></span>
                                <b class="mt-1 block text-lg text-<?= $color ?>-600"><?= $i[7] ?>%</b>
                                <small class="text-gray-400">compliance</small>
                            </div>
                        </div>
                        <div class="mt-3 h-2 rounded-full bg-gray-100">
                            <i class="block h-2 rounded-full bg-<?= $color ?>-500" style="width:<?= $i[7] ?>%"></i>
                        </div>
                        <p class="mt-3 text-xs font-bold text-gray-500">Findings (<?= $i[8] ?> violation<?= $i[8]===1?'':'s' ?>)</p>
                        <?php foreach($i[9] as $finding): ?>
                            <p class="mt-1 text-xs text-gray-600">• <?= e($finding) ?></p>
                        <?php endforeach; ?>
                        <div class="mt-3">
                            <button class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs text-white">Issue Notice</button>
                            <button class="ml-2 rounded-lg border border-gray-300 px-3 py-1.5 text-xs text-gray-600">Print Report</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php elseif($tab==='certificates'): ?>
            <div class="flex justify-between">
                <h2 class="text-xl font-bold">▤ Health Certificates</h2>
                <button class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white">＋ Issue Certificate</button>
            </div>
            
            <div class="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
                <table class="w-full min-w-[700px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Cert No.</th>
                            <th>Type</th>
                            <th>Recipient</th>
                            <th>Purpose</th>
                            <th>Issued</th>
                            <th>Valid Until</th>
                            <th>Fee</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach($certs as $c): ?>
                            <tr>
                                <td class="p-3 font-mono text-xs"><?= $c[0] ?></td>
                                <td><?= e($c[1]) ?></td>
                                <td class="font-semibold"><?= e($c[2]) ?></td>
                                <td><?= e($c[3]) ?></td>
                                <td><?= $c[4] ?></td>
                                <td><?= $c[5] ?></td>
                                <td><?= $c[6]?'₱'.$c[6]:'FREE' ?></td>
                                <td>
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700"><?= $c[7] ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <h2 class="text-xl font-bold">♢ Disease Surveillance — Environmental Focus</h2>
            <div class="space-y-3">
                <?php foreach($diseases as $d): ?>
                    <article class="rounded-xl border <?= $d[6]?'border-red-200 bg-red-50/30':'border-gray-100 bg-white' ?> p-4 shadow-sm">
                        <div class="flex justify-between">
                            <div>
                                <b><?= e($d[1]) ?> <?= $d[6]?'<small class="text-red-500">⚠ ALERT</small>':'' ?></b>
                                <p class="text-xs text-gray-500">ICD-10: <?= $d[2] ?> · <?= $d[3] ?> · Barangays: <?= implode(', ',$d[4]) ?></p>
                                <p class="mt-1 text-xs font-semibold">Action: <?= e($d[7]) ?></p>
                            </div>
                            <div class="text-right">
                                <b class="text-2xl <?= $d[5]>3?'text-red-600':'text-gray-900' ?>"><?= $d[5] ?></b>
                                <small class="block text-gray-400">cases</small>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            Age groups: <?php foreach($d[8] as $age=>$count) echo "$age: $count "; ?>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <nav class="fixed bottom-0 left-0 right-0 z-50 flex border-t bg-white sm:hidden">
        <?php foreach($tabs as $id=>$label): ?>
            <a href="?tab=<?= $id ?>" class="flex flex-1 justify-center py-2 text-[10px] font-semibold <?= $tab===$id?'text-teal-600':'text-gray-400' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>

</body>
</html>
