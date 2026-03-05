import {
  ArrowLeft,
  ArrowRight,
  BadgeCheck,
  BookOpen,
  Briefcase,
  CircleDollarSign,
  GraduationCap,
  ShieldCheck,
  Sparkles,
  UploadCloud,
  Wallet,
} from "lucide-react";
import Image from "../../assets/Image";

const featuredTracks = [
  {
    id: "track-1",
    courseKey: "web3-fundamentals",
    title: "Web3 Fundamentals",
    level: "Beginner",
    duration: "6 hrs",
    lessons: 18,
    earnPotential: "Up to 250 EXA",
  },
  {
    id: "track-2",
    courseKey: "defi-strategy-lab",
    title: "DeFi Strategy Lab",
    level: "Intermediate",
    duration: "5 hrs",
    lessons: 14,
    earnPotential: "Up to 420 EXA",
  },
  {
    id: "track-3",
    courseKey: "rwa-tokenization",
    title: "RWA and Tokenization",
    level: "Advanced",
    duration: "4 hrs",
    lessons: 10,
    earnPotential: "Up to 600 EXA",
  },
  {
    id: "track-4",
    courseKey: "web-development",
    title: "Web Development",
    level: "Beginner",
    duration: "8 hrs",
    lessons: 20,
    earnPotential: "Up to 320 EXA",
  },
];

const platformStats = [
  { label: "Active Learners", value: "24,800+" },
  { label: "EXA Rewards Paid", value: "9.4M" },
  { label: "Scholarships Granted", value: "1,120+" },
  { label: "Hiring Partners", value: "36" },
];

const journeySteps = [
  {
    step: "01",
    title: "Pick a Learning Path",
    detail: "Choose beginner to advanced tracks aligned with high-demand digital skills.",
  },
  {
    step: "02",
    title: "Complete Skill Milestones",
    detail: "Finish lessons, projects, and assessments with real proof-of-skill checkpoints.",
  },
  {
    step: "03",
    title: "Earn EXA Rewards",
    detail: "Receive structured token rewards for verified progress and assessment completion.",
  },
  {
    step: "04",
    title: "Unlock Career Access",
    detail: "Use certificates and portfolio proof to access mentors, jobs, and funding opportunities.",
  },
];

const trustSignals = [
  "Blockchain-backed completion and certification records",
  "Verified mentor and educator onboarding standards",
  "Transparent scholarship review and approval workflow",
  "Global learner support and measurable employment outcomes",
];

