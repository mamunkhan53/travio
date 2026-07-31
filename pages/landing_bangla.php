<?php
function renderBanglaLandingPage($conn) {
    $visitorCountry = getVisitorCountryName();
    $showUSD = ($visitorCountry !== null && strcasecmp($visitorCountry, 'Bangladesh') !== 0);
    $homeCurrencySymbol = $showUSD ? '$' : '৳';
?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap');

        #sz-home {
            --sz-teal: #2BC4B0;
            --sz-teal-light: #61DAFB;
            --sz-purple: #9A8FE0;
            --sz-dark-surface: rgba(22, 26, 35, 0.66);
            --sz-dark-deep: #03181F;
            --sz-darkest: #0A0C11;
            --sz-text-primary: #EAEDF3;
            --sz-text-secondary: #9AA4B2;
            --sz-text-tertiary: #68727F;
            --sz-border-subtle: rgba(255, 255, 255, 0.09);
            --sz-border-overlay: rgba(255, 255, 255, 0.04);
            --sz-shadow-glow: rgba(43, 196, 176, 0.6);
            --sz-shadow-glow-alt: rgba(43, 196, 176, 0.7);
            --sz-radius-card: 16px;
            --sz-radius-btn: 12px;
            --sz-radius-badge: 100px;
            --sz-transition: all 0.25s ease;
        }
        #sz-home * { box-sizing: border-box; }
        #sz-home .sz-bn { font-family: 'Hind Siliguri', sans-serif; }
        #sz-home .sz-display { font-family: 'Space Grotesk', 'Hind Siliguri', sans-serif; letter-spacing: -0.01em; }
        #sz-home .sz-body { font-family: 'Hind Siliguri', 'Inter', sans-serif; }

        #sz-home .sz-badge {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 4px 12px; border-radius: var(--sz-radius-badge);
            font-size: 12px; font-weight: 600; text-transform: uppercase;
            letter-spacing: 0.03em; font-family: 'Inter', sans-serif; height: 24px;
        }
        #sz-home .sz-card {
            background: var(--sz-dark-surface); border: 1px solid var(--sz-border-subtle);
            border-radius: var(--sz-radius-card); padding: 22px;
            transition: var(--sz-transition); color: var(--sz-text-primary);
        }
        #sz-home .sz-card:hover { border-color: rgba(43,196,176,0.3); background: rgba(22,26,35,0.75); }
        #sz-home .sz-glow-btn-filled {
            background: var(--sz-teal); color: #FFFFFF; padding: 11px 20px; border: none;
            border-radius: var(--sz-radius-btn); font-family: 'Hind Siliguri', sans-serif;
            font-size: 14.5px; font-weight: 600;
            box-shadow: var(--sz-shadow-glow) 0px 10px 30px -10px; transition: var(--sz-transition);
            height: 47px; display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none; cursor: pointer;
        }
        #sz-home .sz-glow-btn-filled:hover { background: #1FB8A4; box-shadow: var(--sz-shadow-glow-alt) 0px 15px 40px -8px; transform: translateY(-2px); color:#FFFFFF; }
        #sz-home .sz-ghost-btn {
            background: rgba(255,255,255,0.04); border: 1px solid var(--sz-border-subtle);
            padding: 8px 15px; border-radius: var(--sz-radius-btn);
            font-family: 'Hind Siliguri', sans-serif; font-size: 13.5px; font-weight: 600;
            color: var(--sz-text-primary); transition: var(--sz-transition); height: 40px;
            display: inline-flex; align-items: center; justify-content: center;
            text-decoration: none; cursor: pointer; gap: 8px;
        }
        #sz-home .sz-ghost-btn:hover { background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.15); color: var(--sz-text-primary); }
        #sz-home .sz-btn-outline-light {
            background: transparent; color: #FFFFFF; padding: 11px 20px;
            border: 1px solid rgba(255,255,255,0.2); border-radius: var(--sz-radius-btn);
            font-family: 'Hind Siliguri', sans-serif; font-size: 14.5px; font-weight: 600;
            transition: var(--sz-transition); height: 47px; display: inline-flex;
            align-items: center; justify-content: center; text-decoration: none; cursor: pointer; gap: 8px;
        }
        #sz-home .sz-btn-outline-light:hover { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.4); transform: translateY(-2px); }
        #sz-home .sz-blob { filter: blur(70px); pointer-events: none; }
        #sz-home .sz-float { animation: sz-float 5s ease-in-out infinite; }
        #sz-home .sz-float-delayed { animation: sz-float 5s ease-in-out infinite; animation-delay: 1.5s; }
        #sz-home .sz-float-delayed-2 { animation: sz-float 5s ease-in-out infinite; animation-delay: 3s; }
        @keyframes sz-float { 0%{transform:translateY(0)} 50%{transform:translateY(-14px)} 100%{transform:translateY(0)} }
        @keyframes sz-pulse-ring { 0%{box-shadow:0 0 0 0 rgba(43,196,176,0.35)} 100%{box-shadow:0 0 0 8px rgba(43,196,176,0)} }
        #sz-home .sz-pulse-dot { animation: sz-pulse-ring 1.8s ease-out infinite; }
        #sz-home .sz-bg-grid {
            background-image: linear-gradient(rgba(255,255,255,0.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.02) 1px,transparent 1px);
            background-size: 40px 40px;
        }
        #sz-home .sz-faq-item { border:1px solid var(--sz-border-subtle); border-radius:var(--sz-radius-card); background:var(--sz-dark-surface); transition:var(--sz-transition); overflow:hidden; }
        #sz-home .sz-faq-item:hover { border-color:rgba(43,196,176,0.3); }
        #sz-home .sz-faq-question { width:100%; padding:20px 24px; background:transparent; border:none; color:var(--sz-text-primary); font-family:'Hind Siliguri',sans-serif; font-size:16px; font-weight:600; cursor:pointer; display:flex; justify-content:space-between; align-items:center; text-align:left; transition:var(--sz-transition); }
        #sz-home .sz-faq-question:hover { color:var(--sz-teal); }
        #sz-home .sz-faq-question .sz-faq-icon { transition:transform 0.3s ease; color:var(--sz-teal); font-size:18px; flex-shrink:0; margin-left:16px; }
        #sz-home .sz-faq-question.active .sz-faq-icon { transform:rotate(180deg); }
        #sz-home .sz-faq-answer { max-height:0; overflow:hidden; transition:max-height 0.4s ease,padding 0.4s ease; padding:0 24px; color:var(--sz-text-secondary); font-size:15px; line-height:1.8; font-family:'Hind Siliguri',sans-serif; }
        #sz-home .sz-faq-answer.open { max-height:300px; padding:0 24px 20px 24px; }

        @media(max-width:1023px) { #sz-home .sz-hero-title { font-size:2.75rem !important; } #sz-home .sz-card { padding:18px; } }
        @media(max-width:767px) { #sz-home .sz-hero-title { font-size:2rem !important; } #sz-home .sz-hero-sub { font-size:1rem !important; } #sz-home .sz-card { padding:16px; } #sz-home .sz-glow-btn-filled,#sz-home .sz-btn-outline-light { height:42px; font-size:13px; padding:9px 16px; } #sz-home .sz-ghost-btn { height:36px; font-size:12.5px; padding:6px 12px; } #sz-home .sz-faq-question { padding:16px 18px; font-size:14px; } #sz-home .sz-faq-answer { font-size:14px; } #sz-home .sz-faq-answer.open { padding:0 18px 16px 18px; } }
        @media(max-width:479px) { #sz-home .sz-hero-title { font-size:1.6rem !important; } }
    </style>

    <div id="sz-home" class="sz-body" style="background:var(--sz-dark-deep);color:var(--sz-text-primary);min-height:100vh;">

    <!-- ========== NAV ========== -->
    <nav class="bg-[#0A0C11]/80 backdrop-blur-md border-b border-[rgba(255,255,255,0.06)] py-4 fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <div class="w-10 h-10 bg-gradient-to-br from-[#2BC4B0] to-[#61DAFB] rounded-xl flex items-center justify-center text-white shadow-lg shadow-[#2BC4B0]/20">
                    <i class="fa-solid fa-plane-departure text-xl"></i>
                </div>
                <h1 class="sz-display text-2xl font-bold text-white tracking-tight">Travio</h1>
            </div>
            <div class="hidden md:flex items-center space-x-8">
                <a href="#features" class="sz-bn text-sm font-medium text-[#9AA4B2] hover:text-[#2BC4B0] transition">ফিচার</a>
                <a href="#how-it-works" class="sz-bn text-sm font-medium text-[#9AA4B2] hover:text-[#2BC4B0] transition">কীভাবে কাজ করে</a>
                <a href="#pricing" class="sz-bn text-sm font-medium text-[#9AA4B2] hover:text-[#2BC4B0] transition">মূল্য তালিকা</a>
                <a href="#faq" class="sz-bn text-sm font-medium text-[#9AA4B2] hover:text-[#2BC4B0] transition">সাধারণ প্রশ্ন</a>
            </div>
            <div class="flex items-center space-x-4">
                <a href="?route=login" class="sz-bn text-sm font-medium text-[#9AA4B2] hover:text-[#2BC4B0] transition hidden sm:block">লগইন</a>
                <a href="?route=register" class="sz-glow-btn-filled sz-bn">বিনামূল্যে শুরু করুন</a>
            </div>
        </div>
    </nav>

    <!-- ========== HERO ========== -->
    <section class="pt-32 pb-20 lg:pt-44 lg:pb-28 relative overflow-hidden" style="background:var(--sz-dark-deep);">
        <div class="absolute inset-0 sz-bg-grid opacity-30"></div>
        <div class="sz-blob absolute top-0 right-0 -mt-20 -mr-20 w-96 h-96 bg-[#2BC4B0]/20 rounded-full"></div>
        <div class="sz-blob absolute bottom-0 left-0 -mb-20 -ml-20 w-72 h-72 bg-[#61DAFB]/15 rounded-full"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="text-center lg:text-left">
                    <div class="sz-badge" style="background:rgba(43,196,176,0.15);border:1px solid rgba(43,196,176,0.3);color:var(--sz-teal);margin-bottom:24px;">
                        <span class="w-2 h-2 rounded-full" style="background:var(--sz-teal);"></span>
                        <span class="sz-pulse-dot" style="display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--sz-teal);"></span>
                        ক্লাউড ইআরপি SaaS v2.0
                    </div>
                    <h1 class="sz-display sz-hero-title text-5xl lg:text-6xl font-bold text-white leading-tight mb-6 tracking-tight sz-bn">
                        আপনার ট্রাভেল ব্যবসা <br>পরিচালনা করুন <span style="background:linear-gradient(135deg,#2BC4B0,#61DAFB);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">স্মার্টভাবে</span>
                    </h1>
                    <p class="sz-hero-sub sz-bn text-lg text-[#9AA4B2] mb-8 max-w-xl mx-auto lg:mx-0 leading-relaxed">
                        এয়ার টিকেটিং, ভিসা, ট্যুর প্যাকেজ, হোটেল বুকিং ও কাস্টমার ম্যানেজমেন্টের জন্য সম্পূর্ণ ট্রাভেল এজেন্সি ইআরপি সমাধান।
                    </p>
                    <div class="flex flex-col sm:flex-row items-center gap-4 justify-center lg:justify-start mb-8">
                        <a href="?route=register" class="sz-glow-btn-filled w-full sm:w-auto px-8 py-4 text-lg sz-bn">বিনামূল্যে শুরু করুন</a>
                        <a href="#" class="sz-ghost-btn w-full sm:w-auto px-8 py-4 text-lg rounded-xl flex items-center justify-center gap-2 sz-bn">
                            <i class="fa-solid fa-circle-play"></i> ডেমো দেখুন
                        </a>
                    </div>
                    <p class="sz-bn text-sm font-medium text-[#68727F] flex items-center justify-center lg:justify-start gap-2">
                        <i class="fa-solid fa-shield-check" style="color:#3FA85F;"></i> ট্রাভেল এজেন্সির জন্য তৈরি • নিরাপদ • সহজ ব্যবহার • ক্লাউড ভিত্তিক
                    </p>
                </div>

                <div class="relative hidden lg:block">
                    <div class="relative rounded-2xl overflow-hidden shadow-2xl border border-[rgba(255,255,255,0.09)] bg-[#0A0C11] z-10 sz-float">
                        <img src="https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=800&q=80" alt="Travel Agency Dashboard" class="w-full h-[450px] object-cover opacity-70">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#03181F]/80 to-transparent"></div>
                    </div>
                    <div class="absolute -left-12 top-20 bg-[rgba(22,26,35,0.8)] backdrop-blur px-6 py-4 rounded-2xl shadow-xl border border-[rgba(255,255,255,0.09)] z-20 flex items-center gap-4 sz-float-delayed">
                        <div class="w-12 h-12 bg-[rgba(63,168,95,0.15)] text-[#3FA85F] rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-wallet"></i></div>
                        <div>
                            <p class="text-xs text-[#68727F] font-bold uppercase tracking-wider">আজকের বিক্রয়</p>
                            <p class="sz-display text-xl font-bold text-white"><?= $showUSD ? '$1,584' : '৳ ১,৭৪,২০০' ?></p>
                        </div>
                    </div>
                    <div class="absolute -right-8 bottom-32 bg-[rgba(22,26,35,0.8)] backdrop-blur px-6 py-4 rounded-2xl shadow-xl border border-[rgba(255,255,255,0.09)] z-20 flex items-center gap-4 sz-float">
                        <div class="w-12 h-12 bg-[rgba(43,196,176,0.15)] text-[#2BC4B0] rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-users"></i></div>
                        <div>
                            <p class="text-xs text-[#68727F] font-bold uppercase tracking-wider">নতুন লিড</p>
                            <p class="sz-display text-xl font-bold text-white">+১৮ আজকে</p>
                        </div>
                    </div>
                    <div class="absolute left-1/4 -bottom-6 bg-[rgba(22,26,35,0.8)] backdrop-blur px-6 py-4 rounded-2xl shadow-xl border border-[rgba(255,255,255,0.09)] z-20 flex items-center gap-4 sz-float-delayed-2">
                        <div class="w-12 h-12 bg-[rgba(240,169,59,0.15)] text-[#F0A93B] rounded-xl flex items-center justify-center text-xl"><i class="fa-solid fa-plane-circle-check"></i></div>
                        <div>
                            <p class="text-xs text-[#68727F] font-bold uppercase tracking-wider">আসন্ন বুকিং</p>
                            <p class="sz-display text-xl font-bold text-white">২৪টি ফ্লাইট</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== DASHBOARD PREVIEW ========== -->
    <section class="py-24 relative" style="background:var(--sz-dark-deep);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="sz-display sz-bn text-3xl lg:text-4xl font-bold text-white mb-6">আপনার ট্রাভেল এজেন্সি চালানোর জন্য <br><span style="color:var(--sz-teal);">সব কিছু একসাথে</span></h2>
            <p class="sz-bn text-lg text-[#9AA4B2] max-w-3xl mx-auto mb-16">
                বিক্রয় ট্র্যাক করুন, লিড ম্যানেজ করুন, বুকিং পরিচালনা করুন এবং একটি শক্তিশালী প্ল্যাটফর্ম থেকে সম্পূর্ণ ট্রাভেল অপারেশন সংগঠিত করুন।
            </p>
            <div class="relative mx-auto max-w-5xl">
                <div class="rounded-t-2xl bg-[#0A0C11] border border-[rgba(255,255,255,0.06)] flex items-center px-4 py-3 gap-2">
                    <div class="w-3.5 h-3.5 rounded-full bg-[#FF2D20]"></div>
                    <div class="w-3.5 h-3.5 rounded-full bg-[#F0A93B]"></div>
                    <div class="w-3.5 h-3.5 rounded-full bg-[#3FA85F]"></div>
                    <div class="ml-4 w-64 h-6 bg-[rgba(255,255,255,0.06)] rounded-md"></div>
                </div>
                <div class="border-x border-b border-[rgba(255,255,255,0.06)] rounded-b-2xl overflow-hidden shadow-2xl" style="background:var(--sz-dark-surface);">
                    <img src="image_2ddf80.png" alt="Travel ERP Dashboard" class="w-full object-cover" onerror="this.src='https://placehold.co/1200x800/161A23/9AA4B2?text=Dashboard+Screenshot'">
                </div>
            </div>
        </div>
    </section>

    <!-- ========== FEATURES ========== -->
    <section id="features" class="py-24 relative overflow-hidden" style="background:var(--sz-dark-deep);">
        <div class="sz-blob absolute top-1/3 left-0 -ml-32 w-80 h-80 bg-[#2BC4B0]/10 rounded-full"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16">
                <h2 class="sz-display sz-bn text-3xl lg:text-4xl font-bold text-white mb-4">আধুনিক এজেন্সির জন্য শক্তিশালী ফিচার সমূহ</h2>
                <p class="sz-bn text-lg text-[#9AA4B2]">সব কিছু স্বয়ংক্রিয় করুন যাতে আপনি ব্যবসা বাড়ানোতে মনোযোগ দিতে পারেন।</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php
                $features = [
                    ['icon'=>'fa-plane-departure','title'=>'এয়ার টিকেট ও ভিসা প্রসেসিং','desc'=>'এয়ারলাইন বুকিং, যাত্রীর তথ্য ও ভিসা আবেদন এক জায়গায় পরিচালনা করুন।'],
                    ['icon'=>'fa-users-viewfinder','title'=>'লিড ও সিআরএম ম্যানেজমেন্ট','desc'=>'অনুসন্ধান ক্যাপচার করুন, গ্রাহকদের ফলো-আপ করুন এবং সহজেই লিড থেকে বিক্রয়ে রূপান্তর করুন।'],
                    ['icon'=>'fa-graduation-cap','title'=>'স্টুডেন্ট কনসালটেন্সি','desc'=>'ছাত্র ভিসা, বিশ্ববিদ্যালয় ভর্তি এবং কনসালটেন্সি কেস সম্পূর্ণভাবে পরিচালনা করুন।'],
                    ['icon'=>'fa-kaaba','title'=>'হজ্জ ও ওমরাহ','desc'=>'তীর্থযাত্রার বুকিং, গ্রুপ প্যাকেজ, যাত্রী দলিল এবং পেমেন্ট ট্র্যাকিং পরিচালনা করুন।'],
                    ['icon'=>'fa-file-invoice-dollar','title'=>'ইনভয়েস','desc'=>'তাৎক্ষণিকভাবে পেশাদার ইনভয়েস তৈরি করুন, পেমেন্ট ট্র্যাক করুন এবং বকেয়া পরিচালনা করুন।'],
                    ['icon'=>'fa-calculator','title'=>'ফাইন্যান্স ও অ্যাকাউন্টিং','desc'=>'আয়, ব্যয়, লাভ-ক্ষতি ট্র্যাক করুন এবং সম্পূর্ণ আর্থিক প্রতিবেদন তৈরি করুন।'],
                    ['icon'=>'fa-address-book','title'=>'কাস্টমার ডেটাবেজ','desc'=>'বিক্রয় সম্পন্ন হলে গ্রাহকের প্রোফাইল, ক্রয় ইতিহাস ও দলিল স্বয়ংক্রিয়ভাবে সুরক্ষিত থাকে।'],
                    ['icon'=>'fa-users-gear','title'=>'স্টাফ ম্যানেজমেন্ট','desc'=>'রোল-ভিত্তিক অনুমতি সহ স্টাফ যোগ করুন, উপস্থিতি ট্র্যাক করুন এবং বেতন পরিচালনা করুন।'],
                ];
                foreach ($features as $f): ?>
                <div class="sz-card">
                    <div class="w-14 h-14 rounded-xl flex items-center justify-center text-2xl mb-6" style="background:rgba(43,196,176,0.12);color:var(--sz-teal);">
                        <i class="fa-solid <?= $f['icon'] ?>"></i>
                    </div>
                    <h3 class="sz-bn text-lg font-bold text-white mb-3"><?= $f['title'] ?></h3>
                    <p class="sz-bn text-[#9AA4B2] leading-relaxed text-sm"><?= $f['desc'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ========== HOW IT WORKS ========== -->
    <section id="how-it-works" class="py-24 relative" style="background:var(--sz-dark-deep);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="sz-display sz-bn text-3xl lg:text-4xl font-bold text-white mb-4">কীভাবে কাজ করে</h2>
                <p class="sz-bn text-lg text-[#9AA4B2] max-w-2xl mx-auto">মিনিটের মধ্যে আপনার ট্রাভেল এজেন্সি চালু করুন — কোনো প্রযুক্তিগত সেটআপ প্রয়োজন নেই।</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 relative">
                <div class="hidden md:block absolute top-8 left-[12%] right-[12%] h-0.5" style="background:linear-gradient(to right,rgba(43,196,176,0.1),rgba(43,196,176,0.3),rgba(43,196,176,0.1));"></div>
                <?php
                $steps = [
                    ['step'=>'১','icon'=>'fa-user-plus','title'=>'অ্যাকাউন্ট তৈরি করুন','desc'=>'আপনার এজেন্সি নিবন্ধন করুন এবং ১ মাসের বিনামূল্যে ট্রায়াল পান — কার্ড লাগবে না।'],
                    ['step'=>'২','icon'=>'fa-sliders','title'=>'এজেন্সি সেটআপ করুন','desc'=>'আপনার লোগো, স্টাফ সদস্য যোগ করুন এবং প্রতিটি টিম ভূমিকার জন্য অনুমতি নির্ধারণ করুন।'],
                    ['step'=>'৩','icon'=>'fa-suitcase-rolling','title'=>'কার্যক্রম পরিচালনা করুন','desc'=>'লিড ট্র্যাক করুন, ভিসা ও টিকেট প্রসেস করুন, ইনভয়েস তৈরি করুন এবং কাস্টমার ডেটাবেজ গড়ুন।'],
                    ['step'=>'৪','icon'=>'fa-chart-line','title'=>'অন্তর্দৃষ্টি দিয়ে বিকাশ করুন','desc'=>'রিয়েল-টাইম ড্যাশবোর্ড থেকে মুনাফা, স্টাফ পারফরম্যান্স এবং ডেডলাইন মনিটর করুন।'],
                ];
                foreach ($steps as $h): ?>
                <div class="relative text-center">
                    <div class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center text-2xl relative z-10 mb-6" style="background:var(--sz-teal);color:#FFFFFF;box-shadow:var(--sz-shadow-glow) 0px 10px 30px -10px;">
                        <i class="fa-solid <?= $h['icon'] ?>"></i>
                    </div>
                    <div class="sz-badge inline-flex" style="background:rgba(43,196,176,0.15);border:1px solid rgba(43,196,176,0.3);color:var(--sz-teal);margin-bottom:8px;">ধাপ <?= $h['step'] ?></div>
                    <h3 class="sz-bn text-lg font-bold text-white mb-2"><?= $h['title'] ?></h3>
                    <p class="sz-bn text-[#9AA4B2] leading-relaxed text-sm"><?= $h['desc'] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ========== PRICING ========== -->
    <?php $landingPlans = getSubscriptionPlans($conn); ?>
    <section id="pricing" class="py-24 relative overflow-hidden" style="background:var(--sz-dark-deep);">
        <div class="sz-blob absolute top-0 right-0 -mt-20 -mr-20 w-96 h-96 bg-[#2BC4B0]/10 rounded-full"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16">
                <h2 class="sz-display sz-bn text-3xl lg:text-4xl font-bold text-white mb-4">সহজ, স্বচ্ছ মূল্য নির্ধারণ</h2>
                <p class="sz-bn text-lg text-[#9AA4B2] max-w-2xl mx-auto">বিনামূল্যে শুরু করুন, তারপর আপনার এজেন্সির জন্য উপযুক্ত প্ল্যান বেছে নিন। কোনো লুকানো ফি নেই।</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <?php
                $planDisplay = [
                    'trial'   => ['icon'=>'fa-rocket',       'highlight'=>false,'cta'=>'বিনামূল্যে ট্রায়াল শুরু করুন','href'=>'?route=register'],
                    'monthly' => ['icon'=>'fa-calendar-days','highlight'=>false,'cta'=>'শুরু করুন','href'=>'?route=register'],
                    'yearly'  => ['icon'=>'fa-crown',        'highlight'=>true, 'cta'=>'শুরু করুন','href'=>'?route=register'],
                ];
                foreach ($planDisplay as $key => $disp):
                    $p = $landingPlans[$key] ?? null;
                    if (!$p) continue;
                    $isHighlight = $disp['highlight'];
                    $planPrice   = $showUSD ? ($p['price_usd'] ?? 0) : $p['price'];
                ?>
                <div class="rounded-2xl p-8 flex flex-col transition transform hover:-translate-y-1 relative" style="<?= $isHighlight ? 'background:var(--sz-teal);color:#FFFFFF;box-shadow:0 20px 60px -20px rgba(43,196,176,0.4);border:none;' : 'background:var(--sz-dark-surface);border:1px solid var(--sz-border-subtle);color:var(--sz-text-primary);' ?>">
                    <?php if ($isHighlight): ?><span class="sz-badge absolute -top-3 right-6" style="background:#F0A93B;color:#0A0C11;border:none;">সেরা মূল্য</span><?php endif; ?>
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl mb-6" style="<?= $isHighlight ? 'background:rgba(255,255,255,0.2);color:#fff;' : 'background:rgba(43,196,176,0.12);color:var(--sz-teal);' ?>">
                        <i class="fa-solid <?= $disp['icon'] ?>"></i>
                    </div>
                    <h3 class="sz-bn text-xl font-bold mb-2"><?= xss_clean($p['name']) ?></h3>
                    <div class="mb-4">
                        <span class="sz-display text-4xl font-bold"><?= $planPrice > 0 ? $homeCurrencySymbol . number_format($planPrice, $showUSD ? 2 : 0) : 'বিনামূল্যে' ?></span>
                        <?php if ($p['price'] > 0): ?>
                        <span class="sz-bn text-sm font-medium <?= $isHighlight ? 'text-[#B8E6DF]' : 'text-[#68727F]' ?>">/ <?= $p['duration_days'] >= 300 ? 'বছর' : 'মাস' ?></span>
                        <?php else: ?>
                        <span class="sz-bn text-sm font-medium <?= $isHighlight ? 'text-[#B8E6DF]' : 'text-[#68727F]' ?>">প্রথম <?= $p['duration_days'] ?> দিনের জন্য</span>
                        <?php endif; ?>
                    </div>
                    <p class="sz-bn text-sm leading-relaxed mb-6 <?= $isHighlight ? 'text-[#B8E6DF]' : 'text-[#68727F]' ?>"><?= xss_clean($p['terms']) ?></p>
                    <ul class="space-y-3 mb-8 flex-1 text-sm">
                        <?php foreach (['সীমাহীন লিড ও বিক্রয়','অনুমতিসহ স্টাফ অ্যাকাউন্ট','কাস্টমার ডেটাবেজ ও ফলো-আপ','পিডিএফ ও এক্সেল রিপোর্ট'] as $feat): ?>
                        <li class="flex items-center gap-2 sz-bn">
                            <i class="fa-solid fa-check <?= $isHighlight ? 'text-emerald-300' : 'text-emerald-500' ?>"></i>
                            <span class="<?= $isHighlight ? 'text-white' : 'text-[#9AA4B2]' ?>"><?= $feat ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="<?= $disp['href'] ?>" class="sz-bn text-center py-3 rounded-xl font-bold transition <?= $isHighlight ? 'bg-white text-[#2BC4B0] hover:bg-slate-50' : 'bg-[rgba(43,196,176,0.12)] text-[#2BC4B0] hover:bg-[rgba(43,196,176,0.2)]' ?>">
                        <?= $disp['cta'] ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="sz-bn text-center text-sm text-[#68727F] mt-10">সব প্ল্যানে সব ফিচার অন্তর্ভুক্ত — কোনো অ্যাড-অন নেই, কোনো লুকানো চার্জ নেই। মূল্য <?= $showUSD ? 'USD' : 'BDT' ?>-তে প্রদর্শিত।</p>
        </div>
    </section>

    <!-- ========== FAQ ========== -->
    <section id="faq" class="py-24 relative overflow-hidden" style="background:var(--sz-dark-deep);">
        <div class="sz-blob absolute bottom-0 right-0 -mb-20 -mr-20 w-80 h-80 bg-[#2BC4B0]/10 rounded-full"></div>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16">
                <div class="sz-badge" style="background:rgba(43,196,176,0.15);border:1px solid rgba(43,196,176,0.3);color:var(--sz-teal);margin-bottom:16px;">
                    <i class="fa-solid fa-circle-question"></i> সাধারণ প্রশ্ন
                </div>
                <h2 class="sz-display sz-bn text-3xl lg:text-4xl font-bold text-white mb-4">সাধারণ জিজ্ঞাসা</h2>
                <p class="sz-bn text-lg text-[#9AA4B2] max-w-2xl mx-auto">ট্রাভিও ইআরপি সম্পর্কে আপনার যা জানা দরকার। কিছু খুঁজে পাচ্ছেন না? <a href="#" style="color:var(--sz-teal);text-decoration:none;font-weight:600;">যোগাযোগ করুন</a></p>
            </div>
            <div class="space-y-4">
                <?php
                $faqs = [
                    ['q'=>'ট্রাভিও ইআরপি কী?','a'=>'ট্রাভিও একটি ব্যাপক SaaS সমাধান যা বিশেষভাবে ট্রাভেল এজেন্সিদের জন্য তৈরি। এটি দিয়ে আপনি এয়ার টিকেটিং, ভিসা প্রসেসিং, ট্যুর প্যাকেজ, হোটেল বুকিং, কাস্টমার রিলেশনশিপ এবং আর্থিক বিশ্লেষণ একটি প্ল্যাটফর্ম থেকে পরিচালনা করতে পারবেন।'],
                    ['q'=>'কি বিনামূল্যে ট্রায়াল পাওয়া যায়?','a'=>'হ্যাঁ! আমরা সব ফিচারসহ ৩০ দিনের বিনামূল্যে ট্রায়াল অফার করি। কোনো ক্রেডিট কার্ড লাগবে না। আপনি তাৎক্ষণিকভাবে আপনার ট্রাভেল এজেন্সি পরিচালনা শুরু করতে পারবেন।'],
                    ['q'=>'পরে কি প্ল্যান পরিবর্তন করতে পারব?','a'=>'অবশ্যই। আপনি যেকোনো সময় আপগ্রেড, ডাউনগ্রেড বা সাবস্ক্রিপশন বাতিল করতে পারবেন। পরিবর্তন পরবর্তী বিলিং সাইকেলের শুরুতে কার্যকর হবে।'],
                    ['q'=>'আমার ডেটা কি নিরাপদ?','a'=>'হ্যাঁ, আমরা নিরাপত্তাকে সর্বোচ্চ গুরুত্ব দিই। সমস্ত ডেটা ট্রানজিট এবং রেস্টে এনক্রিপ্টেড থাকে। আপনার ব্যবসায়িক ডেটা সবসময় নিরাপদ রাখতে আমরা শিল্পমানের নিরাপত্তা অনুশীলন ও নিয়মিত ব্যাকআপ ব্যবহার করি।'],
                    ['q'=>'কাস্টমার সাপোর্ট পাওয়া যাবে?','a'=>'হ্যাঁ, সব প্ল্যানে ইমেইল সাপোর্ট অন্তর্ভুক্ত। আমরা ডকুমেন্টেশন, ভিডিও টিউটোরিয়াল এবং নলেজ বেসও সরবরাহ করি যাতে আপনি ট্রাভিও থেকে সর্বোচ্চ সুবিধা পেতে পারেন।'],
                    ['q'=>'একাধিক স্টাফ সদস্য যোগ করতে পারব?','a'=>'হ্যাঁ, সব পেইড প্ল্যানে গ্রানুলার পারমিশন কন্ট্রোলসহ একাধিক স্টাফ অ্যাকাউন্ট অন্তর্ভুক্ত। আপনি প্রয়োজনমতো যতজন টিম সদস্য যোগ করতে এবং নির্দিষ্ট ভূমিকা ও অনুমতি নির্ধারণ করতে পারবেন।'],
                ];
                foreach ($faqs as $faq): ?>
                <div class="sz-faq-item">
                    <button class="sz-faq-question" onclick="toggleFAQ(this)">
                        <span><?= $faq['q'] ?></span>
                        <span class="sz-faq-icon"><i class="fa-solid fa-chevron-down"></i></span>
                    </button>
                    <div class="sz-faq-answer"><?= $faq['a'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ========== CTA ========== -->
    <section class="py-24 relative overflow-hidden" style="background:var(--sz-dark-deep);">
        <div class="sz-blob absolute top-0 left-1/2 -translate-x-1/2 -mt-20 w-96 h-96 bg-[#2BC4B0]/15 rounded-full"></div>
        <div class="sz-blob absolute bottom-0 right-0 -mb-20 -mr-20 w-80 h-80 bg-[#61DAFB]/10 rounded-full"></div>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <div class="sz-badge" style="background:rgba(43,196,176,0.15);border:1px solid rgba(43,196,176,0.3);color:var(--sz-teal);margin-bottom:24px;">
                <span class="sz-pulse-dot"></span>
                আজই শুরু করুন
            </div>
            <h2 class="sz-display sz-bn text-4xl lg:text-5xl font-bold text-white leading-tight mb-6">
                ট্রাভিও দিয়ে আপনার ট্রাভেল<br>
                <span style="background:linear-gradient(135deg,#2BC4B0,#61DAFB);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">ব্যবসা গড়ে তুলুন</span>
            </h2>
            <p class="sz-bn text-lg text-[#9AA4B2] max-w-2xl mx-auto mb-10 leading-relaxed">
                ইতিমধ্যে শত শত ট্রাভেল এজেন্সি ট্রাভিও ব্যবহার করে তাদের কার্যক্রম সুবিন্যস্ত করছে, বিক্রয় বাড়াচ্ছে এবং ব্যবসা বৃদ্ধি করছে। আজই বিনামূল্যে ট্রায়াল শুরু করুন।
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="?route=register" class="sz-glow-btn-filled sz-bn text-lg px-10 py-4" style="height:auto;">
                    বিনামূল্যে ট্রায়াল শুরু করুন <i class="fa-solid fa-arrow-right ml-2"></i>
                </a>
                <a href="#" class="sz-btn-outline-light sz-bn text-lg px-10 py-4" style="height:auto;">
                    <i class="fa-solid fa-circle-play"></i> ডেমো দেখুন
                </a>
            </div>
            <div class="mt-12 flex flex-wrap items-center justify-center gap-8 text-sm text-[#68727F]">
                <span class="sz-bn flex items-center gap-2"><i class="fa-solid fa-check-circle" style="color:var(--sz-teal);"></i> ৩০ দিনের বিনামূল্যে ট্রায়াল</span>
                <span class="sz-bn flex items-center gap-2"><i class="fa-solid fa-check-circle" style="color:var(--sz-teal);"></i> কার্ড প্রয়োজন নেই</span>
                <span class="sz-bn flex items-center gap-2"><i class="fa-solid fa-check-circle" style="color:var(--sz-teal);"></i> যেকোনো সময় বাতিল করুন</span>
                <span class="sz-bn flex items-center gap-2"><i class="fa-solid fa-check-circle" style="color:var(--sz-teal);"></i> ২৪/৭ সাপোর্ট</span>
            </div>
        </div>
    </section>

    <!-- ========== FOOTER ========== -->
    <footer style="background:#0A0C11;border-top:1px solid rgba(255,255,255,0.06);" class="pt-16 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12 mb-8 border-b border-[rgba(255,255,255,0.06)]">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-10">
                <!-- Logo & About -->
                <div class="col-span-2 md:col-span-1">
                    <div class="flex items-center gap-2 mb-5">
                        <div class="w-8 h-8 bg-gradient-to-br from-[#2BC4B0] to-[#61DAFB] rounded-lg flex items-center justify-center text-white">
                            <i class="fa-solid fa-plane-departure text-sm"></i>
                        </div>
                        <h1 class="sz-display text-xl font-bold text-white tracking-tight">Travio</h1>
                    </div>
                    <p class="sz-bn text-[#68727F] text-sm leading-relaxed mb-6">প্রিমিয়াম ট্রাভিও ইআরপি বিশেষভাবে ট্রাভেল এজেন্সিদের কার্যক্রম স্বয়ংক্রিয় করতে ও স্কেল করতে ডিজাইন করা হয়েছে।</p>
                    <div class="flex items-center gap-4">
                        <a href="https://facebook.com" target="_blank" rel="noopener" class="w-9 h-9 rounded-xl flex items-center justify-center transition" style="background:rgba(255,255,255,0.06);color:#9AA4B2;" onmouseover="this.style.background='rgba(24,119,242,0.2)';this.style.color='#4F9EF7'" onmouseout="this.style.background='rgba(255,255,255,0.06)';this.style.color='#9AA4B2'"><i class="fa-brands fa-facebook-f text-sm"></i></a>
                        <a href="https://youtube.com" target="_blank" rel="noopener" class="w-9 h-9 rounded-xl flex items-center justify-center transition" style="background:rgba(255,255,255,0.06);color:#9AA4B2;" onmouseover="this.style.background='rgba(255,0,0,0.2)';this.style.color='#FF4444'" onmouseout="this.style.background='rgba(255,255,255,0.06)';this.style.color='#9AA4B2'"><i class="fa-brands fa-youtube text-sm"></i></a>
                        <a href="https://instagram.com" target="_blank" rel="noopener" class="w-9 h-9 rounded-xl flex items-center justify-center transition" style="background:rgba(255,255,255,0.06);color:#9AA4B2;" onmouseover="this.style.background='rgba(225,48,108,0.2)';this.style.color='#E1306C'" onmouseout="this.style.background='rgba(255,255,255,0.06)';this.style.color='#9AA4B2'"><i class="fa-brands fa-instagram text-sm"></i></a>
                    </div>
                </div>
                <!-- Company -->
                <div>
                    <h4 class="sz-bn text-white font-bold mb-5 uppercase tracking-wider text-xs">কোম্পানি</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="/travio-bangla" class="sz-bn text-[#9AA4B2] hover:text-[#2BC4B0] transition">হোম</a></li>
                        <li><a href="#features" class="sz-bn text-[#9AA4B2] hover:text-[#2BC4B0] transition">ফিচার</a></li>
                        <li><a href="#pricing" class="sz-bn text-[#9AA4B2] hover:text-[#2BC4B0] transition">মূল্য তালিকা</a></li>
                        <li><a href="#faq" class="sz-bn text-[#9AA4B2] hover:text-[#2BC4B0] transition">সাধারণ প্রশ্ন</a></li>
                    </ul>
                </div>
                <!-- Portal -->
                <div>
                    <h4 class="sz-bn text-white font-bold mb-5 uppercase tracking-wider text-xs">পোর্টাল</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="?route=login" class="sz-bn text-[#9AA4B2] hover:text-[#2BC4B0] transition">এজেন্সি লগইন</a></li>
                        <li><a href="?route=register" class="sz-bn text-[#9AA4B2] hover:text-[#2BC4B0] transition">এজেন্সি নিবন্ধন</a></li>
                        <li><a href="#" class="sz-bn text-[#9AA4B2] hover:text-[#2BC4B0] transition">ডকুমেন্টেশন</a></li>
                        <li><a href="/travio-bangla" class="sz-bn text-[#2BC4B0] hover:text-[#1FB8A4] transition font-semibold">বাংলা পোর্টাল</a></li>
                    </ul>
                </div>
                <!-- Contact -->
                <div>
                    <h4 class="sz-bn text-white font-bold mb-5 uppercase tracking-wider text-xs">যোগাযোগ</h4>
                    <ul class="space-y-4 text-sm">
                        <li>
                            <a href="https://wa.me/8801XXXXXXXXX" target="_blank" rel="noopener" class="flex items-start gap-3 text-[#9AA4B2] hover:text-[#25D366] transition">
                                <i class="fa-brands fa-whatsapp text-base mt-0.5 text-[#25D366]"></i>
                                <span class="sz-bn">+880 1XXX-XXXXXX</span>
                            </a>
                        </li>
                        <li>
                            <a href="mailto:support@travioerp.com" class="flex items-start gap-3 text-[#9AA4B2] hover:text-[#2BC4B0] transition">
                                <i class="fa-solid fa-envelope text-base mt-0.5" style="color:var(--sz-teal);"></i>
                                <span>support@travioerp.com</span>
                            </a>
                        </li>
                        <li>
                            <a href="https://facebook.com" target="_blank" rel="noopener" class="flex items-start gap-3 text-[#9AA4B2] hover:text-[#4F9EF7] transition">
                                <i class="fa-brands fa-facebook-f text-base mt-0.5 text-[#4F9EF7]"></i>
                                <span class="sz-bn">Travel Agency Solution - Travio</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Payment Icons -->
        <?php include __DIR__ . '/../includes/payment_icons.php'; ?>

        <!-- Bottom bar -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-[#68727F]">
            <span class="sz-bn">&copy; <?= date('Y') ?> Travio ERP. সর্বস্বত্ব সংরক্ষিত।</span>
            <div class="flex items-center gap-5">
                <a href="#" class="sz-bn hover:text-[#2BC4B0] transition">গোপনীয়তা নীতি</a>
                <a href="#" class="sz-bn hover:text-[#2BC4B0] transition">সেবার শর্তাবলী</a>
            </div>
        </div>
    </footer>
    </div>

    <script>
    function toggleFAQ(button) {
        const answer = button.nextElementSibling;
        const isOpen = answer.classList.contains('open');
        document.querySelectorAll('#sz-home .sz-faq-answer').forEach(el => {
            el.classList.remove('open');
            el.previousElementSibling.classList.remove('active');
        });
        if (!isOpen) { answer.classList.add('open'); button.classList.add('active'); }
    }
    document.addEventListener('DOMContentLoaded', function() {
        const first = document.querySelector('#sz-home .sz-faq-question');
        if (first) first.click();
    });
    </script>
<?php }
