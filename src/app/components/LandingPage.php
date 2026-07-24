<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function e(mixed $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
function initials(string $name): string { $name = preg_replace('/^(Hon\. |Dr\. |Midwife |RN )/', '', $name);
return strtoupper(substr(implode('', array_map(fn($part) => $part[0] ?? '', explode(' ', $name))), 0, 2));
}
$tab = $_GET['tab'] ?? 'home';
if (!in_array($tab, ['home', 'services', 'events', 'contact'], true)) $tab = 'home';

$rhu = ['name'=>'Nasugbu Rural Health Unit I','municipality'=>'Nasugbu','province'=>'Batangas','address'=>'Poblacion, Nasugbu, Batangas','contact'=>'(043) 416-1234','hours'=>'Mon–Fri: 8:00 AM – 5:00 PM','mho'=>'Dr. Rosalinda V. Castillo','population'=>48230,'barangays'=>['Halang','Mabini','San Jose','Poblacion','Kumintang Ilaya','Kumintang Ibaba','Alangilan','Bolbok']];

$services = [['⚕','OPD Consultations','Mon–Fri, 8AM–5PM','bg-blue-100 text-blue-700'],['♟','Maternal & Child Health','Prenatal, delivery, postpartum','bg-pink-100 text-pink-700'],['⌁','Immunization (EPI)','Wed & Fri, 8AM–12NN','bg-indigo-100 text-indigo-700'],['♥','Family Planning','Every Tuesday, 8AM–12NN','bg-rose-100 text-rose-700'],['●','TB-DOTS Program','Daily drug collection, 8AM–5PM','bg-orange-100 text-orange-700'],['⌁','Disease Surveillance','PIDSR monitoring & response','bg-red-100 text-red-700'],['▤','Health Certificates','Medical, health & birth certs','bg-green-100 text-green-700'],['♢','Sanitation Inspection','Environmental health services','bg-teal-100 text-teal-700']];

$events = [['Jun 20','Blood Drive — Municipal Hall','Free BP check for all donors','bg-red-500'],['Jun 24','Free Cervical Cancer Screening','Halang Barangay Hall, 8AM–12NN','bg-pink-500'],['Jun 28','Senior Citizens Health Fair','Free ECG, glucose, BP monitoring','bg-blue-500'],['Jul 1–31','Nutrition Month (OPT+)','Free growth monitoring for 0–5 years','bg-green-500'],['Jul 10','Family Planning Day','RHU Main, free FP consultation','bg-rose-500'],['Aug 1','National Immunization Day','Free vaccines for children 0–5','bg-indigo-500']];

$officials = [['Hon. Antonio Jose A. Barcelon','Municipal Mayor','Office of the Municipal Mayor'],['Hon. Larry D. Albanio','Municipal Vice Mayor','Office of the Municipal Vice Mayor'],['Hon. Arlene R. Chuidian','Municipal Councilor','Committee on Health'],['Hon. Marcos H. Guevarra','Municipal Councilor','Committee on Women & Family'],['Hon. Anne Linette A. Vivas','Municipal Councilor','Committee on Budget & Finance'],['Hon. Dionisio B. Botobara','Municipal Councilor','Committee on Social Services'],['Hon. Jehiel B. Barcelon','Municipal Councilor','Committee on Social Services'],['Hon. Jose Mari S. Bautista','Municipal Councilor','Committee on Social Services'],['Hon. Wilfredo V. Limboc','Municipal Councilor','Committee on Social Services'],['Hon. King W. Gumba','Municipal Councilor','Committee on Social Services']];

$staff = [['Dr. Maria C. Santos','Municipal Health Officer','Public Health','Plantilla','Active'],['Dr. Joseph T. Ramos','Rural Health Physician','General Practice','Plantilla','Active'],['Midwife Rosario Peralta','Rural Midwife','Midwifery/OB','Plantilla','Active'],['RN Clara Mendez','Public Health Nurse I','Public Health Nursing','Plantilla','Active'],['RN Jose Figueroa','Public Health Nurse II','Epidemiology','Contractual','Pending'],['Ramon Villareal','Sanitary Inspector','Environmental Health','Plantilla','N/A']];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'contact') { $_SESSION['landing_messages'][] = ['name'=>trim($_POST['name'] ?? ''),'contact'=>trim($_POST['contact'] ?? ''),'subject'=>$_POST['subject'] ?? 'General Inquiry','message'=>trim($_POST['message'] ?? ''),'at'=>date('c')];
$_SESSION['landing_flash'] = 'Thank you. Your message has been sent to the RHU.';
header('Location: ?tab=contact');
exit;
}
$flash = $_SESSION['landing_flash'] ?? '';
unset($_SESSION['landing_flash']);