function EdTech({
  onBack,
  onOpenBecomeEducator,
  onOpenApplyScholarship,
  onOpenCourseUpload,
  onOpenStartLearning = () => {},
  onOpenInstructorWorkshop,
  onOpenInstructorDashboard = () => {},
}) {
  return (
    <main className="min-h-screen text-white exa-bg app-shell">
      <div className="container mx-auto w-full max-w-sm px-3 pb-6 pt-4 sm:max-w-lg sm:px-4 sm:pt-6 md:max-w-2xl lg:max-w-5xl xl:max-w-6xl">
        <div className="rounded-3xl p-4 shadow-xl glass-card sm:p-6">
          <header className="rounded-3xl border border-[#D4AF37]/30 bg-gradient-to-br from-[#0B0B0B]/95 via-[#151515]/90 to-[#0B0B0B]/95 p-5">
            <div className="flex items-center justify-between gap-3">
              <div className="flex items-center gap-3">
                <div className="flex h-11 w-11 items-center justify-center rounded-2xl border border-[#D4AF37]/60 bg-[#0B0B0B]/80">
                  <img src={Image.edu} alt="ExaEarn EdTech" className="h-6 w-6 object-contain" />
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.24em] text-[#D4AF37]/80">ExaEarn</p>
                  <p className="text-sm text-[#F8F8F8]/75">EdTech</p>
                </div>
              </div>
              {onBack ? (
                <button type="button" onClick={onBack} className="btn-outline inline-flex items-center gap-2">
                  <ArrowLeft className="h-4 w-4" />
                  Back
                </button>
              ) : null}
            </div>

            <div className="mt-5 grid gap-5 lg:grid-cols-[1.18fr_0.82fr]">
              <div>
                <p className="inline-flex items-center gap-2 rounded-full border border-[#D4AF37]/40 bg-[#D4AF37]/10 px-3 py-1 text-xs font-semibold text-[#D4AF37]">
                  <Sparkles className="h-3.5 w-3.5" />
                  Earn While You Learn
                </p>
                <h1 className="mt-3 font-['Sora'] text-3xl font-semibold leading-tight text-[#F8F8F8] sm:text-5xl">
                  Learn Skills. Build Proof. Earn Value.
                </h1>
                <p className="mt-3 max-w-2xl text-sm leading-relaxed text-[#F8F8F8]/78 sm:text-base">
                  ExaEarn EdTech combines education, rewards, and verified digital credentials so learners can grow income while
                  growing competence.
                </p>
                <div className="mt-5 flex flex-wrap gap-3">
                  <button type="button" onClick={onOpenStartLearning} className="btn-gold">
                    Start Learning
                  </button>
                  <button type="button" onClick={onOpenApplyScholarship} className="btn-outline">
                    Apply Scholarship
                  </button>
                  <button type="button" onClick={onOpenBecomeEducator} className="btn-outline">
                    Become an Educator
                  </button>
                </div>
              </div>

              <aside className="grid gap-3">
                <div className="rounded-2xl border border-[#D4AF37]/30 bg-[#D4AF37]/10 p-4">
                  <p className="text-xs uppercase tracking-[0.18em] text-[#D4AF37]/85">Current Learning Wallet</p>
                  <p className="mt-1 text-2xl font-semibold text-[#D4AF37]">3,250 EXA</p>
                  <p className="mt-1 text-xs text-[#F8F8F8]/72">Rewards available from completed pathways and quizzes.</p>
                </div>
                <div className="rounded-2xl border border-[#F8F8F8]/15 bg-[#0B0B0B]/60 p-4">
                  <p className="text-xs uppercase tracking-[0.18em] text-[#D4AF37]/85">This Week Focus</p>
                  <p className="mt-1 text-sm font-semibold text-[#F8F8F8]">Complete 2 modules to unlock +120 EXA bonus</p>
                  <div className="mt-3 h-2 overflow-hidden rounded-full bg-[#F8F8F8]/15">
                    <div className="h-full w-[68%] rounded-full bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020]" />
                  </div>
                </div>
              </aside>
            </div>
          </header>

          <section className="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {platformStats.map((stat) => (
              <article key={stat.label} className="rounded-2xl border border-[#F8F8F8]/14 bg-[#0B0B0B]/60 p-4 text-center shadow-cosmic-card">
                <p className="text-xs text-[#F8F8F8]/65">{stat.label}</p>
                <p className="mt-2 text-xl font-semibold text-[#D4AF37]">{stat.value}</p>
              </article>
            ))}
          </section>

          <section className="mt-6 rounded-2xl border border-[#F8F8F8]/14 bg-[#0B0B0B]/60 p-4 sm:p-5">
            <div className="flex items-center gap-2 text-[#D4AF37]">
              <BadgeCheck className="h-4 w-4" />
              <h2 className="font-['Sora'] text-lg font-semibold text-[#F8F8F8]">Choose Your Path</h2>
            </div>
            <div className="mt-4 grid gap-3 lg:grid-cols-3">
              <button
                type="button"
                onClick={() => onOpenCourseUpload("web3-fundamentals")}
                className="group rounded-2xl border border-[#F8F8F8]/15 bg-[#0B0B0B]/70 p-4 text-left transition-all duration-300 hover:-translate-y-1 hover:border-[#D4AF37]/60 hover:shadow-button-glow"
              >
                <BookOpen className="h-5 w-5 text-[#D4AF37]" />
                <p className="mt-3 text-base font-semibold text-[#F8F8F8]">I want to learn and earn</p>
                <p className="mt-1 text-xs text-[#F8F8F8]/70">Access high-value courses with EXA rewards and proof-of-skill certification.</p>
                <p className="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-[#D4AF37]">Start path <ArrowRight className="h-3.5 w-3.5" /></p>
              </button>

              <button
                type="button"
                onClick={onOpenBecomeEducator}
                className="group rounded-2xl border border-[#F8F8F8]/15 bg-[#0B0B0B]/70 p-4 text-left transition-all duration-300 hover:-translate-y-1 hover:border-[#D4AF37]/60 hover:shadow-button-glow"
              >
                <GraduationCap className="h-5 w-5 text-[#D4AF37]" />
                <p className="mt-3 text-base font-semibold text-[#F8F8F8]">I want to teach and monetize</p>
                <p className="mt-1 text-xs text-[#F8F8F8]/70">Create learning programs, grow audience, and earn from verified outcomes.</p>
                <p className="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-[#D4AF37]">Apply now <ArrowRight className="h-3.5 w-3.5" /></p>
              </button>

              <button
                type="button"
                onClick={onOpenApplyScholarship}
                className="group rounded-2xl border border-[#F8F8F8]/15 bg-[#0B0B0B]/70 p-4 text-left transition-all duration-300 hover:-translate-y-1 hover:border-[#D4AF37]/60 hover:shadow-button-glow"
              >
                <Briefcase className="h-5 w-5 text-[#D4AF37]" />
                <p className="mt-3 text-base font-semibold text-[#F8F8F8]">I need scholarship support</p>
                <p className="mt-1 text-xs text-[#F8F8F8]/70">Get sponsored access to premium tracks and guided mentorship cohorts.</p>
                <p className="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-[#D4AF37]">Request funding <ArrowRight className="h-3.5 w-3.5" /></p>
              </button>
            </div>
          </section>

          <section className="mt-6 grid gap-5 lg:grid-cols-[1.05fr_0.95fr]">
            <article className="rounded-2xl border border-[#F8F8F8]/14 bg-[#0B0B0B]/60 p-4 sm:p-5">
              <div className="flex items-center gap-2 text-[#D4AF37]">
                <CircleDollarSign className="h-4 w-4" />
                <h2 className="font-['Sora'] text-lg font-semibold text-[#F8F8F8]">How Learning Converts to Earnings</h2>
              </div>
              <div className="mt-4 space-y-3">
                {journeySteps.map((item) => (
                  <div key={item.step} className="rounded-xl border border-[#F8F8F8]/12 bg-[#0B0B0B]/70 p-3">
                    <p className="text-xs font-semibold text-[#D4AF37]">Step {item.step}</p>
                    <p className="mt-1 text-sm font-semibold text-[#F8F8F8]">{item.title}</p>
                    <p className="mt-1 text-xs text-[#F8F8F8]/70">{item.detail}</p>
                  </div>
                ))}
              </div>
            </article>

            <article className="rounded-2xl border border-[#F8F8F8]/14 bg-[#0B0B0B]/60 p-4 sm:p-5">
              <div className="flex items-center gap-2 text-[#D4AF37]">
                <Wallet className="h-4 w-4" />
                <h2 className="font-['Sora'] text-lg font-semibold text-[#F8F8F8]">Reward Engine</h2>
              </div>
              <div className="mt-4 rounded-2xl border border-[#D4AF37]/25 bg-[#D4AF37]/10 p-4">
                <p className="text-xs uppercase tracking-[0.16em] text-[#D4AF37]/85">Milestone Bonus</p>
                <p className="mt-1 text-xl font-semibold text-[#D4AF37]">+120 EXA Pending</p>
                <p className="mt-1 text-xs text-[#F8F8F8]/75">Finish your current assessments to unlock reward settlement.</p>
              </div>
              <div className="mt-3 grid gap-2 text-xs">
                <p className="rounded-xl border border-[#F8F8F8]/12 bg-[#0B0B0B]/70 px-3 py-2 text-[#F8F8F8]/80">Course completion rewards (base)</p>
                <p className="rounded-xl border border-[#F8F8F8]/12 bg-[#0B0B0B]/70 px-3 py-2 text-[#F8F8F8]/80">Assessment score multipliers</p>
                <p className="rounded-xl border border-[#F8F8F8]/12 bg-[#0B0B0B]/70 px-3 py-2 text-[#F8F8F8]/80">Consistency streak bonuses</p>
              </div>
              <button type="button" onClick={onOpenInstructorDashboard} className="mt-4 w-full rounded-xl border border-[#D4AF37] bg-gradient-to-r from-[#D4AF37] via-[#e7c766] to-[#be9020] px-4 py-3 text-sm font-semibold text-[#0B0B0B] shadow-[0_0_22px_rgba(212,175,55,0.32)]">
                Open Earnings Dashboard
              </button>
            </article>
          </section>

          <section className="mt-6 rounded-2xl border border-[#F8F8F8]/14 bg-[#0B0B0B]/60 p-4 sm:p-5">
            <div className="flex items-center justify-between gap-3">
              <div className="flex items-center gap-2 text-[#D4AF37]">
                <BookOpen className="h-4 w-4" />
                <h2 className="font-['Sora'] text-lg font-semibold text-[#F8F8F8]">Featured Learning Tracks</h2>
              </div>
              <button type="button" onClick={() => onOpenCourseUpload("web3-fundamentals")} className="text-xs font-semibold text-[#D4AF37]">
                View all tracks
              </button>
            </div>
            <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              {featuredTracks.map((track) => (
                <button
                  key={track.id}
                  type="button"
                  onClick={() => onOpenCourseUpload(track.courseKey)}
                  className="group rounded-2xl border border-[#F8F8F8]/14 bg-[#0B0B0B]/70 p-3 text-left transition-all duration-300 hover:-translate-y-1 hover:border-[#D4AF37]/60 hover:shadow-button-glow"
                >
                  <div className="relative h-24 overflow-hidden rounded-xl">
                    <img src={Image.edu} alt={track.title} className="h-full w-full object-cover opacity-75 transition duration-300 group-hover:opacity-95" />
                    <div className="absolute inset-0 bg-gradient-to-t from-[#0B0B0B]/80 via-transparent to-transparent" />
                    <span className="absolute right-2 top-2 rounded-full border border-[#D4AF37]/45 bg-[#0B0B0B]/70 px-2 py-0.5 text-[10px] font-semibold text-[#D4AF37]">
                      {track.level}
                    </span>
                  </div>
                  <p className="mt-3 text-sm font-semibold text-[#F8F8F8]">{track.title}</p>
                  <p className="mt-1 text-xs text-[#F8F8F8]/70">{track.lessons} lessons • {track.duration}</p>
                  <p className="mt-2 text-xs font-semibold text-[#D4AF37]">{track.earnPotential}</p>
                </button>
              ))}
            </div>
          </section>

          <section className="mt-6 grid gap-5 lg:grid-cols-2">
            <article className="rounded-2xl border border-[#F8F8F8]/14 bg-[#0B0B0B]/60 p-4 sm:p-5">
              <div className="flex items-center gap-2 text-[#D4AF37]">
                <ShieldCheck className="h-4 w-4" />
                <h2 className="font-['Sora'] text-lg font-semibold text-[#F8F8F8]">Trust, Standards, and Transparency</h2>
              </div>
              <div className="mt-4 grid gap-2">
                {trustSignals.map((signal) => (
                  <p key={signal} className="rounded-xl border border-[#F8F8F8]/12 bg-[#0B0B0B]/70 px-3 py-2 text-xs text-[#F8F8F8]/78">
                    {signal}
                  </p>
                ))}
              </div>
            </article>

            <article className="rounded-2xl border border-[#F8F8F8]/14 bg-[#0B0B0B]/60 p-4 sm:p-5">
              <div className="flex items-center gap-2 text-[#D4AF37]">
                <UploadCloud className="h-4 w-4" />
                <h2 className="font-['Sora'] text-lg font-semibold text-[#F8F8F8]">Educator Growth Hub</h2>
              </div>
              <p className="mt-2 text-xs text-[#F8F8F8]/72">
                Publish premium courses, run workshops, and monetize impact through verified learner outcomes.
              </p>
              <div className="mt-4 grid gap-2">
                <button type="button" onClick={onOpenInstructorWorkshop} className="btn-gold text-sm">
                  Launch Instructor Workshop
                </button>
                <button type="button" onClick={onOpenInstructorDashboard} className="btn-outline text-sm">
                  Open Instructor Dashboard
                </button>
              </div>
            </article>
          </section>

          <section className="mt-6 rounded-2xl border border-[#D4AF37]/30 bg-gradient-to-r from-[#D4AF37]/12 via-[#D4AF37]/6 to-transparent p-5 text-center sm:p-6">
            <h2 className="font-['Sora'] text-3xl font-semibold text-[#F8F8F8] sm:text-4xl">Education That Compounds Into Wealth</h2>
            <p className="mx-auto mt-2 max-w-3xl text-sm text-[#F8F8F8]/75 sm:text-base">
              Move from learner to earner with structured rewards, verified credentials, and access to real opportunities.
            </p>
            <div className="mt-4 flex flex-wrap justify-center gap-3">
              <button type="button" onClick={onOpenStartLearning} className="btn-gold">
                Start Learning Now
              </button>
              <button type="button" onClick={onOpenApplyScholarship} className="btn-outline">
                Apply for Scholarship
              </button>
            </div>
          </section>
        </div>
      </div>
    </main>
  );
}

export default EdTech;
