<?php
function renderLandingPage($conn) {
    // Homepage Pricing: Bangladesh visitors see BDT, everyone else sees USD. Defaults to BDT when the
    // visitor's country can't be determined (lookup unreachable, local/private IP, etc).
    $visitorCountry = getVisitorCountryName();
    $showUSD = ($visitorCountry !== null && strcasecmp($visitorCountry, 'Bangladesh') !== 0);
    $homeCurrencySymbol = $showUSD ? '$' : '৳';
?>
    <!-- ============================================================= -->
    <!-- Homepage-only design layer. Scoped to #sz-home so nothing here -->
    <!-- touches the shared dashboard/admin styles in router.php.       -->
    <!-- ============================================================= -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap');

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

        #sz-home * {
            box-sizing: border-box;
        }

        #sz-home .sz-display {
            font-family: 'Space Grotesk', 'Plus Jakarta Sans', sans-serif;
            letter-spacing: -0.01em;
        }

        #sz-home .sz-body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        #sz-home .sz-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 4px 12px;
            border-radius: var(--sz-radius-badge);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-family: 'Inter', sans-serif;
            height: 24px;
        }

        #sz-home .sz-card {
            background: var(--sz-dark-surface);
            border: 1px solid var(--sz-border-subtle);
            border-radius: var(--sz-radius-card);
            padding: 22px;
            transition: var(--sz-transition);
            color: var(--sz-text-primary);
        }

        #sz-home .sz-card:hover {
            border-color: rgba(43, 196, 176, 0.3);
            background: rgba(22, 26, 35, 0.75);
        }

        #sz-home .sz-glow-btn {
            background: rgba(43, 196, 176, 0);
            border: 1px solid rgba(0, 0, 0, 0);
            padding: 11px 20px;
            border-radius: var(--sz-radius-btn);
            font-family: 'Inter', sans-serif;
            font-size: 14.5px;
            font-weight: 600;
            color: #FFFFFF;
            box-shadow: var(--sz-shadow-glow) 0px 10px 30px -10px;
            transition: var(--sz-transition);
            height: 47px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            cursor: pointer;
            position: relative;
        }

        #sz-home .sz-glow-btn:hover {
            box-shadow: var(--sz-shadow-glow-alt) 0px 15px 40px -8px;
            transform: translateY(-2px);
            color: #FFFFFF;
        }

        #sz-home .sz-glow-btn:active {
            box-shadow: var(--sz-shadow-glow) 0px 5px 15px -10px;
            transform: translateY(0px);
        }

        #sz-home .sz-glow-btn-filled {
            background: var(--sz-teal);
            color: #FFFFFF;
            padding: 11px 20px;
            border: none;
            border-radius: var(--sz-radius-btn);
            font-family: 'Inter', sans-serif;
            font-size: 14.5px;
            font-weight: 600;
            box-shadow: var(--sz-shadow-glow) 0px 10px 30px -10px;
            transition: var(--sz-transition);
            height: 47px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            cursor: pointer;
        }

        #sz-home .sz-glow-btn-filled:hover {
            background: #1FB8A4;
            box-shadow: var(--sz-shadow-glow-alt) 0px 15px 40px -8px;
            transform: translateY(-2px);
            color: #FFFFFF;
        }

        #sz-home .sz-ghost-btn {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--sz-border-subtle);
            padding: 8px 15px;
            border-radius: var(--sz-radius-btn);
            font-family: 'Inter', sans-serif;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--sz-text-primary);
            transition: var(--sz-transition);
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            cursor: pointer;
            gap: 8px;
        }

        #sz-home .sz-ghost-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.15);
            color: var(--sz-text-primary);
        }

        #sz-home .sz-btn-white {
            background: #FFFFFF;
            color: var(--sz-teal);
            padding: 11px 20px;
            border: none;
            border-radius: var(--sz-radius-btn);
            font-family: 'Inter', sans-serif;
            font-size: 14.5px;
            font-weight: 600;
            box-shadow: 0px 8px 24px rgba(0, 0, 0, 0.2);
            transition: var(--sz-transition);
            height: 47px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            cursor: pointer;
        }

        #sz-home .sz-btn-white:hover {
            background: #f5f5f5;
            transform: translateY(-2px);
            box-shadow: 0px 12px 32px rgba(0, 0, 0, 0.3);
        }

        #sz-home .sz-btn-outline-light {
            background: transparent;
            color: #FFFFFF;
            padding: 11px 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--sz-radius-btn);
            font-family: 'Inter', sans-serif;
            font-size: 14.5px;
            font-weight: 600;
            transition: var(--sz-transition);
            height: 47px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            cursor: pointer;
            gap: 8px;
        }

        #sz-home .sz-btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.4);
            transform: translateY(-2px);
        }

        #sz-home .sz-blob {
            filter: blur(70px);
            pointer-events: none;
        }

        #sz-home .sz-float {
            animation: sz-float 5s ease-in-out infinite;
        }

        #sz-home .sz-float-delayed {
            animation: sz-float 5s ease-in-out infinite;
            animation-delay: 1.5s;
        }

        #sz-home .sz-float-delayed-2 {
            animation: sz-float 5s ease-in-out infinite;
            animation-delay: 3s;
        }

        @keyframes sz-float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-14px); }
            100% { transform: translateY(0px); }
        }

        @keyframes sz-pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(43, 196, 176, 0.35); }
            100% { box-shadow: 0 0 0 8px rgba(43, 196, 176, 0); }
        }

        #sz-home .sz-pulse-dot {
            animation: sz-pulse-ring 1.8s ease-out infinite;
        }

        #sz-home .sz-bg-grid {
            background-image: 
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        /* FAQ Accordion Styles */
        #sz-home .sz-faq-item {
            border: 1px solid var(--sz-border-subtle);
            border-radius: var(--sz-radius-card);
            background: var(--sz-dark-surface);
            transition: var(--sz-transition);
            overflow: hidden;
        }

        #sz-home .sz-faq-item:hover {
            border-color: rgba(43, 196, 176, 0.3);
        }

        #sz-home .sz-faq-question {
            width: 100%;
            padding: 20px 24px;
            background: transparent;
            border: none;
            color: var(--sz-text-primary);
            font-family: 'Inter', sans-serif;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-align: left;
            transition: var(--sz-transition);
        }

        #sz-home .sz-faq-question:hover {
            color: var(--sz-teal);
        }

        #sz-home .sz-faq-question .sz-faq-icon {
            transition: transform 0.3s ease;
            color: var(--sz-teal);
            font-size: 18px;
            flex-shrink: 0;
            margin-left: 16px;
        }

        #sz-home .sz-faq-question.active .sz-faq-icon {
            transform: rotate(180deg);
        }

        #sz-home .sz-faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.4s ease;
            padding: 0 24px;
            color: var(--sz-text-secondary);
            font-size: 15px;
            line-height: 1.7;
        }

        #sz-home .sz-faq-answer.open {
            max-height: 300px;
            padding: 0 24px 20px 24px;
        }

        /* Responsive adjustments */
        @media (max-width: 1023px) {
            #sz-home .sz-hero-title {
                font-size: 2.75rem !important;
            }
            #sz-home .sz-card {
                padding: 18px;
            }
        }

        @media (max-width: 767px) {
            #sz-home .sz-hero-title {
                font-size: 2rem !important;
            }
            #sz-home .sz-hero-sub {
                font-size: 1rem !important;
            }
            #sz-home .sz-card {
                padding: 16px;
            }
            #sz-home .sz-glow-btn,
            #sz-home .sz-glow-btn-filled,
            #sz-home .sz-btn-white,
            #sz-home .sz-btn-outline-light {
                height: 42px;
                font-size: 13px;
                padding: 9px 16px;
            }
            #sz-home .sz-ghost-btn {
                height: 36px;
                font-size: 12.5px;
                padding: 6px 12px;
            }
            #sz-home .sz-faq-question {
                padding: 16px 18px;
                font-size: 14px;
            }
            #sz-home .sz-faq-answer {
                font-size: 14px;
            }
            #sz-home .sz-faq-answer.open {
                padding: 0 18px 16px 18px;
            }
        }

        @media (max-width: 479px) {
            #sz-home .sz-hero-title {
                font-size: 1.6rem !important;
            }
        }
    </style>

    <div id="sz-home" class="sz-body" style="background: var(--sz-dark-deep); color: var(--sz-text-primary); min-height: 100vh;">

    <!-- ============================================================ -->
    <!-- NAVIGATION                                                    -->
    <!-- ============================================================ -->
    <nav class="bg-[#0A0C11]/80 backdrop-blur-md border-b border-[rgba(255,255,255,0.06)] py-4 fixed w-full z-50 transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <div class="w-10 h-10 bg-gradient-to-br from-[#2BC4B0] to-[#61DAFB] rounded-xl flex items-center justify-center text-white shadow-lg shadow-[#2BC4B0]/20">
                    <i class="fa-solid fa-plane-departure text-xl"></i>
                </div>
                <h1 class="sz-display text-2xl font-bold text-white tracking-tight">Travio</h1>
            </div>
            <div class="hidden md:flex items-center space-x-8">
                <a href="#features" class="text-sm font-medium text-[#9AA4B2] hover:text-[#2BC4B0] transition">Features</a>
                <a href="#how-it-works" class="text-sm font-medium text-[#9AA4B2] hover:text-[#2BC4B0] transition">How It Works</a>
                <a href="#pricing" class="text-sm font-medium text-[#9AA4B2] hover:text-[#2BC4B0] transition">Pricing</a>
                <a href="#faq" class="text-sm font-medium text-[#9AA4B2] hover:text-[#2BC4B0] transition">FAQ</a>
            </div>
            <div class="flex items-center space-x-4">
                <a href="/login" class="text-sm font-medium text-[#9AA4B2] hover:text-[#2BC4B0] transition hidden sm:block">Login</a>
                <a href="/register" class="sz-glow-btn-filled">Start Free Trial</a>
            </div>
        </div>
    </nav>

    <!-- ============================================================ -->
    <!-- HERO SECTION                                                  -->
    <!-- ============================================================ -->
    <section class="pt-32 pb-20 lg:pt-44 lg:pb-28 relative overflow-hidden" style="background: var(--sz-dark-deep);">
        <div class="absolute inset-0 sz-bg-grid opacity-30"></div>
        <div class="sz-blob absolute top-0 right-0 -mt-20 -mr-20 w-96 h-96 bg-[#2BC4B0]/20 rounded-full"></div>
        <div class="sz-blob absolute bottom-0 left-0 -mb-20 -ml-20 w-72 h-72 bg-[#61DAFB]/15 rounded-full"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="text-center lg:text-left">
                    <div class="sz-badge" style="background: rgba(43,196,176,0.15); border: 1px solid rgba(43,196,176,0.3); color: var(--sz-teal); margin-bottom: 24px;">
                        <span class="w-2 h-2 rounded-full" style="background: var(--sz-teal);"></span>
                        <span class="sz-pulse-dot" style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--sz-teal);"></span>
                        Cloud ERP SaaS v2.0
                    </div>
                    <h1 class="sz-display sz-hero-title text-5xl lg:text-6xl font-bold text-white leading-tight mb-6 tracking-tight">
                        Manage Your Travel <br>Business <span style="background: linear-gradient(135deg, #2BC4B0, #61DAFB); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">Smarter</span>
                    </h1>
                    <p class="sz-hero-sub text-lg text-[#9AA4B2] mb-8 max-w-xl mx-auto lg:mx-0 leading-relaxed">
                        Complete Travel Agency ERP Solution for Air Ticketing, Visa, Tour Packages, Hotel Booking & Customer Management.
                    </p>
                    <div class="flex flex-col sm:flex-row items-center gap-4 justify-center lg:justify-start mb-8">
                        <a href="/register" class="sz-glow-btn-filled w-full sm:w-auto px-8 py-4 text-lg">Start Free Trial</a>
                        <a href="#" class="sz-ghost-btn w-full sm:w-auto px-8 py-4 text-lg rounded-xl flex items-center justify-center gap-2">
                            <i class="fa-solid fa-circle-play"></i> Watch Demo
                        </a>
                    </div>
                    <p class="text-sm font-medium text-[#68727F] flex items-center justify-center lg:justify-start gap-2">
                        <i class="fa-solid fa-shield-check" style="color: #3FA85F;"></i> Built for Travel Agencies • Secure • Easy to Use • Cloud Based
                    </p>
                </div>

                <div class="relative hidden lg:block">
                    <div class="relative rounded-2xl overflow-hidden shadow-2xl border border-[rgba(255,255,255,0.09)] bg-[#0A0C11] z-10 sz-float">
                        <img src="https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=800&q=80" alt="Travel Agent Dashboard" class="w-full h-[450px] object-cover opacity-70">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#03181F]/80 to-transparent"></div>
                    </div>

                    <!-- Floating Stat Cards -->
                    <div class="absolute -left-12 top-20 bg-[rgba(22,26,35,0.8)] backdrop-blur px-6 py-4 rounded-2xl shadow-xl border border-[rgba(255,255,255,0.09)] z-20 flex items-center gap-4 sz-float-delayed">
                        <div class="w-12 h-12 bg-[rgba(63,168,95,0.15)] text-[#3FA85F] rounded-xl flex items-center justify-center text-xl">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                        <div>
                            <p class="text-xs text-[#68727F] font-bold uppercase tracking-wider">Today's Sales</p>
                            <p class="sz-display text-xl font-bold text-white"><?= $showUSD ? '$1,584' : '৳ 174,200' ?></p>
                        </div>
                    </div>

                    <div class="absolute -right-8 bottom-32 bg-[rgba(22,26,35,0.8)] backdrop-blur px-6 py-4 rounded-2xl shadow-xl border border-[rgba(255,255,255,0.09)] z-20 flex items-center gap-4 sz-float">
                        <div class="w-12 h-12 bg-[rgba(43,196,176,0.15)] text-[#2BC4B0] rounded-xl flex items-center justify-center text-xl">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div>
                            <p class="text-xs text-[#68727F] font-bold uppercase tracking-wider">New Leads</p>
                            <p class="sz-display text-xl font-bold text-white">+18 Today</p>
                        </div>
                    </div>

                    <div class="absolute left-1/4 -bottom-6 bg-[rgba(22,26,35,0.8)] backdrop-blur px-6 py-4 rounded-2xl shadow-xl border border-[rgba(255,255,255,0.09)] z-20 flex items-center gap-4 sz-float-delayed-2">
                        <div class="w-12 h-12 bg-[rgba(240,169,59,0.15)] text-[#F0A93B] rounded-xl flex items-center justify-center text-xl">
                            <i class="fa-solid fa-plane-circle-check"></i>
                        </div>
                        <div>
                            <p class="text-xs text-[#68727F] font-bold uppercase tracking-wider">Upcoming Bookings</p>
                            <p class="sz-display text-xl font-bold text-white">24 Flights</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- DASHBOARD PREVIEW SECTION                                     -->
    <!-- ============================================================ -->
    <section class="py-24 relative" style="background: var(--sz-dark-deep);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="sz-display text-3xl lg:text-4xl font-bold text-white mb-6">Everything You Need To Run <br><span style="color: var(--sz-teal);">Your Travel Agency</span></h2>
            <p class="text-lg text-[#9AA4B2] max-w-3xl mx-auto mb-16">
                Track sales, manage leads, handle bookings, monitor profit and organize your complete travel operation from one powerful platform.
            </p>

            <div class="relative mx-auto max-w-5xl">
                <div class="rounded-t-2xl bg-[#0A0C11] border border-[rgba(255,255,255,0.06)] flex items-center px-4 py-3 gap-2">
                    <div class="w-3.5 h-3.5 rounded-full bg-[#FF2D20]"></div>
                    <div class="w-3.5 h-3.5 rounded-full bg-[#F0A93B]"></div>
                    <div class="w-3.5 h-3.5 rounded-full bg-[#3FA85F]"></div>
                    <div class="ml-4 w-64 h-6 bg-[rgba(255,255,255,0.06)] rounded-md"></div>
                </div>
                <div class="border-x border-b border-[rgba(255,255,255,0.06)] rounded-b-2xl overflow-hidden shadow-2xl" style="background: var(--sz-dark-surface);">
                    <img src="image_2ddf80.png" alt="Travel ERP Dashboard" class="w-full object-cover" onerror="this.src='https://placehold.co/1200x800/161A23/9AA4B2?text=Dashboard+Screenshot'">
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- FEATURES SECTION                                              -->
    <!-- ============================================================ -->
    <section id="features" class="py-24 relative overflow-hidden" style="background: var(--sz-dark-deep);">
        <div class="sz-blob absolute top-1/3 left-0 -ml-32 w-80 h-80 bg-[#2BC4B0]/10 rounded-full"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16">
                <h2 class="sz-display text-3xl lg:text-4xl font-bold text-white mb-4">Powerful Features for Modern Agencies</h2>
                <p class="text-lg text-[#9AA4B2]">Everything automated so you can focus on growing your business.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php
                $featuresList = [
                    ['icon' => 'fa-plane-departure', 'title' => 'Air Ticket & Visa Processing', 'desc' => 'Manage airline bookings, track passenger details, visa applications, and embassy appointments in one place.'],
                    ['icon' => 'fa-users-viewfinder', 'title' => 'Lead & CRM Management', 'desc' => 'Capture enquiries, follow-up customers, and convert leads into confirmed sales effortlessly.'],
                    ['icon' => 'fa-graduation-cap', 'title' => 'Student Consultancy', 'desc' => 'Handle student visa applications, university admissions, and consultancy case tracking end-to-end.'],
                    ['icon' => 'fa-kaaba', 'title' => 'Hajj & Umrah', 'desc' => 'Manage pilgrimage bookings, group packages, passenger documents, and payment tracking.'],
                    ['icon' => 'fa-file-invoice-dollar', 'title' => 'Invoice', 'desc' => 'Generate professional invoices instantly, track payments, and manage outstanding dues easily.'],
                    ['icon' => 'fa-calculator', 'title' => 'Finance & Accounting', 'desc' => 'Track income, expenses, profit/loss, and generate financial reports for your agency.'],
                    ['icon' => 'fa-address-book', 'title' => 'Customer Database', 'desc' => 'Auto-save customer profiles, purchase history, and documents securely when sales are completed.'],
                    ['icon' => 'fa-users-gear', 'title' => 'Staff Management', 'desc' => 'Add staff with role-based permissions, track attendance, manage salaries, and monitor performance.'],
                ];
                foreach ($featuresList as $f): ?>
                    <div class="sz-card">
                        <div class="w-14 h-14 rounded-xl flex items-center justify-center text-2xl mb-6" style="background: rgba(43,196,176,0.12); color: var(--sz-teal);">
                            <i class="fa-solid <?= $f['icon'] ?>"></i>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-3"><?= $f['title'] ?></h3>
                        <p class="text-[#9AA4B2] leading-relaxed text-sm"><?= $f['desc'] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- HOW IT WORKS SECTION                                          -->
    <!-- ============================================================ -->
    <section id="how-it-works" class="py-24 relative" style="background: var(--sz-dark-deep);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="sz-display text-3xl lg:text-4xl font-bold text-white mb-4">How It Works</h2>
                <p class="text-lg text-[#9AA4B2] max-w-2xl mx-auto">Get your travel agency up and running in minutes - no technical setup required.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 relative">
                <div class="hidden md:block absolute top-8 left-[12%] right-[12%] h-0.5" style="background: linear-gradient(to right, rgba(43,196,176,0.1), rgba(43,196,176,0.3), rgba(43,196,176,0.1));"></div>
                <?php
                $howItWorks = [
                    ['step' => '1', 'icon' => 'fa-user-plus', 'title' => 'Create Your Account', 'desc' => 'Register your agency and get instant access to a 1-month free trial - no card required.'],
                    ['step' => '2', 'icon' => 'fa-sliders', 'title' => 'Set Up Your Agency', 'desc' => 'Add your logo, staff members, and set granular permissions for each team role.'],
                    ['step' => '3', 'icon' => 'fa-suitcase-rolling', 'title' => 'Manage Daily Operations', 'desc' => 'Track leads, process visas & tickets, generate invoices, and build your customer database.'],
                    ['step' => '4', 'icon' => 'fa-chart-line', 'title' => 'Grow With Insights', 'desc' => 'Monitor profit, staff performance, and deadlines from one real-time dashboard.'],
                ];
                foreach ($howItWorks as $h): ?>
                    <div class="relative text-center">
                        <div class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center text-2xl relative z-10 mb-6" style="background: var(--sz-teal); color: #FFFFFF; box-shadow: var(--sz-shadow-glow) 0px 10px 30px -10px;">
                            <i class="fa-solid <?= $h['icon'] ?>"></i>
                        </div>
                        <div class="sz-badge inline-flex" style="background: rgba(43,196,176,0.15); border: 1px solid rgba(43,196,176,0.3); color: var(--sz-teal); margin-bottom: 8px;">Step <?= $h['step'] ?></div>
                        <h3 class="text-lg font-bold text-white mb-2"><?= $h['title'] ?></h3>
                        <p class="text-[#9AA4B2] leading-relaxed text-sm"><?= $h['desc'] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- PRICING SECTION                                               -->
    <!-- ============================================================ -->
    <?php $landingPlans = getSubscriptionPlans($conn); ?>
    <section id="pricing" class="py-24 relative overflow-hidden" style="background: var(--sz-dark-deep);">
        <div class="sz-blob absolute top-0 right-0 -mt-20 -mr-20 w-96 h-96 bg-[#2BC4B0]/10 rounded-full"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16">
                <h2 class="sz-display text-3xl lg:text-4xl font-bold text-white mb-4">Simple, Transparent Pricing</h2>
                <p class="text-lg text-[#9AA4B2] max-w-2xl mx-auto">Start free, then pick the plan that fits your agency. No hidden fees.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <?php
                $planDisplay = [
                    'trial' => ['icon' => 'fa-rocket', 'highlight' => false, 'cta' => 'Start Free Trial', 'href' => '/register'],
                    'monthly' => ['icon' => 'fa-calendar-days', 'highlight' => false, 'cta' => 'Get Started', 'href' => '/register'],
                    'yearly' => ['icon' => 'fa-crown', 'highlight' => true, 'cta' => 'Get Started', 'href' => '/register'],
                ];
                foreach ($planDisplay as $key => $disp):
                    $p = $landingPlans[$key] ?? null;
                    if (!$p) continue;
                    $isHighlight = $disp['highlight'];
                ?>
                    <div class="rounded-2xl p-8 flex flex-col transition transform hover:-translate-y-1 relative" style="<?= $isHighlight ? 'background: var(--sz-teal); color: #FFFFFF; box-shadow: 0 20px 60px -20px rgba(43,196,176,0.4); border: none;' : 'background: var(--sz-dark-surface); border: 1px solid var(--sz-border-subtle); color: var(--sz-text-primary);' ?>">
                        <?php if ($isHighlight): ?>
                            <span class="sz-badge absolute -top-3 right-6" style="background: #F0A93B; color: #0A0C11; border: none;">Best Value</span>
                        <?php endif; ?>
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl mb-6 <?= $isHighlight ? 'bg-white/20 text-white' : '' ?>" style="<?= !$isHighlight ? 'background: rgba(43,196,176,0.12); color: var(--sz-teal);' : '' ?>">
                            <i class="fa-solid <?= $disp['icon'] ?>"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-2"><?= xss_clean($p['name']) ?></h3>
                        <div class="mb-4">
                            <?php $planPrice = $showUSD ? ($p['price_usd'] ?? 0) : $p['price']; ?>
                            <span class="sz-display text-4xl font-bold"><?= $planPrice > 0 ? $homeCurrencySymbol . number_format($planPrice, $showUSD ? 2 : 0) : 'Free' ?></span>
                            <?php if ($p['price'] > 0): ?>
                                <span class="text-sm font-medium <?= $isHighlight ? 'text-[#B8E6DF]' : 'text-[#68727F]' ?>">/ <?= $p['duration_days'] >= 300 ? 'year' : 'month' ?></span>
                            <?php else: ?>
                                <span class="text-sm font-medium <?= $isHighlight ? 'text-[#B8E6DF]' : 'text-[#68727F]' ?>">for <?= $p['duration_days'] ?> days</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm leading-relaxed mb-6 <?= $isHighlight ? 'text-[#B8E6DF]' : 'text-[#68727F]' ?>"><?= xss_clean($p['terms']) ?></p>
                        <ul class="space-y-3 mb-8 flex-1 text-sm">
                            <?php foreach (['Unlimited leads & sales', 'Staff accounts with permissions', 'Customer database & follow-ups', 'PDF & Excel reports'] as $feat): ?>
                                <li class="flex items-center gap-2">
                                    <i class="fa-solid fa-check <?= $isHighlight ? 'text-emerald-300' : 'text-emerald-500' ?>"></i>
                                    <span class="<?= $isHighlight ? 'text-white' : 'text-[#9AA4B2]' ?>"><?= $feat ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="<?= $disp['href'] ?>" class="text-center py-3 rounded-xl font-bold transition <?= $isHighlight ? 'bg-white text-[#2BC4B0] hover:bg-slate-50' : 'bg-[rgba(43,196,176,0.12)] text-[#2BC4B0] hover:bg-[rgba(43,196,176,0.2)]' ?>">
                            <?= $disp['cta'] ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-center text-sm text-[#68727F] mt-10">All plans include every feature - no add-ons, no surprise charges. Prices shown in <?= $showUSD ? 'USD' : 'BDT' ?>.</p>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- FAQ SECTION - New                                             -->
    <!-- ============================================================ -->
    <section id="faq" class="py-24 relative overflow-hidden" style="background: var(--sz-dark-deep);">
        <div class="sz-blob absolute bottom-0 right-0 -mb-20 -mr-20 w-80 h-80 bg-[#2BC4B0]/10 rounded-full"></div>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-16">
                <div class="sz-badge" style="background: rgba(43,196,176,0.15); border: 1px solid rgba(43,196,176,0.3); color: var(--sz-teal); margin-bottom: 16px;">
                    <i class="fa-solid fa-circle-question"></i> FAQ
                </div>
                <h2 class="sz-display text-3xl lg:text-4xl font-bold text-white mb-4">Frequently Asked Questions</h2>
                <p class="text-lg text-[#9AA4B2] max-w-2xl mx-auto">Everything you need to know about Travio ERP. Can't find what you're looking for? <a href="#" style="color: var(--sz-teal); text-decoration: none; font-weight: 600;">Contact us</a></p>
            </div>

            <div class="space-y-4">
                <?php
                $faqs = [
                    [
                        'q' => 'What is Travio ERP?',
                        'a' => 'Travio is a comprehensive SaaS solution designed specifically for travel agencies. It helps you manage air ticketing, visa processing, tour packages, hotel bookings, customer relationships, and financial analytics all from one platform.'
                    ],
                    [
                        'q' => 'Is there a free trial available?',
                        'a' => 'Yes! We offer a 30-day free trial with full access to all features. No credit card required. You can start managing your travel agency operations immediately.'
                    ],
                    [
                        'q' => 'Can I switch plans later?',
                        'a' => 'Absolutely. You can upgrade, downgrade, or cancel your subscription at any time. Changes take effect at the start of your next billing cycle.'
                    ],
                    [
                        'q' => 'Is my data secure?',
                        'a' => 'Yes, we take security seriously. All data is encrypted in transit and at rest. We use industry-standard security practices and regular backups to ensure your business data is always safe.'
                    ],
                    [
                        'q' => 'Do you offer customer support?',
                        'a' => 'Yes, all plans include email support. We also provide documentation, video tutorials, and a knowledge base to help you get the most out of Travio.'
                    ],
                    [
                        'q' => 'Can I add multiple staff members?',
                        'a' => 'Yes, all paid plans include multiple staff accounts with granular permission controls. You can add as many team members as you need and assign specific roles and permissions.'
                    ]
                ];
                foreach ($faqs as $index => $faq): ?>
                    <div class="sz-faq-item">
                        <button class="sz-faq-question" onclick="toggleFAQ(this)">
                            <span><?= $faq['q'] ?></span>
                            <span class="sz-faq-icon"><i class="fa-solid fa-chevron-down"></i></span>
                        </button>
                        <div class="sz-faq-answer">
                            <?= $faq['a'] ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- CTA SECTION - Redesigned to match Vireo "Build your next dashboard" -->
    <!-- ============================================================ -->
    <section class="py-24 relative overflow-hidden" style="background: var(--sz-dark-deep);">
        <div class="sz-blob absolute top-0 left-1/2 -translate-x-1/2 -mt-20 w-96 h-96 bg-[#2BC4B0]/15 rounded-full"></div>
        <div class="sz-blob absolute bottom-0 right-0 -mb-20 -mr-20 w-80 h-80 bg-[#61DAFB]/10 rounded-full"></div>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <!-- Badge -->
            <div class="sz-badge" style="background: rgba(43,196,176,0.15); border: 1px solid rgba(43,196,176,0.3); color: var(--sz-teal); margin-bottom: 24px;">
                <span class="sz-pulse-dot"></span>
                Get Started Today
            </div>

            <!-- Heading -->
            <h2 class="sz-display text-4xl lg:text-5xl font-bold text-white leading-tight mb-6">
                Build Your Travel Business<br>
                <span style="background: linear-gradient(135deg, #2BC4B0, #61DAFB); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">With Travio</span>
            </h2>

            <!-- Description -->
            <p class="text-lg text-[#9AA4B2] max-w-2xl mx-auto mb-10 leading-relaxed">
                Join hundreds of travel agencies already using Travio to streamline operations, 
                boost sales, and grow their business. Start your free trial today.
            </p>

            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="/register" class="sz-glow-btn-filled text-lg px-10 py-4" style="height: auto;">
                    Start Free Trial
                    <i class="fa-solid fa-arrow-right ml-2"></i>
                </a>
                <a href="#" class="sz-btn-outline-light text-lg px-10 py-4" style="height: auto;">
                    <i class="fa-solid fa-circle-play"></i>
                    Watch Demo
                </a>
            </div>

            <!-- Trust Indicators -->
            <div class="mt-12 flex flex-wrap items-center justify-center gap-8 text-sm text-[#68727F]">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-check-circle" style="color: var(--sz-teal);"></i>
                    30-day free trial
                </span>
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-check-circle" style="color: var(--sz-teal);"></i>
                    No credit card required
                </span>
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-check-circle" style="color: var(--sz-teal);"></i>
                    Cancel anytime
                </span>
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-check-circle" style="color: var(--sz-teal);"></i>
                    24/7 support
                </span>
            </div>
        </div>
    </section>

    <!-- ============================================================ -->
    <!-- FOOTER                                                        -->
    <!-- ============================================================ -->
    <footer style="background: #0A0C11; border-top: 1px solid rgba(255,255,255,0.06);" class="pt-16 pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12 mb-8 border-b border-[rgba(255,255,255,0.06)]">
            <!-- 4-column grid: 2 cols on mobile, 4 on desktop -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-10">

                <!-- Col 1: Logo & About -->
                <div class="col-span-2 md:col-span-1">
                    <div class="flex items-center gap-2 mb-5">
                        <div class="w-8 h-8 bg-gradient-to-br from-[#2BC4B0] to-[#61DAFB] rounded-lg flex items-center justify-center text-white">
                            <i class="fa-solid fa-plane-departure text-sm"></i>
                        </div>
                        <h1 class="sz-display text-xl font-bold text-white tracking-tight">Travio</h1>
                    </div>
                    <p class="text-[#68727F] text-sm leading-relaxed mb-6">The premium ERP designed exclusively to help travel agencies automate and scale their operations.</p>
                    <div class="flex items-center gap-4">
                        <a href="https://facebook.com" target="_blank" rel="noopener" class="w-9 h-9 rounded-xl flex items-center justify-center transition" style="background: rgba(255,255,255,0.06); color: #9AA4B2;" onmouseover="this.style.background='rgba(24,119,242,0.2)';this.style.color='#4F9EF7'" onmouseout="this.style.background='rgba(255,255,255,0.06)';this.style.color='#9AA4B2'">
                            <i class="fa-brands fa-facebook-f text-sm"></i>
                        </a>
                        <a href="https://youtube.com" target="_blank" rel="noopener" class="w-9 h-9 rounded-xl flex items-center justify-center transition" style="background: rgba(255,255,255,0.06); color: #9AA4B2;" onmouseover="this.style.background='rgba(255,0,0,0.2)';this.style.color='#FF4444'" onmouseout="this.style.background='rgba(255,255,255,0.06)';this.style.color='#9AA4B2'">
                            <i class="fa-brands fa-youtube text-sm"></i>
                        </a>
                        <a href="https://instagram.com" target="_blank" rel="noopener" class="w-9 h-9 rounded-xl flex items-center justify-center transition" style="background: rgba(255,255,255,0.06); color: #9AA4B2;" onmouseover="this.style.background='rgba(225,48,108,0.2)';this.style.color='#E1306C'" onmouseout="this.style.background='rgba(255,255,255,0.06)';this.style.color='#9AA4B2'">
                            <i class="fa-brands fa-instagram text-sm"></i>
                        </a>
                    </div>
                </div>

                <!-- Col 2: Company -->
                <div>
                    <h4 class="text-white font-bold mb-5 uppercase tracking-wider text-xs">Company</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="#" class="text-[#9AA4B2] hover:text-[#2BC4B0] transition">Home</a></li>
                        <li><a href="#features" class="text-[#9AA4B2] hover:text-[#2BC4B0] transition">Features</a></li>
                        <li><a href="#pricing" class="text-[#9AA4B2] hover:text-[#2BC4B0] transition">Pricing</a></li>
                        <li><a href="#faq" class="text-[#9AA4B2] hover:text-[#2BC4B0] transition">FAQ</a></li>
                    </ul>
                </div>

                <!-- Col 3: Portal -->
                <div>
                    <h4 class="text-white font-bold mb-5 uppercase tracking-wider text-xs">Portal</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="/login" class="text-[#9AA4B2] hover:text-[#2BC4B0] transition">Agency Login</a></li>
                        <li><a href="/register" class="text-[#9AA4B2] hover:text-[#2BC4B0] transition">Agency Register</a></li>
                        <li><a href="#" class="text-[#9AA4B2] hover:text-[#2BC4B0] transition">Documentation</a></li>
                        <li><a href="/travio-bangla" class="text-[#2BC4B0] hover:text-[#1FB8A4] transition font-semibold">Bangla Portal</a></li>
                    </ul>
                </div>

                <!-- Col 4: Contact Us -->
                <div>
                    <h4 class="text-white font-bold mb-5 uppercase tracking-wider text-xs">Contact Us</h4>
                    <ul class="space-y-4 text-sm">
                        <li>
                            <a href="https://wa.me/8801XXXXXXXXX" target="_blank" rel="noopener" class="flex items-start gap-3 text-[#9AA4B2] hover:text-[#25D366] transition group">
                                <i class="fa-brands fa-whatsapp text-base mt-0.5 text-[#25D366]"></i>
                                <span>+880 1XXX-XXXXXX</span>
                            </a>
                        </li>
                        <li>
                            <a href="mailto:support@travioerp.com" class="flex items-start gap-3 text-[#9AA4B2] hover:text-[#2BC4B0] transition">
                                <i class="fa-solid fa-envelope text-base mt-0.5" style="color: var(--sz-teal);"></i>
                                <span>support@travioerp.com</span>
                            </a>
                        </li>
                        <li>
                            <a href="https://facebook.com" target="_blank" rel="noopener" class="flex items-start gap-3 text-[#9AA4B2] hover:text-[#4F9EF7] transition">
                                <i class="fa-brands fa-facebook-f text-base mt-0.5 text-[#4F9EF7]"></i>
                                <span>Travel Agency Solution - Travio</span>
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
            <span>&copy; <?= date('Y') ?> Travio ERP. All rights reserved.</span>
            <div class="flex items-center gap-5">
                <a href="#" class="hover:text-[#2BC4B0] transition">Privacy Policy</a>
                <a href="#" class="hover:text-[#2BC4B0] transition">Terms of Service</a>
            </div>
        </div>
    </footer>
    </div>

    <!-- ============================================================ -->
    <!-- FAQ Accordion JavaScript                                      -->
    <!-- ============================================================ -->
    <script>
    function toggleFAQ(button) {
        const answer = button.nextElementSibling;
        const isOpen = answer.classList.contains('open');
        
        // Close all other FAQs
        document.querySelectorAll('#sz-home .sz-faq-answer').forEach(el => {
            el.classList.remove('open');
            el.previousElementSibling.classList.remove('active');
        });
        
        if (!isOpen) {
            answer.classList.add('open');
            button.classList.add('active');
        }
    }

    // Open first FAQ by default
    document.addEventListener('DOMContentLoaded', function() {
        const firstFaq = document.querySelector('#sz-home .sz-faq-question');
        if (firstFaq) {
            firstFaq.click();
        }
    });
    </script>

<?php }