function serviceCards(array $services, bool $compact = false): void { foreach ($services as [$icon,$label,$detail,$color]) { echo '<div class="rounded-2xl border border-gray-100 bg-white p-'.($compact?'3.5':'5').' shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
<div class="mb-'.($compact?'3':'4').' flex h-'.($compact?'9':'11').' w-'.($compact?'9':'11').' items-center justify-center rounded-xl '.$color.' text-lg">'.$icon.'</div>
<h3 class="font-bold text-gray-900 '.($compact?'text-xs':'').'">'.e($label).'</h3>
<p class="mt-1 text-xs text-gray-500">'.e($detail).'</p>
</div>';
} }
function eventCards(array $events): void { foreach ($events as [$date,$title,$detail,$color]) { echo '<div class="flex items-start gap-4 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition hover:shadow-md">
<span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full '.$color.'">
</span>
<div>
<span class="rounded-full bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-400">'.e($date).'</span>
<p class="mt-2 font-bold text-gray-900">'.e($title).'</p>
<p class="mt-1 text-xs text-gray-500">'.e($detail).'</p>
<p class="mt-2 text-xs font-semibold text-emerald-600">FREE for residents ✓</p>
</div>
</div>';
} }
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>RedPulse RHU</title>
    <script src="https://cdn.tailwindcss.com">
    </script>
    <style>
      .safe {
        padding-bottom: env(safe-area-inset-bottom);
      }

      .mobile {
        display: block;
      }

      .desktop {
        display: none;
      }

      @media (min-width: 640px) {
        .mobile {
          display: none;
        }

        .desktop {
          display: block;
        }
      }

      @keyframes out {
        to {
          opacity: 0;
          visibility: hidden;
        }
      }

      #loader {
        animation: out .3s ease 1.65s forwards;
      }
    </style>
    </head>
    <body class="bg-white text-gray-900">
      <div id="loader" class="fixed inset-0 z-[100] flex items-center justify-center overflow-hidden bg-gradient-to-br from-emerald-800 via-teal-800 to-emerald-900">
        <div class="w-full max-w-xs px-6 text-center">
          <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl border border-white/20 bg-white/10 text-4xl text-white shadow-2xl">♥</div>
          <h1 class="mt-3 text-2xl font-black text-white">RedPulse RHU</h1>
          <p class="mt-1 text-xs text-emerald-300">
            <?= e($rhu['name']) ?>
          </p>
          <p class="mt-7 text-sm font-medium text-emerald-200">Preparing your health portal…</p>
          <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/10">
            <span class="block h-full w-full origin-left animate-pulse rounded-full bg-emerald-300">
            </span>
          </div>
          <div class="mt-5 flex flex-wrap justify-center gap-2">
            <?php foreach(['OPD','Immunization','Maternal Health','TB-DOTS','Nutrition'] as $item): ?>
            <span class="rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-xs text-emerald-200">
              <?= $item ?>
            </span>
            <?php endforeach;
            ?>
          </div>
        </div>
      </div>

      <div class="mobile min-h-screen pb-16">
        <header class="sticky top-0 z-50 flex items-center justify-between border-b border-gray-100 bg-white px-4 py-3 shadow-sm">
          <div class="flex items-center gap-2">
            <span class="text-2xl text-emerald-600">♥</span>
            <div>
              <b class="block text-base leading-none">RedPulse RHU</b>
              <small class="text-gray-400">
                <?= e($rhu['municipality']) ?>, <?= e($rhu['province']) ?>
              </small>
            </div>
          </div>
        <div class="flex gap-2">
        <a href="ResidentLogin.php"
          class="rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700">
            Sign In
        </a>

        <a href="ResidentRegistration.php"
          class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white">
            Register
        </a>
        </div>
        </header>
        <?php if ($flash): ?>
        <p class="mx-4 mt-4 rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">✓ <?= e($flash) ?>
        </p>
        <?php endif;
        ?>
        <?php if ($tab === 'home'): ?>
        <section class="bg-gradient-to-br from-emerald-700 to-teal-800 px-5 pb-10 pt-8 text-white">
          <span class="rounded-full border border-white/20 bg-white/15 px-3 py-1 text-xs font-semibold">⌖ <?= e($rhu['municipality']) ?> Rural Health Unit</span>
          <h1 class="mt-4 text-3xl font-black leading-tight">Your Health,<br>
            <span class="text-emerald-300">Our Priority.</span>
          </h1>
          <p class="mt-3 text-sm leading-relaxed text-emerald-100">Free health services, records, certificates and community programs for all residents of <?= e($rhu['municipality']) ?>.</p>
          <div class="mt-6 flex gap-3">
            <a href="ResidentLogin.php" class="flex-1 rounded-xl bg-white py-3 text-center text-sm font-bold text-emerald-700">Access My Records</a>
            <a href="ResidentRegistration.php" class="flex-1 rounded-xl border border-white/30 bg-white/15 py-3 text-center text-sm font-semibold">Register Free</a>
          </div>
        </section>
        <section class="grid grid-cols-3 gap-2 bg-gray-900 px-5 py-4 text-center text-white">
          <div>
            <b class="text-xl text-emerald-400">
              <?= number_format($rhu['population']) ?>
            </b>
            <small class="block text-gray-400">Population</small>
          </div>
          <div>
            <b class="text-xl text-emerald-400">23</b>
            <small class="block text-gray-400">Programs</small>
          </div>
          <div>
            <b class="text-xl text-emerald-400">FREE</b>
            <small class="block text-gray-400">For All</small>
          </div>
        </section>
        <p class="mx-4 mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-relaxed text-red-700">⚑ <b>Dengue Alert:</b> Active cases in Halang & Mabini. Clean your surroundings and eliminate mosquito breeding sites.</p>
        <section class="px-4 pt-6">
          <div class="mb-3 flex justify-between">
            <b>Our Services</b>
            <small class="font-semibold text-emerald-600">All FREE</small>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <?php serviceCards($services, true);
            ?>
          </div>
        </section>
        <section class="px-4 pt-7">
          <b>Upcoming Events</b>
          <div class="mt-3 space-y-2">
            <?php foreach($events as [$date,$title,$detail,$color]): ?>
            <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white p-3 shadow-sm">
              <i class="h-2.5 w-2.5 rounded-full <?= $color ?>">
              </i>
              <div class="min-w-0 flex-1">
                <b class="block truncate text-sm">
                  <?= e($title) ?>
                </b>
                <small class="block truncate text-gray-500">
                  <?= e($detail) ?>
                </small>
              </div>
              <span class="rounded-lg bg-gray-50 px-2 py-1 font-mono text-xs text-gray-400">
                <?= e($date) ?>
              </span>
            </div>
            <?php endforeach;
            ?>
          </div>
        </section>
        <section class="mx-4 mt-7 rounded-2xl bg-emerald-600 p-5 text-center text-white">
          <p class="text-lg font-black">Register as a Resident</p>
          <p class="mt-1 text-sm text-emerald-100">Access your health records, immunization card & certificates anytime.</p>
          <a href="ResidentRegistration.php" class="mt-4 inline-block rounded-xl bg-white px-6 py-2.5 text-sm font-bold text-emerald-700">Create Free Account</a>
        </section>
        <?php elseif ($tab === 'services'): ?>
        <section class="px-4 pt-5">
          <h2 class="text-lg font-black">Our Services</h2>
          <p class="mb-4 text-xs text-gray-400">All FREE for <?= e($rhu['municipality']) ?> residents</p>
          <div class="grid grid-cols-2 gap-3">
            <?php serviceCards($services, true);
            ?>
          </div>
          <div class="mt-5 rounded-2xl bg-emerald-600 p-4 text-center text-white">
            <b>Access All Services</b>
            <p class="mt-1 text-xs text-emerald-100">Register your account to track health records online.</p>
            <a class="mt-3 inline-block rounded-xl bg-white px-5 py-2 text-sm font-bold text-emerald-700" href="ResidentRegistration.php">Register Free</a>
          </div>
        </section>
        <?php elseif ($tab === 'events'): ?>
        <section class="px-4 pt-5">
          <h2 class="text-lg font-black">Health Events</h2>
          <p class="mb-4 text-xs text-gray-400">Upcoming programs in <?= e($rhu['municipality']) ?>
          </p>
          <div class="space-y-2">
            <?php eventCards($events);
            ?>
          </div>
        </section>
        <?php else: ?>
        <section class="px-4 pt-5">
          <h2 class="mb-4 text-lg font-black">Contact the RHU</h2>
          <div class="mb-4 divide-y rounded-2xl border border-gray-100 bg-white shadow-sm">
            <?php foreach([['⌖','Address',$rhu['address']],['☎','Contact',$rhu['contact']],['◷','Hours',$rhu['hours']],['⚕','MHO',$rhu['mho']]] as [$icon,$label,$value]): ?>
            <div class="flex gap-3 p-4">
              <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                <?= $icon ?>
              </span>
              <div>
                <small class="font-semibold text-gray-400">
                  <?= $label ?>
                </small>
                <p class="text-sm text-gray-700">
                  <?= e($value) ?>
                </p>
              </div>
            </div>
            <?php endforeach;
            ?>
          </div>
          <?php endif;
          ?>
          <?php if ($tab === 'contact'): ?>
          <form method="post" class="space-y-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
            <input type="hidden" name="form" value="contact">
            <b>Send a Message</b>
            <input required name="name" placeholder="Your name" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
            <input name="contact" placeholder="Contact number" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
            <select name="subject" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
              <option>General Inquiry</option>
              <option>Appointment Request</option>
              <option>Certificate Request</option>
              <option>Health Concern</option>
              <option>Feedback / Complaint</option>
            </select>
            <textarea required name="message" rows="3" placeholder="Your message…" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
            </textarea>
            <button class="w-full rounded-xl bg-emerald-600 py-3 text-sm font-bold text-white">Send Message</button>
          </form>
        </section>
        <?php endif;
        ?>
        <nav class="safe fixed bottom-0 left-0 right-0 z-50 flex border-t border-gray-200 bg-white">
          <?php foreach(['home'=>['⌂','Home'],'services'=>['▦','Services'],'events'=>['▣','Events'],'contact'=>['☎','Contact']] as $id=>[$icon,$label]): ?>
          <a href="?tab=<?= $id ?>" class="relative flex flex-1 flex-col items-center py-2 text-xs font-semibold <?= $tab===$id?'text-emerald-600':'text-gray-400' ?>">
            <?= $icon ?>
            <span>
              <?= $label ?>
            </span>
            <?php if($tab===$id): ?>
            <i class="absolute bottom-0 h-0.5 w-8 rounded bg-emerald-500">
            </i>
            <?php endif;
            ?>
          </a>
          <?php endforeach;
          ?>
        </nav>
      </div>

      <div class="desktop">
        <nav class="sticky top-0 z-50 border-b border-gray-100 bg-white/95 shadow-sm backdrop-blur">
          <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
            <a href="#top" class="flex items-center gap-2">
              <span class="text-3xl text-red-600">♥</span>
              <span>
                <b class="block text-lg leading-none">RedPulse RHU</b>
                <small class="text-gray-500">
                  <?= e($rhu['municipality']) ?>, <?= e($rhu['province']) ?>
                </small>
              </span>
            </a>
            <div class="flex items-center gap-4">
              <a href="#services" class="text-sm text-gray-600">Services</a>
              <a href="#events" class="text-sm text-gray-600">Events</a>
              <a href="#contact" class="text-sm text-gray-600">Contact</a>

              <a href="ResidentLogin.php"
                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                  Sign In
              </a>

              <a href="ResidentRegistration.php"
                class="rounded-lg border-2 border-emerald-600 px-4 py-2 text-sm font-semibold text-emerald-700">
                  Register
              </a>
          </div>
          </div>
        </nav>
        <section id="top" class="bg-gradient-to-br from-emerald-700 via-teal-700 to-emerald-900 text-white">
          <div class="mx-auto grid max-w-6xl grid-cols-2 items-center gap-12 px-6 py-24">
            <div>
              <span class="rounded-full border border-white/20 bg-white/15 px-4 py-1.5 text-sm font-semibold">⌖ <?= e($rhu['municipality']) ?> Rural Health Unit</span>
              <h1 class="mt-6 text-5xl font-black leading-tight">Your Health,<br>
                <span class="text-emerald-300">Our Priority.</span>
              </h1>
              <p class="mt-5 text-lg leading-relaxed text-emerald-100">The official digital health portal of <b class="text-white">
                <?= e($rhu['name']) ?>
              </b>. Access your health records, immunization card, certificates, and community health programs — all in one place.</p>
              <div class="mt-8 flex gap-4">
                <a href="ResidentLogin.php" class="rounded-xl bg-white px-7 py-3.5 font-bold text-emerald-700">♟ Access My Health Portal</a>
                <a href="#services" class="rounded-xl border border-white/30 bg-white/15 px-7 py-3.5 font-semibold">Our Services →</a>
              </div>
              <p class="mt-8 text-sm text-emerald-200">✓ Free to register &nbsp;
                ✓ Secure & private &nbsp;
                ✓ 24/7 access</p>
              </div>
              <aside class="rounded-2xl border border-white/20 bg-white/10 p-6 shadow-xl">
                <p class="text-xs font-bold uppercase tracking-widest text-emerald-300">As a registered resident, you can:</p>
                <?php foreach(['View consultation history & prescriptions','Check your immunization schedule & card','See upcoming RHU health events','Download medical & health certificates','Get reminders for follow-ups & vaccines','Send messages to RHU staff'] as $benefit): ?>
                <p class="mt-4 text-sm text-emerald-100">✓ <?= $benefit ?>
                </p>
                <?php endforeach;
                ?>
                <a class="mt-5 block rounded-xl bg-emerald-500 py-2.5 text-center text-sm font-semibold" href="ResidentRegistration.php">Register Now — It’s Free</a>
              </aside>
            </div>
          </section>
          <section class="bg-gray-900 py-8 text-white">
            <div class="mx-auto grid max-w-6xl grid-cols-4 gap-6 px-6 text-center">
              <div>
                <b class="text-3xl text-emerald-400">
                  <?= number_format($rhu['population']) ?>
                </b>
                <p class="mt-1 text-sm text-gray-400">Population Served</p>
              </div>
              <div>
                <b class="text-3xl text-emerald-400">
                  <?= count($rhu['barangays']) ?>
                </b>
                <p class="mt-1 text-sm text-gray-400">Barangays Covered</p>
              </div>
              <div>
                <b class="text-3xl text-emerald-400">23</b>
                <p class="mt-1 text-sm text-gray-400">Health Programs</p>
              </div>
              <div>
                <b class="text-3xl text-emerald-400">FREE</b>
                <p class="mt-1 text-sm text-gray-400">For All Residents</p>
              </div>
            </div>
          </section>
          <section id="services" class="mx-auto max-w-6xl px-6 py-20">
            <div class="mb-12 text-center">
              <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">What We Offer</p>
              <h2 class="mt-2 text-3xl font-black">RHU Health Services</h2>
              <p class="mt-3 text-gray-500">All services are free for registered residents of <?= e($rhu['municipality']) ?>. Walk-in patients are welcome.</p>
            </div>
            <div class="grid grid-cols-4 gap-4">
              <?php serviceCards($services);
              ?>
            </div>
            <div class="mt-8 flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
              <div>
                <b class="text-lg text-emerald-900">Register as a Resident</b>
                <p class="text-sm text-emerald-700">Create your health profile to access all services and your records online.</p>
              </div>
              <a href="ResidentRegistration.php" class="rounded-xl bg-emerald-600 px-6 py-3 font-semibold text-white">Get Started →</a>
            </div>
          </section>
          <section id="events" class="bg-gray-50 py-20">
            <div class="mx-auto max-w-6xl px-6">
              <div class="mb-12 text-center">
                <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Stay Updated</p>
                <h2 class="mt-2 text-3xl font-black">Upcoming Health Events</h2>
                <p class="mt-3 text-gray-500">Community health programs open to all residents of <?= e($rhu['municipality']) ?>.</p>
              </div>
              <div class="grid grid-cols-3 gap-4">
                <?php eventCards($events);
                ?>
              </div>
            </div>
          </section>
          <section class="mx-auto max-w-6xl px-6 py-20">
            <div class="mb-12 text-center">
              <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Simple & Easy</p>
              <h2 class="mt-2 text-3xl font-black">How the Resident Portal Works</h2>
            </div>
            <div class="grid grid-cols-3 gap-8 text-center">
              <?php foreach([['1','Register Your Account','Create a free account using your name, barangay, contact number, and email.'],['2','View Your Health Records','Once verified by RHU staff, your health history and results appear in your portal.'],['3','Stay Connected','Get reminders for follow-ups, vaccines, and health programs.']] as [$n,$title,$text]): ?>
              <div>
                <b class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-600 text-2xl text-white">
                  <?= $n ?>
                </b>
                <h3 class="mt-4 text-xl font-bold">
                  <?= $title ?>
                </h3>
                <p class="mt-3 text-sm leading-relaxed text-gray-500">
                  <?= $text ?>
                </p>
              </div>
              <?php endforeach;
              ?>
            </div>
          </section>
          <section class="bg-emerald-700 py-16 text-center text-white">
            <h2 class="text-3xl font-black">Trusted by <?= e($rhu['municipality']) ?> Residents</h2>
            <p class="mx-auto mt-3 max-w-xl text-emerald-200">Your health data is protected under the <b class="text-white">Data Privacy Act (RA 10173)</b>. Only you and authorized RHU personnel can access your records.</p>
            <div class="mx-auto mt-8 grid max-w-3xl grid-cols-3 gap-4">
              <?php foreach([['♢','RA 10173 Compliant','Data Privacy Act'],['✓','DOH Accredited','Health Information System'],['★','Free for All','No fees, no hidden charges']] as [$icon,$title,$sub]): ?>
              <div class="rounded-2xl border border-white/20 bg-white/10 p-5">
                <b class="text-2xl text-emerald-300">
                  <?= $icon ?>
                </b>
                <p class="mt-2 font-bold">
                  <?= $title ?>
                </p>
                <small class="text-emerald-300">
                  <?= $sub ?>
                </small>
              </div>
              <?php endforeach;
              ?>
            </div>
            <a href="ResidentRegistration.php" class="mt-10 inline-block rounded-xl bg-white px-8 py-4 font-bold text-emerald-700">♟ Create My Resident Account</a>
          </section>
          <section class="mx-auto max-w-6xl px-6 py-20">
            <div class="mb-10 text-center">
              <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Local Government Unit</p>
              <h2 class="mt-2 text-3xl font-black">Municipal Officials</h2>
              <p class="mt-3 text-gray-500">We acknowledge the support of the <?= e($rhu['municipality']) ?> Municipal Government.</p>
            </div>
            <div class="grid grid-cols-5 gap-4">
              <?php foreach($officials as $i=>[$name,$position,$office]): ?>
              <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4 text-center">
                <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full <?= $i<2?'bg-emerald-600':'bg-slate-600' ?> text-lg font-black text-white">
                  <?= e(initials($name)) ?>
                </span>
                <p class="mt-3 text-xs font-bold">
                  <?= e($name) ?>
                </p>
                <p class="mt-1 text-xs font-semibold text-emerald-600">
                  <?= e($position) ?>
                </p>
                <p class="mt-1 text-xs text-gray-400">
                  <?= e($office) ?>
                </p>
              </div>
              <?php endforeach;
              ?>
            </div>
            <div class="mb-10 mt-20 text-center">
              <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Meet Our Team</p>
              <h2 class="mt-2 text-3xl font-black">RHU Health Professionals</h2>
              <p class="mt-3 text-gray-500">Dedicated public health servants committed to quality, accessible care.</p>
            </div>
            <div class="grid grid-cols-3 gap-5">
              <?php foreach($staff as $i=>[$name,$position,$specialty,$employment,$philhealth]): ?>
              <div class="flex gap-4 rounded-2xl border border-gray-100 p-5 shadow-sm">
                <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl <?= ['bg-emerald-600','bg-blue-600','bg-pink-600','bg-teal-600','bg-indigo-600','bg-cyan-700'][$i] ?> text-lg font-black text-white">
                  <?= e(initials($name)) ?>
                </span>
                <div>
                  <b>
                    <?= e($name) ?>
                  </b>
                  <p class="text-sm font-semibold text-emerald-700">
                    <?= e($position) ?>
                  </p>
                  <p class="mt-1 text-xs text-gray-400">
                    <?= e($specialty) ?>
                  </p>
                  <p class="mt-2 text-xs">
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700">
                      <?= e($employment) ?>
                    </span> <span class="rounded-full bg-blue-100 px-2 py-0.5 text-blue-700">PhilHealth <?= e($philhealth) ?>
                  </span>
                </p>
              </div>
            </div>
            <?php endforeach;
            ?>
          </div>
        </section>
        <section id="contact" class="bg-gray-50 py-20">
          <div class="mx-auto grid max-w-4xl grid-cols-2 gap-8 px-6">
            <div>
              <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Find Us</p>
              <h2 class="mt-2 text-3xl font-black">Contact the RHU</h2>
              <?php foreach([['⌖','Address',$rhu['address']],['☎','Contact Number',$rhu['contact']],['◷','Operating Hours',$rhu['hours']],['⚕','Chief MHO',$rhu['mho']]] as [$icon,$label,$value]): ?>
              <div class="mt-5 flex gap-4">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                  <?= $icon ?>
                </span>
                <div>
                  <small class="font-bold uppercase tracking-wide text-gray-400">
                    <?= $label ?>
                  </small>
                  <p class="text-gray-800">
                    <?= e($value) ?>
                  </p>
                </div>
              </div>
              <?php endforeach;
              ?>
              <p class="mt-5 rounded-xl border border-gray-200 p-4 text-sm text-gray-500">
                <b class="text-gray-700">Barangays Covered</b>
                <br>
                <?= e(implode(' · ', $rhu['barangays'])) ?>
              </p>
            </div>
            <form method="post" class="rounded-2xl border border-gray-200 bg-white p-6">
              <input type="hidden" name="form" value="contact">
              <h3 class="mb-4 font-bold">Send Us a Message</h3>
              <input required name="name" placeholder="Your name" class="mb-3 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm">
              <input name="contact" placeholder="Contact number" class="mb-3 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm">
              <select name="subject" class="mb-3 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm">
                <option>General Inquiry</option>
                <option>Appointment Request</option>
                <option>Certificate Request</option>
                <option>Health Concern</option>
                <option>Feedback / Complaint</option>
              </select>
              <textarea required name="message" rows="4" placeholder="Your message..." class="mb-3 w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm">
              </textarea>
              <button class="w-full rounded-xl bg-emerald-600 py-3 font-semibold text-white">Send Message</button>
              <?php if($flash): ?>
              <p class="mt-3 text-sm text-green-700">✓ <?= e($flash) ?>
              </p>
              <?php endif;
              ?>
            </form>
          </div>
        </section>
        <footer class="bg-gray-900 py-12 text-gray-400">
          <div class="mx-auto grid max-w-6xl grid-cols-3 gap-8 px-6">
            <div>
              <p class="text-lg font-bold text-white">♥ RedPulse RHU</p>
              <p class="mt-3 text-sm">The official health information system of <?= e($rhu['name']) ?>. Serving residents of <?= e($rhu['municipality']) ?>, <?= e($rhu['province']) ?>.</p>
            </div>
            <div>
              <b class="text-white">Quick Links</b>
              <p class="mt-3 space-y-1 text-sm">
                <a class="block" href="ResidentLogin.php">Resident Portal</a>
                <a class="block" href="#services">Our Services</a>
                <a class="block" href="#events">Health Events</a>
                <a class="block" href="#contact">Contact Us</a>
              </p>
            </div>
            <div>
              <b class="text-white">Legal & Compliance</b>
              <p class="mt-3 text-sm">✓ Data Privacy Act (RA 10173)<br>✓ Universal Health Care Act<br>✓ DOH Digital Health Framework<br>✓ PhilHealth Integration</p>
            </div>
          </div>
          <div class="mx-auto mt-8 flex max-w-6xl justify-between border-t border-gray-800 px-6 pt-6 text-xs text-gray-600">
            <span>© 2026 <?= e($rhu['name']) ?>. All rights reserved.</span>
            <span>
              <a href="RHULogin.php">Staff Portal</a> &nbsp;
              <a href="RHUAdminLogin.php">Admin</a> &nbsp;
              <a href="BHWLogin.php">BHW</a>
            </span>
          </div>
        </footer>
      </div>
    </body>
  </html>
